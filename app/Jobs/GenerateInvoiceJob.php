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

class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Cargamos las relaciones necesarias si no venían cargadas
        $this->order->loadMissing(['user', 'products']);

        // 2. Iniciamos la transacción de base de datos
        DB::transaction(function () {
            
            // Seguro extra por si el Job se encoló dos veces por error
            if ($this->order->invoice()->exists()) {
                return;
            }

            // --- A. BLOQUEO PESIMISTA Y GENERACIÓN DE CONSECUTIVOS ---
            $lastInvoice = Invoice::lockForUpdate()->orderBy('invoice_number', 'desc')->first();
            $nextNumber = $lastInvoice ? (intval($lastInvoice->invoice_number) + 1) : 1;
            $formattedNumber = str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
            
            $invoiceNumber = $formattedNumber; 
            $controlNumber = '00-' . $formattedNumber;

            // --- B. CÁLCULO DE TOTALES (EN BOLÍVARES) ---
            $totalAmountBs = 0;
            $rate = $this->order->exchange_rate;

            foreach ($this->order->products as $product) {
                $qty = $product->pivot->quantity;
                $price = $product->pivot->price;
                $discountPercent = $product->pivot->discount ?? 0; // Ahora es un % (0-100)
                
                // Calculamos el monto base en USD primero
                $lineSubtotalUsd = $qty * $price;
                // Aplicamos el porcentaje de descuento
                $discountAmountUsd = $lineSubtotalUsd * ($discountPercent / 100);
                $finalLineUsd = $lineSubtotalUsd - $discountAmountUsd;

                // Convertimos a Bolívares al final
                $totalAmountBs += ($finalLineUsd * $rate);
            }

            // --- NUEVO: CÁLCULO DEL IGTF EN BOLÍVARES ---
            // Tomamos el IGTF en USD que guardamos en la orden y lo multiplicamos por la tasa
            $igtfBs = $this->order->igtf_amount * $rate;

            // --- C. CREAR EL REGISTRO ---
            $invoice = Invoice::create([
                'order_id'        => $this->order->id,
                'invoice_number'  => $invoiceNumber,
                'control_number'  => $controlNumber,
                'client_name'     => $this->order->user->name,
                'client_document' => $this->order->user->document ?? 'V-00000000', 
                'client_address'  => $this->order->user->address ?? 'Porlamar, Nueva Esparta',
                
                // Distribución de montos (En Bolívares)
                'exempt_amount'   => round($totalAmountBs, 2), // Solo el costo de los productos
                'tax_base_amount' => 0,
                'tax_percentage'  => 0,
                'tax_amount'      => 0,
                'igtf_amount'     => round($igtfBs, 2), // El impuesto guardado explícitamente
                
                // El Total final de la factura suma los productos exentos + el IGTF
                'total_amount'    => round($totalAmountBs + $igtfBs, 2),
                'emitted_at'      => now(),
            ]);

            $invoice->refresh();

            // --- D. GENERACIÓN DEL PDF ---
            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
                'order'   => $this->order
            ]);

            $fileName = 'comprobante_' . $invoiceNumber . '.pdf';
            $filePath = 'invoices/' . $fileName;

            Storage::disk('public')->put($filePath, $pdf->output());

            $invoice->update(['pdf_url' => $filePath]);

        });
    }
}