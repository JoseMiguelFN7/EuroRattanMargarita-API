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
            // lockForUpdate() congela la tabla Invoices para otras transacciones 
            // hasta que este Job termine, evitando números duplicados.
            $lastInvoice = Invoice::lockForUpdate()->orderBy('invoice_number', 'desc')->first();
            $nextNumber = $lastInvoice ? (intval($lastInvoice->invoice_number) + 1) : 1;
            $formattedNumber = str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
            
            $invoiceNumber = $formattedNumber; 
            $controlNumber = '00-' . $formattedNumber; // Formato típico venezolano

            // --- B. CÁLCULO DE TOTALES (EN BOLÍVARES) ---
            $totalAmountBs = 0;
            $rate = $this->order->exchange_rate; // Tomamos la tasa de la orden

            foreach ($this->order->products as $product) {
                // Cantidad * (Precio - Descuento)
                $qty = $product->pivot->quantity;
                $price = $product->pivot->price;
                $discount = $product->pivot->discount ?? 0;
                
                // Convertimos el subtotal a Bs antes de sumarlo al total de la factura
                $subtotalBs = $qty * ($price - $discount) * $rate;
                $totalAmountBs += $subtotalBs;
            }

            // --- C. CREAR EL REGISTRO ---
            $invoice = Invoice::create([
                'order_id'        => $this->order->id,
                'invoice_number'  => $invoiceNumber,
                'control_number'  => $controlNumber,
                'client_name'     => $this->order->user->name,
                'client_document' => $this->order->user->document ?? 'V-00000000', 
                'client_address'  => $this->order->user->address ?? 'Porlamar, Nueva Esparta',
                'exempt_amount'   => $totalAmountBs, // Guardamos los Bolívares
                'tax_base_amount' => 0,
                'tax_percentage'  => 0,
                'tax_amount'      => 0,
                'total_amount'    => $totalAmountBs,
                'emitted_at'      => now(),
            ]);

            // Obliga a Laravel a traer la versión fresca de la BD para que Blade no falle con la fecha
            $invoice->refresh();

            // --- D. GENERACIÓN DEL PDF ---
            // 1. Cargamos la vista de Blade y le pasamos los datos
            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
                'order'   => $this->order
            ]);

            // 2. Definimos el nombre y la ruta del archivo
            $fileName = 'factura_' . $invoiceNumber . '.pdf';
            $filePath = 'invoices/' . $fileName;

            // 3. Guardamos el PDF en el disco 'public' (storage/app/public/invoices/...)
            Storage::disk('public')->put($filePath, $pdf->output());

            // 4. Actualizamos el registro de la base de datos con la ruta
            $invoice->update(['pdf_url' => $filePath]);

        });
    }
}
