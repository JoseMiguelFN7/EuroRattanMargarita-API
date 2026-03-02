<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Order;
use App\Jobs\GenerateInvoiceJob;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentVerificationMail;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        // 1. Eager loading base
        $query = Payment::with(['order.user', 'paymentMethod', 'currency']);

        // 2. Buscador Abierto
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('reference_number', 'like', "%{$searchTerm}%")
                  ->orWhereHas('order', function ($orderQuery) use ($searchTerm) {
                      $orderQuery->where('code', 'like', "%{$searchTerm}%")
                                 ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                                     $userQuery->where('name', 'like', "%{$searchTerm}%");
                                 });
                  });
            });
        }

        // 3. Filtros Exactos
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->input('payment_method_id'));
        }

        // 4. Filtro por Rango de Fechas
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
                
                // Determinamos la moneda del pago
                $currencyCode = $payment->currency ? strtoupper($payment->currency->code) : 'USD';
                
                $montoUsd = 0;
                $montoBs = 0;

                // Hacemos el cruce correcto dependiendo de la moneda de origen
                if ($currencyCode === 'VES') {
                    $montoBs = $amount;
                    $montoUsd = $rate > 0 ? ($amount / $rate) : 0;
                } else {
                    $montoUsd = $amount;
                    $montoBs = $amount * $rate;
                }

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
                        'id' => $payment->paymentMethod?->id,
                        'name' => $payment->paymentMethod?->name
                    ],
                    
                    // --- NUEVOS CAMPOS FORMATEADOS ---
                    'original_amount'   => $amount,
                    'original_currency' => $currencyCode,
                    
                    'amount_usd' => round($montoUsd, 2), // Para vistas o reportes en dólares
                    'total_ves'  => round($montoBs, 2),  // Para vistas o reportes en bolívares
                    'rate'       => $rate,
                    
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

    public function storeMany(Request $request)
    {
        // 1. Validamos los datos entrantes esperando un arreglo 'payments'
        $validator = Validator::make($request->all(), [
            'order_id'                     => 'required|exists:orders,id',
            'payments'                     => 'required|array|min:1',
            'payments.*.amount'            => 'required|numeric|min:0.01',
            'payments.*.currency_id'       => 'required|exists:currencies,id',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.reference_number'  => 'nullable|string|max:255',
            'payments.*.exchange_rate'     => 'required|numeric|min:0',
            'payments.*.proof_image'       => 'nullable|image|mimes:jpeg,png,jpg,pdf|max:2048', // Máximo 2MB por imagen
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Seguridad: Verificamos que la orden exista y pertenezca al usuario autenticado
        $order = Order::with('products')->where('id', $request->order_id)
            ->where('user_id', auth('sanctum')->id())
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'La orden no existe o no tienes permisos para acceder a ella.'
            ], 404);
        }

        // Validamos que la orden esté en un estatus donde tenga sentido recibir pagos
        if (!in_array($order->status, ['pending_payment', 'verifying_payment'])) {
            return response()->json([
                'message' => 'Esta orden ya está pagada o cancelada. No admite nuevos pagos.'
            ], 400);
        }

        // --- 3. REGLA DEL IGTF (El Impuesto de Schrödinger) ---
        // Verificamos si la orden viene de un estado "limpio" (todos sus pagos anteriores fueron rechazados)
        $activePaymentsCount = $order->payments()->whereIn('status', ['pending', 'verified'])->count();

        if ($activePaymentsCount === 0) {
            // Calculamos el subtotal base de los productos
            $subtotal = $order->products->sum(function ($product) {
                $base = $product->pivot->quantity * $product->pivot->price;
                $percent = $product->pivot->discount ?? 0;
                return $base * (1 - ($percent / 100));
            });

            $igtfAmount = 0;
            // Leemos el primer método de pago que el cliente está intentando usar AHORA
            // (Asumimos que todos los pagos del arreglo tienen la misma regla, como definimos antes)
            $firstMethod = \App\Models\PaymentMethod::find($request->payments[0]['payment_method_id']);
            
            if ($firstMethod && $firstMethod->applies_igtf) {
                $igtfAmount = round($subtotal * 0.03, 2);
            }

            // Actualizamos la orden con la nueva realidad del IGTF antes de guardar los pagos
            $order->update(['igtf_amount' => $igtfAmount]);
        }
        // ------------------------------------------------------

        DB::beginTransaction();
        $uploadedPaths = []; // Arreglo para rastrear imágenes en caso de fallo

        try {
            $createdPayments = [];

            // 4. Procesamos cada pago del arreglo
            foreach ($request->payments as $index => $paymentData) {
                $imagePath = null;
                
                // Extraemos y guardamos la imagen si viene en este índice específico
                if ($request->hasFile("payments.{$index}.proof_image")) {
                    $file = $request->file("payments.{$index}.proof_image");
                    $imagePath = $file->store('payments', 'public');
                    $uploadedPaths[] = $imagePath; // Lo guardamos en el historial de esta petición
                }

                // Creamos el registro del pago
                $payment = Payment::create([
                    'order_id'          => $order->id,
                    'currency_id'       => $paymentData['currency_id'],
                    'payment_method_id' => $paymentData['payment_method_id'],
                    'amount'            => $paymentData['amount'],
                    'reference_number'  => $paymentData['reference_number'] ?? null,
                    'exchange_rate'     => $paymentData['exchange_rate'],
                    'proof_image'       => $imagePath,
                    'status'            => 'pending', 
                ]);

                // Lo agregamos a la respuesta
                $createdPayments[] = [
                    'id'          => $payment->id,
                    'amount'      => $payment->amount,
                    'status'      => $payment->status,
                    'comprobante' => $payment->proof_image_url
                ];
            }

            // 5. Actualizamos el status de la orden para que el Admin sepa que hay dinero por revisar
            $order->update(['status' => 'verifying_payment']);

            DB::commit();

            return response()->json([
                'message' => count($createdPayments) > 1 
                                ? 'Pagos registrados exitosamente.' 
                                : 'Pago registrado exitosamente.',
                'data'    => $createdPayments
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Limpieza de emergencia: Borramos los archivos físicos si algo falló en la BD
            foreach ($uploadedPaths as $path) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                }
            }

            return response()->json([
                'message' => 'Error al registrar los pagos.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function verify(Request $request, $id)
    {
        $request->validate([
            'is_approved' => 'required|boolean'
        ]);

        $payment = Payment::with(['order.user', 'order.products', 'order.payments', 'currency', 'paymentMethod'])->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        $order = $payment->order;

        DB::beginTransaction();

        try {
            if ($request->is_approved) {
                // 1. APROBAR ESTE PAGO
                $payment->update([
                    'status'      => 'verified', 
                    'verified_at' => now()       
                ]);

                // 2. CÁLCULO DEL TOTAL DE LA ORDEN
                $subtotalUsd = $order->products->sum(function ($product) {
                    $base = $product->pivot->quantity * $product->pivot->price;
                    $percent = $product->pivot->discount ?? 0;
                    return $base * (1 - ($percent / 100));
                });
                
                $totalOrderUsd = round($subtotalUsd + (float)$order->igtf_amount, 2);
                $orderRate = (float) $order->exchange_rate;

                // 3. CÁLCULO DE PAGOS VERIFICADOS (A prueba de decimales)
                $order->load('payments.currency'); 
                $verifiedPayments = $order->payments->where('status', 'verified');
                
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

                // 4. MÁQUINA DE ESTADOS (Manzanas con Manzanas)
                $isFullyPaid = false;

                if ($paymentCurrency === 'VES') {
                    $totalOrderBs = round($totalOrderUsd * $orderRate, 2);
                    // Redondeamos a 2 decimales ambos lados antes de comparar
                    if (round($totalPaid, 2) >= $totalOrderBs) {
                        $isFullyPaid = true;
                    }
                } else {
                    if (round($totalPaid, 2) >= $totalOrderUsd) {
                        $isFullyPaid = true;
                    }
                }

                // Ejecutamos la acción según el resultado exacto
                if ($isFullyPaid) {
                    $order->update(['status' => 'completed']);
                    
                    // Recuerda importar el Job si no lo tienes arriba: use App\Jobs\GenerateInvoiceJob;
                    GenerateInvoiceJob::dispatch($order);

                    $message = 'Pago verificado. Total alcanzado. La orden ha sido completada y el comprobante se está generando.';
                } else {
                    $hasPending = $order->payments->where('status', 'pending')->count() > 0;

                    if ($hasPending) {
                        $order->update(['status' => 'verifying_payment']); 
                        $message = 'Pago verificado parcialmente. Aún resta saldo y hay otros pagos en cola por verificar.';
                    } else {
                        $order->update(['status' => 'pending_payment']); 
                        $message = 'Pago verificado. Falta saldo por pagar y no hay más pagos reportados. La orden pasó a pendiente.';
                    }
                }

            } else {
                // RECHAZAR PAGO
                $payment->update([
                    'status'      => 'rejected',
                    'verified_at' => null 
                ]);

                // --- EL CAMBIO ESTÁ AQUÍ ---
                // Hacemos una consulta fresca y real a la base de datos.
                $hasPending = $order->payments()->where('status', 'pending')->exists();
                $hasVerified = $order->payments()->where('status', 'verified')->exists();

                if ($hasPending) {
                    $order->update(['status' => 'verifying_payment']);
                    $message = 'Pago rechazado. Aún hay otros pagos de esta orden esperando verificación.';
                } else if ($hasVerified) {
                    // Si no hay pagos pendientes, pero SÍ hay pagos verificados, 
                    // significa que el cliente tiene que pagar el saldo restante.
                    $order->update(['status' => 'pending_payment']); 
                    $message = 'Pago rechazado. La orden se mantiene con saldo pendiente por otros pagos verificados.';
                } else {
                    // LIENZO EN BLANCO: No quedan pendientes ni verificados.
                    $order->update([
                        'status'      => 'pending_payment',
                        'igtf_amount' => 0
                    ]);
                    $message = 'Pago rechazado. No quedan pagos activos, la orden vuelve a estar pendiente y el IGTF fue reiniciado a 0.';
                }
            }

            DB::commit();

            // ENVIAR EL CORREO AL USUARIO
            if ($order->user) {
                Mail::to($order->user->email)->send(new PaymentVerificationMail($payment));
            }

            return response()->json([
                'message'        => $message,
                'payment_status' => $payment->status,
                'order_status'   => $order->status
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al procesar la verificación.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
