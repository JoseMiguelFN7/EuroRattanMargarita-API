<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class OrderController extends Controller
{
    protected $inventoryService;

    // --- NUEVO: Inyectamos el servicio en el constructor ---
    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

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
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        // 5. Paginación
        $perPage = $request->input('per_page', 10);
        
        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        // 6. Transformación (Limpieza y Cálculo)
        $orders->through(function ($order) {
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
        $userId = auth('sanctum')->id();
        
        $query = Order::with(['products', 'invoice', 'payments.currency'])->where('user_id', $userId);

        if ($request->filled('search')) {
            $query->where('code', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        $orders = $query->latest() 
            ->paginate($request->input('per_page', 10)) 
            ->through(function ($order) {
                
                // 1. Cálculo del total de la orden en USD
                $subtotalCalculated = $order->products->sum(function ($product) {
                    $base = $product->pivot->quantity * $product->pivot->price;
                    $percent = $product->pivot->discount ?? 0;
                    return $base * (1 - ($percent / 100));
                });
                
                $totalOrderUsd = round($subtotalCalculated + (float) $order->igtf_amount, 2);

                // --- 2. NUEVAS REGLAS DE NEGOCIO (Estados finales) ---
                if (in_array($order->status, ['completed', 'cancelled'])) {
                    $missingAmount = null;
                    $missingCurrency = null;
                } else {
                    // Filtramos solo los pagos activos
                    $activePayments = $order->payments->whereIn('status', ['pending', 'verified']);
                    
                    $paymentCurrency = 'USD'; // Por defecto
                    $totalPaid = 0; // Sumatoria bruta sin importar la moneda
                    $orderRate = (float) $order->exchange_rate;

                    if ($activePayments->isNotEmpty()) {
                        $firstPayment = $activePayments->first();
                        if ($firstPayment->currency) {
                            $paymentCurrency = strtoupper($firstPayment->currency->code);
                        }

                        // Sumamos el monto BRUTO tal cual lo introdujo el cliente
                        $totalPaid = $activePayments->sum(function($p) {
                            return (float) $p->amount;
                        });
                    }

                    // Calculamos la diferencia dependiendo matemáticamente de la moneda
                    if ($paymentCurrency === 'VES') {
                        // --- MATEMÁTICA DIRECTA EN BOLÍVARES ---
                        $totalOrderBs = round($totalOrderUsd * $orderRate, 2);
                        $remainingBs = $totalOrderBs - $totalPaid;
                        
                        $missingAmount = $remainingBs <= 0 ? null : round($remainingBs, 2);
                        $missingCurrency = $missingAmount ? 'VES' : null;
                        
                    } else {
                        // --- MATEMÁTICA DIRECTA EN DÓLARES ---
                        $remainingUsd = $totalOrderUsd - $totalPaid;
                        
                        $missingAmount = $remainingUsd <= 0 ? null : round($remainingUsd, 2);
                        $missingCurrency = $missingAmount ? 'USD' : null;
                    }
                }

                $invoiceLink = null;
                if ($order->invoice && $order->invoice->pdf_url) {
                    $invoiceLink = asset('storage/' . $order->invoice->pdf_url);
                }

                return [
                    'id'            => $order->id,
                    'code'          => $order->code,
                    'status'        => $order->status,
                    'created_at'    => $order->created_at?->format('Y-m-d H:i:s'),
                    'exchange_rate' => (float) $order->exchange_rate,
                    'notes'         => $order->notes,
                    'subtotal_usd'  => round($subtotalCalculated, 2),
                    'igtf_amount'   => (float) $order->igtf_amount,
                    'total_usd'     => $totalOrderUsd,
                    
                    // Aquí mandamos null o los montos exactos
                    'missing_amount'   => $missingAmount,
                    'missing_currency' => $missingCurrency,

                    'invoice_download_link' => $invoiceLink,
                ];
            });

        return response()->json($orders);
    }

    public function show($id)
    {
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            'payments' => function ($query) {
                $query->latest(); 
            },
            'payments.paymentMethod' 
        ])->find($id);

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
            'subtotal_usd'  => round($subtotalCalculated, 2),
            'igtf_amount'   => (float) $order->igtf_amount,
            'total_usd'     => round($subtotalCalculated + $order->igtf_amount, 2),
            'user' => [
                'name'  => $order->user->name,
                'email' => $order->user->email,
                'cellphone' => $order->user->cellphone ?? 'N/A',
            ],
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

    public function showByCode($code)
    {
        // 1. Añadimos 'payments.currency' para poder saber la moneda original de cada pago
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            'payments' => function ($query) {
                $query->latest(); 
            },
            'payments.currency',
            'payments.paymentMethod' 
        ])->where('code', $code)->first();

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
            'subtotal_usd'  => round($subtotalCalculated, 2),
            'igtf_amount'   => (float) $order->igtf_amount,
            'total_usd'     => round($subtotalCalculated + $order->igtf_amount, 2),
            'user' => [
                'name'      => $order->user->name,
                'email'     => $order->user->email,
                'cellphone' => $order->user->cellphone ?? 'N/A',
            ],
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
            'payments' => $order->payments->map(function ($payment) {
                
                // Mapeo inteligente multimoneda
                $amount = (float) $payment->amount;
                $rate = (float) $payment->exchange_rate;
                
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
                    'id'               => $payment->id,
                    'status'           => $payment->status,
                    'reference_number' => $payment->reference_number,
                    'proof_image'      => $payment->proof_image ? asset('storage/' . $payment->proof_image) : null,
                    'method'           => $payment->paymentMethod ? [
                        'name'  => $payment->paymentMethod->name,
                        'image' => $payment->paymentMethod->image ? asset('storage/' . $payment->paymentMethod->image) : null,
                    ] : null,
                    
                    // Datos claros para la interfaz
                    'original_amount'   => $amount,
                    'original_currency' => $currencyCode,
                    'amount_usd'        => round($montoUsd, 2),
                    'total_ves'         => round($montoBs, 2),
                    'rate'              => $rate,
                    'created_at'        => $payment->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ];

        return response()->json($cleanOrder);
    }

    public function showMyOrderByCode($code)
    {
        // 1. Añadimos 'payments.currency' a la carga ansiosa
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            'payments' => function ($query) {
                $query->latest(); 
            },
            'payments.currency',
            'payments.paymentMethod' 
        ])->where('code', $code)->first();

        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        if ($order->user_id !== auth('sanctum')->id()) {
            return response()->json([
                'message' => 'Acceso denegado. Esta orden no pertenece a tu cuenta.'
            ], 403);
        }

        // 2. Cálculo del subtotal
        $subtotalCalculated = $order->products->sum(function ($product) {
            $base = $product->pivot->quantity * $product->pivot->price;
            $percent = $product->pivot->discount ?? 0;
            return $base * (1 - ($percent / 100));
        });

        // 3. Respuesta Final Limpia
        $cleanOrder = [
            'id'            => $order->id,
            'code'          => $order->code,
            'status'        => $order->status,
            'created_at'    => $order->created_at->toDateTimeString(),
            'exchange_rate' => $order->exchange_rate,
            'notes'         => $order->notes,
            'subtotal_usd'  => round($subtotalCalculated, 2),
            'igtf_amount'   => (float) $order->igtf_amount,
            'total_usd'     => round($subtotalCalculated + (float) $order->igtf_amount, 2),
            
            'user' => [
                'name'      => $order->user->name,
                'email'     => $order->user->email,
                'cellphone' => $order->user->cellphone ?? 'N/A',
            ],
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
            'payments' => $order->payments->map(function ($payment) {
                
                // Mapeo inteligente multimoneda
                $amount = (float) $payment->amount;
                $rate = (float) $payment->exchange_rate;
                
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
                    'id'               => $payment->id,
                    'status'           => $payment->status,
                    'reference_number' => $payment->reference_number,
                    'proof_image'      => $payment->proof_image ? asset('storage/' . $payment->proof_image) : null,
                    'method'           => $payment->paymentMethod ? [
                        'name'  => $payment->paymentMethod->name,
                        'image' => $payment->paymentMethod->image ? asset('storage/' . $payment->paymentMethod->image) : null,
                    ] : null,
                    
                    // Datos claros para la interfaz del cliente
                    'original_amount'   => $amount,
                    'original_currency' => $currencyCode,
                    'amount_usd'        => round($montoUsd, 2),
                    'total_ves'         => round($montoBs, 2),
                    'rate'              => $rate,
                    'created_at'        => $payment->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ];

        return response()->json($cleanOrder);
    }

    //Crear Orden
    public function store(Request $request)
    {
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

            // --- NUEVA VALIDACIÓN PARA MÚLTIPLES PAGOS ---
            'payments'                      => 'nullable|array',
            'payments.*.amount'             => 'required_with:payments|numeric|min:0.01',
            'payments.*.currency_id'        => 'required_with:payments|exists:currencies,id',
            'payment_method_ids'            => 'required_with:payments|array', // Validar IGTF
            'payments.*.payment_method_id'  => 'required_with:payments|exists:payment_methods,id',
            'payments.*.reference_number'   => 'nullable|string|max:50',
            'payments.*.proof_image'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        DB::beginTransaction();
        $uploadedPaths = []; // Guardamos rutas por si hay rollback

        try {
            $subtotal = 0;
            foreach ($request->products as $item) {
                $lineTotal = $item['quantity'] * $item['price'];
                $discountPercent = $item['discount'] ?? 0;
                $subtotal += $lineTotal * (1 - ($discountPercent / 100));
            }

            // --- CÁLCULO DE IGTF (Leyendo el primer método de pago si existe) ---
            $igtfAmount = 0;
            if ($request->has('payments') && count($request->payments) > 0) {
                // Como todos aplican o no, basta con chequear el primero
                $firstPaymentMethodId = $request->payments[0]['payment_method_id'];
                $paymentMethod = PaymentMethod::find($firstPaymentMethodId);
                
                if ($paymentMethod && $paymentMethod->applies_igtf) {
                    $igtfAmount = round($subtotal * 0.03, 2);
                }
            }

            $initialStatus = $request->has('payments') ? 'verifying_payment' : 'pending_payment';

            $order = Order::create([
                'user_id'       => $request->user_id,
                'exchange_rate' => $request->exchange_rate,
                'notes'         => $request->notes,
                'status'        => $initialStatus,
                'igtf_amount'   => $igtfAmount,
            ]);

            $now = $order->created_at;
            $productIds = collect($request->products)->pluck('id');
            $productsData = Product::with('set.furnitures')->whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($request->products as $item) {
                $variantId = $item['variant_id'] ?? null;
                $productModel = $productsData[$item['id']];

                $order->products()->attach($item['id'], [
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'discount'   => $item['discount'] ?? 0,
                    'variant_id' => $variantId,
                ]);

                if ($productModel->set && $productModel->set->furnitures) {
                    foreach ($productModel->set->furnitures as $furniture) {
                        $qtyPerSet = $furniture->pivot->quantity;
                        $totalToDeduct = -abs($item['quantity'] * $qtyPerSet);

                        $this->inventoryService->recordMovement(
                            $furniture->product_id,
                            $totalToDeduct,
                            $variantId,
                            $now,
                            $order
                        );
                    }
                } else {
                    $this->inventoryService->recordMovement(
                        $item['id'],
                        -abs($item['quantity']),
                        $variantId,
                        $now,
                        $order
                    );
                }
            }

            // --- PROCESAR EL ARREGLO DE PAGOS ---
            if ($request->has('payments')) {
                // Notar que en Laravel, cuando mandas archivos en un arreglo de objetos desde FormData,
                // la estructura de request->file() puede variar. Asumimos que lo envías correctamente.
                foreach ($request->payments as $index => $paymentData) {
                    $path = null;
                    
                    // Manejo del archivo comprobante
                    if ($request->hasFile("payments.{$index}.proof_image")) {
                        $file = $request->file("payments.{$index}.proof_image");
                        $path = $file->store('payments', 'public');
                        $uploadedPaths[] = $path; // Guardamos para borrar en caso de error
                    }

                    Payment::create([
                        'order_id'          => $order->id,
                        'amount'            => $paymentData['amount'],
                        'currency_id'       => $paymentData['currency_id'],
                        'payment_method_id' => $paymentData['payment_method_id'],
                        'reference_number'  => $paymentData['reference_number'] ?? null,
                        'proof_image'       => $path,
                        'status'            => 'pending',
                        'exchange_rate'     => $request->exchange_rate // Usamos la misma tasa de la orden
                    ]);
                }
            }

            DB::commit();

            return response()->json($order->load(['products', 'payments']), 201);

        } catch (\Exception $e) {
            DB::rollback();
            // Borrar todas las imágenes que se subieron antes de que fallara
            foreach ($uploadedPaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            return response()->json([
                'message' => 'Error al crear la orden.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function cancel($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada.'], 404);
        }

        if ($order->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Solo se pueden cancelar órdenes que estén pendientes de pago.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $order->update(['status' => 'cancelled']);

            // --- NUEVO: Reversión segura usando el servicio ---
            $this->inventoryService->reverseMovements($order);

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
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['error' => 'Orden no encontrada'], 404);
        }

        DB::beginTransaction();

        try {
            // --- NUEVO: Reversión segura de stock usando el servicio centralizado ---
            // Esto elimina la necesidad de inyectar ProductMovementController y falsear el Kardex
            $this->inventoryService->reverseMovements($order);

            // Eliminar relaciones en la tabla intermedia
            $order->products()->detach();

            // Eliminar la orden
            $order->delete();

            DB::commit();

            return response()->json(['message' => 'Orden eliminada correctamente'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar la orden.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Obtiene la información necesaria para que el cliente reporte nuevos pagos
     * de una orden que quedó pendiente o con pagos rechazados.
     */
    public function getPaymentDetails($code)
    {
        // 1. Buscamos la orden por código con sus relaciones
        $order = Order::with(['products', 'payments.currency', 'payments.paymentMethod'])
            ->where('code', $code)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }

        // 2. Verificamos el status
        if ($order->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Esta orden no está en estado pendiente de pago.',
                'current_status' => $order->status
            ], 400);
        }

        // 3. Cálculos de montos
        $subtotalUsd = $order->products->sum(function ($product) {
            $base = $product->pivot->quantity * $product->pivot->price;
            $percent = $product->pivot->discount ?? 0;
            return $base * (1 - ($percent / 100));
        });
        
        $totalOrderUsd = round($subtotalUsd + (float) $order->igtf_amount, 2);
        $orderRate = (float) $order->exchange_rate;

        // Filtramos pagos verificados (los únicos que "congelan" la moneda)
        $verifiedPayments = $order->payments->where('status', 'verified');
        
        // Calculamos total pagado en USD
        $totalPaidUsd = $verifiedPayments->sum(function($p) {
            $amt = (float) $p->amount;
            $rate = (float) $p->exchange_rate;
            if ($p->currency && strtoupper($p->currency->code) === 'VES') {
                return $rate > 0 ? ($amt / $rate) : 0;
            }
            return $amt;
        });

        $remainingUsd = round($totalOrderUsd - $totalPaidUsd, 2);

        // 4. Lógica de Moneda Restante y Métodos de Pago
        $missingAmount = [];
        $allowedPaymentMethods = [];
        
        // Obtenemos todos los métodos de pago activos
        $allMethods = PaymentMethod::where('is_active', true)->with('currency')->get();

        if ($verifiedPayments->isNotEmpty()) {
            // Si hay pagos aprobados, detectamos su moneda (tomamos el primero)
            $firstVerified = $verifiedPayments->first();
            $currencyCode = strtoupper($firstVerified->currency->code);
            $appliesIgtf = $firstVerified->paymentMethod->applies_igtf;

            if ($currencyCode === 'VES') {
                $totalOrderBs = round($totalOrderUsd * $orderRate, 2);
                $totalPaidBs = $verifiedPayments->sum(fn($p) => (float) $p->amount);
                
                $missingAmount = [
                    'amount' => round($totalOrderBs - $totalPaidBs, 2),
                    'currency' => 'VES'
                ];
                
                // Solo permitimos métodos en Bs que NO apliquen IGTF
                $allowedPaymentMethods = $allMethods->where('applies_igtf', false)
                    ->filter(fn($m) => strtoupper($m->currency->code) === 'VES');
            } else {
                $missingAmount = [
                    'amount' => $remainingUsd,
                    'currency' => 'USD'
                ];
                // Solo permitimos métodos en $ o que apliquen IGTF
                $allowedPaymentMethods = $allMethods->filter(function($m) use ($appliesIgtf) {
                    return $m->applies_igtf === $appliesIgtf || strtoupper($m->currency->code) === 'USD';
                });
            }
        } else {
            // CASO: No hay pagos aprobados (todos rechazados o nueva)
            // Enviamos opciones en ambas monedas
            $missingAmount = [
                'usd' => $remainingUsd,
                'ves' => round($remainingUsd * $orderRate, 2)
            ];
            $allowedPaymentMethods = $allMethods;
        }

        // 5. Respuesta final
        return response()->json([
            'order_id'        => $order->id,
            'order_code'      => $order->code,
            'total_order_usd' => $totalOrderUsd,
            'exchange_rate'   => $orderRate,
            'missing_amount'  => $missingAmount,
            'payment_methods' => $allowedPaymentMethods->values()->map(function($m) {
                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'currency' => $m->currency->code,
                    'currency_id' => $m->currency->id,
                    'applies_igtf' => $m->applies_igtf,
                    'requires_proof' => $m->requires_proof,
                    'bank_details' => $m->bank_details,
                    'image' => $m->image ? asset('storage/' . $m->image) : null
                ];
            })
        ]);
    }
}