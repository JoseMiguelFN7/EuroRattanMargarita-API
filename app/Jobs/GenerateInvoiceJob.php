<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\OrderInvoiceMail;

class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30];

    public $order;
    public $generatedInvoice;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function handle(): void
    {
        // Forzamos la carga desde la DB para evitar el bug del caché con las monedas
        $this->order->load(['user', 'products', 'payments.currency']);

        DB::transaction(function () {
            
            if ($this->order->invoice()->exists()) {
                return;
            }

            $lastInvoice = Invoice::lockForUpdate()->orderBy('invoice_number', 'desc')->first();
            $nextNumber = $lastInvoice ? (intval($lastInvoice->invoice_number) + 1) : 1;
            $formattedNumber = str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
            
            $invoiceNumber = $formattedNumber; 
            $controlNumber = '00-' . $formattedNumber;

            // --- CÁLCULO DE TOTALES DE LA ORDEN ---
            $totalOrderUsd = 0;
            $orderRate = (float) $this->order->exchange_rate;

            foreach ($this->order->products as $product) {
                $qty = $product->pivot->quantity;
                $price = $product->pivot->price;
                $discountPercent = $product->pivot->discount ?? 0;
                
                $lineSubtotalUsd = $qty * $price;
                $discountAmountUsd = $lineSubtotalUsd * ($discountPercent / 100);
                $totalOrderUsd += ($lineSubtotalUsd - $discountAmountUsd);
            }

            $igtfUsd = (float) $this->order->igtf_amount;

            $totalAmountBs = $totalOrderUsd * $orderRate;
            $igtfBs = $igtfUsd * $orderRate;
            $granTotalOrderBs = $totalAmountBs + $igtfBs;

            // --- CÁLCULO DE LO QUE PAGÓ EL CLIENTE EN BOLÍVARES ---
            $verifiedPayments = $this->order->payments->where('status', 'verified');
            
            $paymentCurrency = 'USD';
            $totalPaid = 0; 
            
            if ($verifiedPayments->isNotEmpty()) {
                $firstPayment = $verifiedPayments->first();
                
                if ($firstPayment->currency) {
                    $paymentCurrency = strtoupper($firstPayment->currency->code);
                } else {
                    if ((float)$firstPayment->exchange_rate > 10) {
                        $paymentCurrency = 'VES';
                    }
                }

                $totalPaid = $verifiedPayments->sum(function($p) {
                    return (float) $p->amount;
                });
            }

            // Aquí convertimos lo pagado a Bolívares dependiendo de la moneda de origen
            $totalPaidBs = 0;
            if ($paymentCurrency === 'VES') {
                $totalPaidBs = $totalPaid;
            } else {
                $totalPaidBs = $totalPaid * $orderRate;
            }

            // --- CREAR EL REGISTRO DE LA FACTURA ---
            $invoice = Invoice::create([
                'order_id'        => $this->order->id,
                'invoice_number'  => $invoiceNumber,
                'control_number'  => $controlNumber,
                'client_name'     => $this->order->user->name,
                'client_document' => $this->order->user->document ?? 'V-00000000', 
                'client_address'  => $this->order->user->address ?? 'Porlamar, Nueva Esparta',
                
                'exempt_amount'   => round($totalAmountBs, 2), 
                'tax_base_amount' => 0,
                'tax_percentage'  => 0,
                'tax_amount'      => 0,
                'igtf_amount'     => round($igtfBs, 2), 
                'total_amount'    => round($granTotalOrderBs, 2),
                
                'paid_amount'     => round($totalPaidBs, 2), // <-- GUARDAMOS LO QUE PAGÓ

                'emitted_at'      => now(),
            ]);

            $invoice->refresh();

            // --- GENERACIÓN DEL PDF ---
            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
                'order'   => $this->order
            ]);

            $fileName = 'comprobante_' . $invoiceNumber . '.pdf';
            $filePath = 'invoices/' . $fileName;

            Storage::disk('public')->put($filePath, $pdf->output());

            $invoice->update(['pdf_url' => $filePath]);

            $this->generatedInvoice = $invoice;

        });

        // --- ENVIAR EL CORREO AL USUARIO ---
        if (isset($this->generatedInvoice) && $this->order->user) {
            Mail::to($this->order->user->email)->send(new OrderInvoiceMail($this->order, $this->generatedInvoice));
        }
    }

    public function failed(\Throwable $exception)
    {
        // Esto se ejecuta solo si agotó todos los $tries
        Log::error(
            "ALERTA CRÍTICA: No se pudo generar/enviar el comprobante de la orden {$this->order->code}. " .
            "Error: " . $exception->getMessage()
        );
    }
}