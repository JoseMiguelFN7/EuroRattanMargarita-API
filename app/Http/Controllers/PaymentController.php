<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Order;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        // 1. Eager loading base
        $query = Payment::with(['order.user', 'paymentMethod', 'currency']);

        // 2. Buscador Abierto (Referencia de pago, Código de orden o Nombre de usuario)
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            
            $query->where(function($q) use ($searchTerm) {
                // Buscamos en el número de referencia del pago
                $q->where('reference_number', 'like', "%{$searchTerm}%")
                  // Buscamos en el código de la orden asociada
                  ->orWhereHas('order', function ($orderQuery) use ($searchTerm) {
                      $orderQuery->where('code', 'like', "%{$searchTerm}%")
                                 // Buscamos en el nombre del usuario dueño de la orden
                                 ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                                     $userQuery->where('name', 'like', "%{$searchTerm}%");
                                 });
                  });
            });
        }

        // 3. Filtros Exactos (Status y Método de Pago)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->input('payment_method_id'));
        }

        // 4. Filtro por Rango de Fechas (Basado en created_at)
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        // 5. Ordenamiento, Paginación y Transformación
        $payments = $query->latest()
            ->paginate($request->input('per_page', 15))
            ->through(function ($payment) {
                
                $amount = (float) $payment->amount;
                $rate = (float) $payment->exchange_rate;

                $montoUsd = $amount;
                $montoBs = ($amount * $rate);

                return [
                    'id' => $payment->id,
                    'user' => [
                        'id' => $payment->order?->user?->id,
                        'name' => $payment->order?->user?->name,
                    ],
                    'order' => [
                        'id' => $payment->order?->id,
                        'code' => $payment->order?->code,
                    ],
                    'proof_image' => $payment->proof_image_url,
                    'reference_number' => $payment->reference_number,
                    'method' => [
                        'id' => $payment->paymentMethod?->id, // Te agregué el ID por si lo necesitas en el front
                        'name' => $payment->paymentMethod?->name
                    ],
                    'amount' => round($montoUsd, 2),
                    'rate' => $rate,
                    'total_ves' => round($montoBs, 2),
                    'status' => $payment->status,
                    'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($payments);
    }

    public function store(Request $request)
    {
        // 1. Validamos los datos entrantes
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'currency_id' => 'required|exists:currencies,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'required|string|max:255',
            'exchange_rate' => 'required|numeric|min:0',
            'proof_image' => 'required|image|mimes:jpeg,png,jpg,pdf|max:2048', // Máximo 2MB
        ]);

        // 2. Seguridad: Verificamos que la orden exista y pertenezca al usuario autenticado
        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', auth('sanctum')->id())
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'La orden no existe o no tienes permisos para acceder a ella.'
            ], 404);
        }

        // 3. Procesamos la imagen del comprobante
        $imagePath = null;
        if ($request->hasFile('proof_image')) {
            // Se guarda en storage/app/public/payments
            // Tu helper proof_image_url funcionará perfecto con esto
            $imagePath = $request->file('proof_image')->store('payments', 'public');
        }

        // 4. Creamos el pago
        $payment = Payment::create([
            'order_id' => $order->id,
            'currency_id' => $validated['currency_id'],
            'payment_method_id' => $validated['payment_method_id'],
            'amount' => $validated['amount'],
            'reference_number' => $validated['reference_number'],
            'exchange_rate' => $validated['exchange_rate'],
            'proof_image' => $imagePath,
            'status' => 'pending', // O el status inicial que manejes por defecto
        ]);

        // Opcional: Actualizar el status de la orden a 'verifying_payment'
        // basándome en el JSON que me pasaste antes
        $order->update(['status' => 'verifying_payment']);

        return response()->json([
            'message' => 'Pago registrado exitosamente.',
            'data' => [
                'id' => $payment->id,
                'comprobante' => $payment->proof_image_url,
                'status' => $payment->status
            ]
        ], 201);
    }

    public function verify(Request $request, $id)
    {
        // 1. Validar que envíen el booleano
        $request->validate([
            'is_approved' => 'required|boolean'
        ]);

        // 2. Buscar el pago y cargar su orden asociada
        $payment = Payment::with('order')->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        // 3. Lógica de Aprobación / Rechazo
        if ($request->is_approved) {
            // APROBAR
            $payment->update([
                'status'      => 'verified', 
                'verified_at' => now()       
            ]);

            // CAMBIO AQUÍ: La orden pasa a 'completed'
            $payment->order->update([
                'status' => 'completed' 
            ]);

            $message = 'Pago verificado. La orden ha sido marcada como completada.';
        } else {
            // RECHAZAR
            $payment->update([
                'status'      => 'rejected',
                'verified_at' => null 
            ]);

            // La orden regresa a 'pending_payment'
            $payment->order->update([
                'status' => 'pending_payment' 
            ]);

            $message = 'Pago rechazado. La orden ha vuelto a estado pendiente de pago.';
        }

        // 4. Retornar la respuesta con los estados actualizados
        return response()->json([
            'message'        => $message,
            'payment_status' => $payment->status,
            'order_status'   => $payment->order->status
        ], 200);
    }
}
