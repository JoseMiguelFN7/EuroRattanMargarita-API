<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        // 1. Usamos with('order') para cargar la relación y evitar el problema N+1
        $query = Invoice::with('order');

        // 2. Buscador actualizado
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', "%{$searchTerm}%")
                  ->orWhere('control_number', 'like', "%{$searchTerm}%")
                  ->orWhere('client_name', 'like', "%{$searchTerm}%")
                  ->orWhere('client_document', 'like', "%{$searchTerm}%")
                  // Buscamos también dentro de la relación de la orden
                  ->orWhereHas('order', function ($orderQuery) use ($searchTerm) {
                      // Nota: Cambia 'code' si tu columna en la tabla orders se llama distinto (ej. 'reference' o 'tracking_code')
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
                
            // Inyectamos el código de la orden directamente en el primer nivel del JSON
            // (Cambia 'code' por el nombre real del campo en tu modelo Order)
            if ($invoice->order) {
                $invoice->order_code = $invoice->order->code;
                $invoice->order_exchange_rate = $invoice->order->exchange_rate;
            } else {
                $invoice->order_code = null;
                $invoice->order_exchange_rate = null;
            }
            
            // Opcional: Ocultamos el objeto completo 'order' para que el JSON no sea tan pesado,
            // ya que el frontend normalmente solo necesita el código.
            $invoice->makeHidden('order');

            return $invoice;
        });

        return response()->json($invoices);
    }
}
