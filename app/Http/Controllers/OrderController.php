<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductMovement;
use App\Models\Product;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use App\Http\Controllers\ProductMovementController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class OrderController extends Controller
{
    public function index(Request $request)
    {
        // 1. Preparamos la consulta
        $query = Order::with(['user:id,name,email', 'products']);

        // 2. Filtro de Estado Exacto
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 3. Filtro Abierto (Buscador por Código, Nombre o Correo)
        if ($request->has('search')) {
            $searchTerm = $request->search;
            
            $query->where(function ($q) use ($searchTerm) {
                $q->where('code', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('email', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // 4. Filtro por Rango de Fechas
        // Usamos filled() en lugar de has() para asegurar que no venga vacío ("")
        if ($request->filled('start_date')) {
            // Aseguramos que tome desde las 00:00:00 del día
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            // Aseguramos que tome hasta las 23:59:59 del día
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        // 5. Paginación
        $perPage = $request->input('per_page', 10);
        
        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        // 6. Transformación (Limpieza y Cálculo)
        $orders->through(function ($order) {
            
            // CÁLCULO DEL TOTAL AL VUELO
            $subtotalCalculated = $order->products->sum(function ($product) {
                $base = $product->pivot->quantity * $product->pivot->price;
                $percent = $product->pivot->discount ?? 0;
                
                return $base * (1 - ($percent / 100));
            });

            return [
                'id'            => $order->id,
                'code'          => $order->code,
                'status'        => $order->status,
                'created_at'    => $order->created_at->toDateTimeString(),
                'exchange_rate' => (float) $order->exchange_rate,
                'notes'         => $order->notes,
                
                // Nuevos campos
                'subtotal_usd'  => round($subtotalCalculated, 2),
                'igtf_amount'   => (float) $order->igtf_amount,
                'total_usd'     => round($subtotalCalculated + $order->igtf_amount, 2),

                'user' => $order->user,
            ];
        });

        return response()->json($orders);
    }

    public function myOrders(Request $request)
    {
        // Obtenemos el ID del usuario directamente desde el guard de Sanctum
        $userId = auth('sanctum')->id();

        $orders = Order::with(['products'])
            ->where('user_id', $userId)
            ->latest() 
            ->paginate($request->input('per_page', 10)) 
            ->through(function ($order) {
                
                // Calculamos el subtotal en USD
                $subtotalCalculated = $order->products->sum(function ($product) {
                    $base = $product->pivot->quantity * $product->pivot->price;
                    $percent = $product->pivot->discount ?? 0;
                    
                    return $base * (1 - ($percent / 100));
                });

                return [
                    'id'            => $order->id,
                    'code'          => $order->code,
                    'status'        => $order->status,
                    'created_at'    => $order->created_at?->format('Y-m-d H:i:s'),
                    'exchange_rate' => $order->exchange_rate,
                    'notes'         => $order->notes,
                    
                    'subtotal_usd'  => round($subtotalCalculated, 2),
                    'igtf_amount'   => (float) $order->igtf_amount,
                    'total_usd'     => round($subtotalCalculated + $order->igtf_amount, 2),
                ];
            });

        return response()->json($orders);
    }

    public function show($id)
    {
        // 1. Buscamos la orden con todas sus relaciones necesarias
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            // Aplicamos un closure para ordenar los pagos desde la base de datos
            'payments' => function ($query) {
                $query->latest(); // Ordena por created_at de forma descendente
            },
            'payments.paymentMethod' 
        ])->find($id);

        // 2. Validación por si no existe
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        $subtotalCalculated = $order->products->sum(function ($product) {
            $base = $product->pivot->quantity * $product->pivot->price;
            $percent = $product->pivot->discount ?? 0;
            
            return $base * (1 - ($percent / 100));
        });

        $cleanOrder = [
            'id'            => $order->id,
            'code'          => $order->code,
            'status'        => $order->status,
            'created_at'    => $order->created_at->toDateTimeString(),
            'exchange_rate' => $order->exchange_rate,
            'notes'         => $order->notes,

            // Métricas financieras
            'subtotal_usd'  => round($subtotalCalculated, 2),
            'igtf_amount'   => (float) $order->igtf_amount,
            'total_usd'     => round($subtotalCalculated + $order->igtf_amount, 2),

            // Datos del Cliente
            'user' => [
                'name'  => $order->user->name,
                'email' => $order->user->email,
                'cellphone' => $order->user->cellphone ?? 'N/A',
            ],

            // Lista de Productos (Aplanando la tabla pivote)
            'products' => $order->products->map(function ($product) {
                return [
                    'id'         => $product->id,
                    'name'       => $product->name,
                    'quantity'   => $product->pivot->quantity,
                    'price'      => $product->pivot->price,
                    'discount'   => $product->pivot->discount,
                    'variant_id' => $product->pivot->variant_id,
                    'subtotal'   => round($product->pivot->quantity * $product->pivot->price, 2),
                ];
            }),

            // Datos del Pago
            'payments' => $order->payments->map(function ($payment) {
                return [
                    'id'               => $payment->id,
                    'status'           => $payment->status,
                    'amount'           => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    
                    // Generamos la URL completa para que el frontend la lea directo
                    'proof_image'      => $payment->proof_image ? asset('storage/' . $payment->proof_image) : null,
                    
                    // Datos del Método de Pago (Ej: Zelle, Banesco)
                    'method' => $payment->paymentMethod ? [
                        'name'  => $payment->paymentMethod->name,
                        'image' => $payment->paymentMethod->image ? asset('storage/' . $payment->paymentMethod->image) : null,
                    ] : null,
                ];
            }),
        ];

        return response()->json($cleanOrder);
    }

    public function showByCode($code)
    {
        // 1. Buscamos la orden con todas sus relaciones necesarias por su código
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            'payments' => function ($query) {
                $query->latest(); 
            },
            'payments.paymentMethod' 
        ])->where('code', $code)->first();

        // 2. Validación por si no existe
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        // 3. Cálculos
        $subtotalCalculated = $order->products->sum(function ($product) {
            $base = $product->pivot->quantity * $product->pivot->price;
            $percent = $product->pivot->discount ?? 0;
            
            return $base * (1 - ($percent / 100));
        });

        // 4. Formateo y limpieza de la respuesta
        $cleanOrder = [
            'id'            => $order->id,
            'code'          => $order->code,
            'status'        => $order->status,
            'created_at'    => $order->created_at->toDateTimeString(),
            'exchange_rate' => $order->exchange_rate,
            'notes'         => $order->notes,

            // Métricas financieras
            'subtotal_usd'  => round($subtotalCalculated, 2),
            'igtf_amount'   => (float) $order->igtf_amount,
            'total_usd'     => round($subtotalCalculated + $order->igtf_amount, 2),

            // Datos del Cliente
            'user' => [
                'name'      => $order->user->name,
                'email'     => $order->user->email,
                'cellphone' => $order->user->cellphone ?? 'N/A',
            ],

            // Lista de Productos (Aplanando la tabla pivote)
            'products' => $order->products->map(function ($product) {
                return [
                    'id'         => $product->id,
                    'name'       => $product->name,
                    'quantity'   => $product->pivot->quantity,
                    'price'      => $product->pivot->price,
                    'discount'   => $product->pivot->discount,
                    'variant_id' => $product->pivot->variant_id,
                    'subtotal'   => round($product->pivot->quantity * $product->pivot->price, 2),
                ];
            }),

            // Datos del Pago
            'payments' => $order->payments->map(function ($payment) {
                return [
                    'id'               => $payment->id,
                    'status'           => $payment->status,
                    'amount'           => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    
                    // Generamos la URL completa para que el frontend la lea directo
                    'proof_image'      => $payment->proof_image ? asset('storage/' . $payment->proof_image) : null,
                    
                    // Datos del Método de Pago (Ej: Zelle, Banesco)
                    'method' => $payment->paymentMethod ? [
                        'name'  => $payment->paymentMethod->name,
                        'image' => $payment->paymentMethod->image ? asset('storage/' . $payment->paymentMethod->image) : null,
                    ] : null,
                ];
            }),
        ];

        return response()->json($cleanOrder);
    }

    public function showMyOrderByCode($code)
    {
        // 1. Buscamos la orden
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            'payments' => function ($query) {
                $query->latest(); 
            },
            'payments.paymentMethod' 
        ])->where('code', $code)->first();

        // 2. Validación por si no existe
        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        // 3. VERIFICACIÓN DE SEGURIDAD
        // Comparamos el ID del dueño de la orden con el ID del usuario logueado en el token
        if ($order->user_id !== auth('sanctum')->id()) {
            return response()->json([
                'message' => 'Acceso denegado. Esta orden no pertenece a tu cuenta.'
            ], 403);
        }

        // 4. Cálculos
        $subtotalCalculated = $order->products->sum(function ($product) {
            $base = $product->pivot->quantity * $product->pivot->price;
            $percent = $product->pivot->discount ?? 0;
            
            return $base * (1 - ($percent / 100));
        });

        // 5. Formateo y limpieza de la respuesta
        $cleanOrder = [
            'id'            => $order->id,
            'code'          => $order->code,
            'status'        => $order->status,
            'created_at'    => $order->created_at->toDateTimeString(),
            'exchange_rate' => $order->exchange_rate,
            'notes'         => $order->notes,

            // Métricas financieras
            'subtotal_usd'  => round($subtotalCalculated, 2),
            'igtf_amount'   => (float) $order->igtf_amount,
            'total_usd'     => round($subtotalCalculated + $order->igtf_amount, 2),

            // Datos del Cliente
            'user' => [
                'name'      => $order->user->name,
                'email'     => $order->user->email,
                'cellphone' => $order->user->cellphone ?? 'N/A',
            ],

            // Lista de Productos (Aplanando la tabla pivote)
            'products' => $order->products->map(function ($product) {
                return [
                    'id'         => $product->id,
                    'name'       => $product->name,
                    'quantity'   => $product->pivot->quantity,
                    'price'      => $product->pivot->price,
                    'discount'   => $product->pivot->discount,
                    'variant_id' => $product->pivot->variant_id,
                    'subtotal'   => round($product->pivot->quantity * $product->pivot->price, 2),
                ];
            }),

            // Datos del Pago
            'payments' => $order->payments->map(function ($payment) {
                return [
                    'id'               => $payment->id,
                    'status'           => $payment->status,
                    'amount'           => $payment->amount,
                    'reference_number' => $payment->reference_number,
                    
                    'proof_image'      => $payment->proof_image ? asset('storage/' . $payment->proof_image) : null,
                    
                    'method' => $payment->paymentMethod ? [
                        'name'  => $payment->paymentMethod->name,
                        'image' => $payment->paymentMethod->image ? asset('storage/' . $payment->paymentMethod->image) : null,
                    ] : null,
                ];
            }),
        ];

        return response()->json($cleanOrder);
    }

    //Crear Orden
    public function store(Request $request)
    {
        // 1. VALIDACIÓN (Se mantiene igual)
        $validator = Validator::make($request->all(), [
            'user_id'       => 'required|integer|exists:users,id',
            'exchange_rate' => 'required|numeric|min:0',
            'notes'         => 'nullable|string|max:1000',
            
            'products'              => 'required|array',
            'products.*.id'         => 'required|integer|exists:products,id',
            'products.*.variant_id' => 'nullable|integer|exists:colors,id',
            'products.*.quantity'   => 'required|numeric|min:0.01',
            'products.*.price'      => 'required|numeric|min:0',
            'products.*.discount'   => 'nullable|numeric|min:0|max:100',

            'payment_amount'      => 'nullable|numeric|min:0.01',
            'payment_currency_id' => 'required_with:payment_amount|exists:currencies,id',
            'payment_method_id'   => 'required_with:payment_amount|exists:payment_methods,id',
            'payment_reference'   => 'nullable|string|max:50',
            'payment_proof'       => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        DB::beginTransaction();
        $uploadedPath = null;

        try {
            // 2. CÁLCULO DEL SUBTOTAL E IGTF ANTES DE CREAR LA ORDEN
            $subtotal = 0;
            foreach ($request->products as $item) {
                $lineTotal = $item['quantity'] * $item['price'];
                $discountPercent = $item['discount'] ?? 0;
                
                // Restamos el porcentaje al total de la línea
                $subtotal += $lineTotal * (1 - ($discountPercent / 100));
            }

            $igtfAmount = 0;
            if ($request->has('payment_method_id')) {
                $paymentMethod = PaymentMethod::find($request->payment_method_id);
                if ($paymentMethod && $paymentMethod->applies_igtf) {
                    // El IGTF se calcula sobre el precio neto (ya con descuento aplicado)
                    $igtfAmount = round($subtotal * 0.03, 2);
                }
            }

            // 3. CREAR LA ORDEN (Ahora guardamos el igtf_amount)
            $initialStatus = $request->has('payment_amount') ? 'verifying_payment' : 'pending_payment';

            $order = Order::create([
                'user_id'       => $request->user_id,
                'exchange_rate' => $request->exchange_rate,
                'notes'         => $request->notes,
                'status'        => $initialStatus,
                'igtf_amount'   => $igtfAmount, // <-- NUEVO
            ]);

            $now = $order->created_at;

            $productIds = collect($request->products)->pluck('id');
            $productsData = Product::with('set.furnitures')->whereIn('id', $productIds)->get()->keyBy('id');

            // 4. RECORRER PRODUCTOS (Pivot + Movimientos)
            foreach ($request->products as $item) {
                $variantId = $item['variant_id'] ?? null;
                $productModel = $productsData[$item['id']];

                $order->products()->attach($item['id'], [
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'discount'   => $item['discount'] ?? 0,
                    'variant_id' => $variantId,
                ]);

                // Movimientos de inventario (Se mantiene exactamente igual...)
                if ($productModel->set && $productModel->set->furnitures) {
                    foreach ($productModel->set->furnitures as $furniture) {
                        $qtyPerSet = $furniture->pivot->quantity;
                        $totalToDeduct = -abs($item['quantity'] * $qtyPerSet);

                        ProductMovement::create([
                            'product_id'        => $furniture->product_id,
                            'color_id'          => $variantId,
                            'quantity'          => $totalToDeduct,
                            'movement_date'     => $now,
                            'movementable_id'   => $order->id,
                            'movementable_type' => Order::class,
                        ]);
                    }
                } else {
                    ProductMovement::create([
                        'product_id'        => $item['id'],
                        'color_id'          => $variantId,
                        'quantity'          => -abs($item['quantity']),
                        'movement_date'     => $now,
                        'movementable_id'   => $order->id,
                        'movementable_type' => Order::class,
                    ]);
                }
            }

            // 5. PROCESAR PAGO
            if ($request->has('payment_amount')) {
                if ($request->hasFile('payment_proof')) {
                    $uploadedPath = $request->file('payment_proof')->store('payments', 'public');
                }

                Payment::create([
                    'order_id'          => $order->id,
                    'amount'            => $request->payment_amount,
                    'currency_id'       => $request->payment_currency_id,
                    'payment_method_id' => $request->payment_method_id,
                    'reference_number'  => $request->payment_reference,
                    'proof_image'       => $uploadedPath,
                    'status'            => 'pending',
                    'exchange_rate'     => $request->exchange_rate
                ]);
            }

            DB::commit();

            return response()->json($order->load(['products', 'payments']), 201);

        } catch (\Exception $e) {
            DB::rollback();
            if ($uploadedPath && Storage::disk('public')->exists($uploadedPath)) {
                Storage::disk('public')->delete($uploadedPath);
            }
            return response()->json([
                'message' => 'Error al crear la orden.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function cancel($id)
    {
        // 1. Buscar la orden por ID sin importar a qué usuario le pertenezca
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        // 2. Validar que el status sea estrictamente 'pending_payment'
        if ($order->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Solo se pueden cancelar órdenes que estén pendientes de pago.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // 3. Cambiar el status de la orden a cancelled
            $order->update(['status' => 'cancelled']);

            // 4. Eliminar los movimientos de inventario asociados para restaurar el stock
            // Usamos la relación polimórfica exacta con la que se crearon en el store
            ProductMovement::where('movementable_type', Order::class)
                ->where('movementable_id', $order->id)
                ->delete();

            DB::commit();

            return response()->json([
                'message' => 'Orden cancelada y movimientos de inventario revertidos exitosamente.',
                'data' => [
                    'id' => $order->id,
                    'code' => $order->code,
                    'status' => $order->status
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Ocurrió un error al intentar cancelar la orden.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    //eliminar orden
    public function destroy($id)
    {
        // Buscar la orden
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['error' => 'Orden no encontrada'], 404);
        }

        // Iniciar una transacción para asegurar consistencia
        DB::beginTransaction();

        try {
            // Instancia del controlador de movimientos
            $movementController = new ProductMovementController();

            // Obtener productos asociados a la orden con su información adicional
            $products = $order->products()->withPivot('quantity', 'color_id')->get();

            foreach ($products as $product) {
                // Crear movimiento opuesto (cantidad positiva)
                $movementController->createProductMovement(
                    $product->id,
                    abs($product->pivot->quantity), // Cantidad en positivo
                    now(),
                    $product->pivot->color_id
                );
            }

            // Eliminar relaciones en la tabla intermedia
            $order->products()->detach();

            // Eliminar la orden
            $order->delete();

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Orden eliminada correctamente'], 200);
        } catch (\Exception $e) {
            // Revertir la transacción si ocurre un error
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar la orden.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}