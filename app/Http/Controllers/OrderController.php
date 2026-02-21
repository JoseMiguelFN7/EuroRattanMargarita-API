<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\ProductMovementController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class OrderController extends Controller
{
    public function index(Request $request)
    {
        // 1. Preparamos la consulta
        // Necesitamos cargar 'products' para poder hacer el cálculo matemático,
        // aunque luego no lo enviemos en el JSON final.
        $query = Order::with(['user:id,name,email', 'products']);

        // 2. Filtros
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('user_id')) $query->where('user_id', $request->user_id);
        if ($request->has('code')) $query->where('code', 'like', '%' . $request->code . '%');

        // 3. Paginación
        $perPage = $request->input('per_page', 10);
        
        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        // 4. Transformación (Limpieza y Cálculo)
        $orders->through(function ($order) {
            
            // CÁLCULO DEL TOTAL AL VUELO
            // Sumamos (Cantidad * Precio) de cada producto asociado
            $totalCalculated = $order->products->sum(function ($product) {
                $subtotal = $product->pivot->quantity * $product->pivot->price;
                
                // Si manejas descuentos como porcentaje (ej: 10%), descomenta esto:
                // $discountAmount = $subtotal * ($product->pivot->discount / 100);
                // return $subtotal - $discountAmount;

                // Si manejas descuentos como monto fijo o es 0:
                return $subtotal; 
            });

            return [
                'id'            => $order->id,
                'code'          => $order->code,
                'status'        => $order->status,
                'created_at'    => $order->created_at->toDateTimeString(), // 2026-02-18 23:41:05
                'exchange_rate' => $order->exchange_rate,
                'notes'         => $order->notes,
                
                // Campo calculado automáticamente
                'total_usd'     => round($totalCalculated, 2),

                // Solo mandamos el usuario, los productos se eliminaron de la respuesta
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
                
                // Calculamos el total en USD
                $totalUsd = $order->products->sum(function ($product) {
                    $price = $product->pivot->price;
                    $quantity = $product->pivot->quantity;
                    $discount = $product->pivot->discount ?? 0;
                    
                    return ($price * $quantity) - $discount; 
                });

                return [
                    'id' => $order->id,
                    'code' => $order->code,
                    'status' => $order->status,
                    'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                    'exchange_rate' => $order->exchange_rate,
                    'notes' => $order->notes,
                    'total_usd' => (float) round($totalUsd, 2),
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

        // 3. Formateo y limpieza de la respuesta
        $cleanOrder = [
            // Datos generales de la Orden
            'id'            => $order->id,
            'code'          => $order->code,
            'status'        => $order->status,
            'created_at'    => $order->created_at->toDateTimeString(),
            'exchange_rate' => $order->exchange_rate,
            'notes'         => $order->notes,

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

    //Crear Orden
    public function store(Request $request)
    {
        // 1. VALIDACIÓN
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

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        $uploadedPath = null;

        try {
            // 2. CREAR LA ORDEN
            $initialStatus = $request->has('payment_amount') ? 'verifying_payment' : 'pending_payment';

            $order = Order::create([
                'user_id'       => $request->user_id,
                'exchange_rate' => $request->exchange_rate,
                'notes'         => $request->notes,
                'status'        => $initialStatus,
            ]);

            $now = $order->created_at;

            // OPTIMIZACIÓN: Cargamos todos los productos involucrados de una vez para evitar consultas N+1
            $productIds = collect($request->products)->pluck('id');
            $productsData = Product::with('set.furnitures')->whereIn('id', $productIds)->get()->keyBy('id');

            // 3. RECORRER PRODUCTOS (Pivot + Movimiento Manual)
            foreach ($request->products as $item) {
                $variantId = $item['variant_id'] ?? null;
                $productModel = $productsData[$item['id']];

                // A. Insertar en tabla intermedia (La factura sigue mostrando el Juego o Producto tal cual)
                $order->products()->attach($item['id'], [
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'discount'   => $item['discount'] ?? 0,
                    'variant_id' => $variantId,
                ]);

                // B. Crear Movimiento(s) de Inventario
                if ($productModel->set && $productModel->set->furnitures) {
                    // Es un Juego: Iteramos sobre los muebles que lo componen
                    foreach ($productModel->set->furnitures as $furniture) {
                        // Calculamos la cantidad real a descontar: (Cantidad de juegos) * (Cantidad de este mueble por juego)
                        $qtyPerSet = $furniture->pivot->quantity;
                        $totalToDeduct = -abs($item['quantity'] * $qtyPerSet);

                        ProductMovement::create([
                            'product_id'        => $furniture->product_id, // Usamos el product_id del mueble individual
                            'color_id'          => $variantId, // Aplicamos el color del juego a los muebles
                            'quantity'          => $totalToDeduct,
                            'movement_date'     => $now,
                            'movementable_id'   => $order->id,
                            'movementable_type' => Order::class,
                        ]);
                    }
                } else {
                    // Es un producto normal (Mueble individual, Material, etc.)
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

            // 4. PROCESAR PAGO
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
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
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