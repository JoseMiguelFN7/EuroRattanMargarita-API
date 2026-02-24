<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        // 1. Carga ansiosa para evitar N+1
        $query = Invoice::with('order');

        // 2. Buscador
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', "%{$searchTerm}%")
                  ->orWhere('control_number', 'like', "%{$searchTerm}%")
                  ->orWhere('client_name', 'like', "%{$searchTerm}%")
                  ->orWhere('client_document', 'like', "%{$searchTerm}%")
                  ->orWhereHas('order', function ($orderQuery) use ($searchTerm) {
                      $orderQuery->where('code', 'like', "%{$searchTerm}%"); 
                  });
            });
        }

        $invoices = $query->orderBy('emitted_at', 'desc')->paginate($perPage);

        // 3. Transformación de la data
        $invoices->through(function ($invoice) {
            
            // Link de descarga
            $invoice->download_link = $invoice->pdf_url 
                ? asset('storage/' . $invoice->pdf_url) 
                : null;
                
            // Inyectamos datos de la orden
            if ($invoice->order) {
                $invoice->order_code = $invoice->order->code;
                $invoice->order_exchange_rate = $invoice->order->exchange_rate;
            } else {
                $invoice->order_code = null;
                $invoice->order_exchange_rate = null;
            }
            
            // Forzamos el casteo a float para que el frontend no reciba strings 
            // y pueda hacer sumas matemáticas si lo necesita en el dashboard
            $invoice->exempt_amount = (float) $invoice->exempt_amount;
            $invoice->tax_base_amount = (float) $invoice->tax_base_amount;
            $invoice->tax_amount = (float) $invoice->tax_amount; // Siempre será 0 por el Puerto Libre
            $invoice->igtf_amount = (float) $invoice->igtf_amount; // NUESTRO NUEVO CAMPO
            $invoice->total_amount = (float) $invoice->total_amount;
            
            // Ocultamos el objeto completo 'order'
            $invoice->makeHidden('order');

            return $invoice;
        });

        return response()->json($invoices);
    }
}
