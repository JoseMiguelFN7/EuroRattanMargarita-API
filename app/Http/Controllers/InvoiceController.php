<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        // 1. Carga ansiosa para evitar N+1
        $query = Invoice::with('order');

        // 2. Buscador Abierto (Número de Factura, Nombre/Cédula del Cliente/Usuario, y Código de Orden)
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            
            $query->where(function($q) use ($searchTerm) {
                // Búsqueda en los campos directos de la factura
                $q->where('invoice_number', 'like', "%{$searchTerm}%")
                  ->orWhere('control_number', 'like', "%{$searchTerm}%")
                  ->orWhere('client_name', 'like', "%{$searchTerm}%")
                  ->orWhere('client_document', 'like', "%{$searchTerm}%")
                  
                  // Búsqueda en la orden y en el usuario asociado a esa orden
                  ->orWhereHas('order', function ($orderQuery) use ($searchTerm) {
                      $orderQuery->where('code', 'like', "%{$searchTerm}%")
                                 ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                                     $userQuery->where('name', 'like', "%{$searchTerm}%")
                                               ->orWhere('document', 'like', "%{$searchTerm}%");
                                 });
                  });
            });
        }

        // 3. Filtro por Rango de Fechas (Basado en emitted_at)
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('emitted_at', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
            $query->where('emitted_at', '<=', $endDate);
        }

        // 4. Paginación y Ordenamiento
        $invoices = $query->orderBy('emitted_at', 'desc')->paginate($perPage);

        // 5. Transformación de la data
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
            
            // Forzamos el casteo a float para que el frontend reciba números reales
            $invoice->exempt_amount = (float) $invoice->exempt_amount;
            $invoice->tax_base_amount = (float) $invoice->tax_base_amount;
            $invoice->tax_amount = (float) $invoice->tax_amount; 
            $invoice->igtf_amount = (float) $invoice->igtf_amount; 
            $invoice->total_amount = (float) $invoice->total_amount;
            
            // Ocultamos el objeto completo 'order'
            $invoice->makeHidden('order');

            return $invoice;
        });

        return response()->json($invoices);
    }

    /**
     * Verifica la autenticidad de un comprobante mediante su token de seguridad.
     */
    public function verifyToken(string $token)
    {
        // Buscamos la factura y cargamos la orden para saber su estatus y código actual
        $invoice = Invoice::with('order')->where('verification_token', $token)->first();

        if (!$invoice) {
            return response()->json([
                'valid'   => false,
                'message' => 'El código escaneado no pertenece a un comprobante válido registrado en el sistema.'
            ], 404);
        }

        $invoiceLink = $invoice->pdf_url ? asset('storage/' . $invoice->pdf_url) : null;

        // Si existe, devolvemos la verdad absoluta de la base de datos
        return response()->json([
            'valid'           => true,
            'message'         => 'Comprobante verificado exitosamente.',
            'data' => [
                'invoice_number'  => $invoice->invoice_number,
                'client_name'     => $invoice->client_name,
                'client_document' => $invoice->client_document,
                'emitted_at'      => $invoice->emitted_at->format('Y-m-d H:i:s'),
                
                // --- NUEVOS DATOS DE LA ORDEN ---
                'order_code'      => $invoice->order ? $invoice->order->code : 'N/A',
                'invoice_url'         => $invoiceLink,
            ]
        ]);
    }
}
