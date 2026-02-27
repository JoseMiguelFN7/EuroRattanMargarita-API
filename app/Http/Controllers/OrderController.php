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
        $query = Order::with(['products', 'invoice'])->where('user_id', $userId);

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
                $subtotalCalculated = $order->products->sum(function ($product) {
                    $base = $product->pivot->quantity * $product->pivot->price;
                    $percent = $product->pivot->discount ?? 0;
                    return $base * (1 - ($percent / 100));
                });

                $invoiceLink = null;
                if ($order->invoice && $order->invoice->pdf_url) {
                    $invoiceLink = asset('storage/' . $order->invoice->pdf_url);
                }

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
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            'payments' => function ($query) {
                $query->latest(); 
            },
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

    public function showMyOrderByCode($code)
    {
        $order = Order::with([
            'user:id,name,email,cellphone',
            'products',
            'payments' => function ($query) {
                $query->latest(); 
            },
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
            $subtotal = 0;
            foreach ($request->products as $item) {
                $lineTotal = $item['quantity'] * $item['price'];
                $discountPercent = $item['discount'] ?? 0;
                $subtotal += $lineTotal * (1 - ($discountPercent / 100));
            }

            $igtfAmount = 0;
            if ($request->has('payment_method_id')) {
                $paymentMethod = PaymentMethod::find($request->payment_method_id);
                if ($paymentMethod && $paymentMethod->applies_igtf) {
                    $igtfAmount = round($subtotal * 0.03, 2);
                }
            }

            $initialStatus = $request->has('payment_amount') ? 'verifying_payment' : 'pending_payment';

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

                // --- NUEVO: Usamos el InventoryService para salidas con bloqueo ---
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
}