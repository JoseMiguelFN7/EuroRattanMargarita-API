<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Order;
use App\Models\Furniture;
use App\Models\InventoryAdjustment;
use App\Models\ProductMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class ProductMovementController extends Controller
{
    // Obtener todos los movimientos ordenados por fecha descendente
    public function index()
    {
        $productMovements = ProductMovement::with(['product.material', 'product.furniture', 'product.set', 'product.images', 'color'])
            ->orderBy('created_at', 'desc')
            ->get();

        $processedProducts = []; // Arreglo para rastrear los productos procesados

        // Mapear movimientos
        $productMovements = $productMovements->map(function ($movement) use (&$processedProducts) {
            if ($movement->product) {
                $productId = $movement->product->id;

                // Verificar si el producto ya fue procesado
                if (!in_array($productId, $processedProducts)) {
                    $movement->product->images = $movement->product->images->map(function ($image) {
                        // Ajustar la URL solo si no es absoluta
                        if (!str_starts_with($image->url, 'http://') && !str_starts_with($image->url, 'https://')) {
                            $image->url = asset('storage/' . $image->url);
                        }
                        return $image;
                    });

                    $movement->product->image = $movement->product->images->first() ? $movement->product->images->first()->url : null;

                    // Marcar el producto como procesado
                    $processedProducts[] = $productId;
                }
            }

            return $movement;
        });

        return response()->json($productMovements, 200);
    }

    //Obtener todos los movimientos de un producto por ID
    public function indexProduct($id)
    {
        $product = Product::find($id);

        if(!$product){
            return response()->json(['message'=>'Producto no encontrado'], 404);
        }

        $productMovements = $product->movements; //Busca los movimientos del producto por su id

        return response()->json($productMovements, 200);
    }

    public function getMovementsByProductCode(Request $request, $code)
    {
        // 1. Buscamos el producto por su código
        $product = Product::where('code', $code)->firstOrFail();
        
        $perPage = $request->input('per_page', 10);

        // 2. Iniciamos el Query Builder con Eager Loading
        $query = ProductMovement::with([
            'color',
            'movementable' => function ($morphTo) {
                $morphTo->morphWith([
                    Purchase::class               => ['supplier'],
                    Order::class                  => ['user'],
                    Furniture::class              => ['product'],
                    InventoryAdjustment::class    => ['user'], 
                    \App\Models\Commission::class => ['user'], 
                ]);
            }
        ])
        ->where('product_id', $product->id);

        // --- INICIO DE FILTROS ---

        // Filtro: Rango de Fechas
        if ($request->filled('start_date')) {
            $query->whereDate('movement_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('movement_date', '<=', $request->end_date);
        }

        // Filtro: Tipo de Movimiento (Entrada o Salida)
        if ($request->filled('type')) {
            if ($request->type === 'Entrada') {
                $query->where('quantity', '>', 0);
            } elseif ($request->type === 'Salida') {
                $query->where('quantity', '<', 0);
            }
        }

        // Filtro: Color
        if ($request->filled('color_id')) {
            // Permite filtrar por un color específico, o enviar 'null'/0 si se quiere ver los genéricos sin color
            if ($request->color_id === 'null' || $request->color_id == 0) {
                $query->whereNull('color_id');
            } else {
                $query->where('color_id', $request->color_id);
            }
        }

        // Filtro: Referencia / Origen (Compra, Orden, Encargo, etc.)
        if ($request->filled('reference_type')) {
            // Mapeamos el string que envía el frontend al modelo real de Eloquent
            $typeMap = [
                'Compra'      => Purchase::class,
                'Orden'       => Order::class,
                'Fabricación' => Furniture::class,
                'Ajuste'      => InventoryAdjustment::class,
                'Encargo'     => \App\Models\Commission::class,
            ];

            if (array_key_exists($request->reference_type, $typeMap)) {
                $query->where('movementable_type', $typeMap[$request->reference_type]);
            }
        }

        // --- FIN DE FILTROS ---

        // 3. Ejecutamos la consulta paginada
        $movements = $query->orderBy('movement_date', 'desc')->paginate($perPage);

        // 4. Transformamos la data (Mantenemos tu lógica intacta)
        $movements->through(function ($movement) {
            
            $originType    = 'Otro';
            $reason        = 'Ajuste / Otro';
            $details       = '';
            $user          = 'N/A';
            $referenceId   = null;
            $referenceCode = null;

            switch ($movement->movementable_type) {
                
                case Purchase::class:
                    $originType    = 'Compra';
                    $reason        = 'Reabastecimiento de inventario';
                    $details       = 'Proveedor: ' . ($movement->movementable->supplier->name ?? 'Desconocido');
                    $referenceId   = $movement->movementable->id ?? null;
                    $referenceCode = $movement->movementable->code ?? null;
                    break;

                case Order::class:
                    $originType    = 'Orden';
                    if ($movement->quantity > 0) {
                        $reason  = 'Reintegro por cancelación';
                        $details = 'Devolución de material/producto por Orden N° ' . ($movement->movementable->code ?? 'N/A');
                    } else {
                        $reason  = 'Venta / Orden de cliente';
                        $details = 'Orden N° ' . ($movement->movementable->code ?? 'N/A');
                    }
                    $user          = $movement->movementable->user->name ?? 'Cliente Desconocido';
                    $referenceId   = $movement->movementable->id ?? null;
                    $referenceCode = $movement->movementable->code ?? null;
                    break;

                case Furniture::class:
                    $originType    = 'Fabricación';
                    $reason        = $movement->quantity > 0 ? 'Ingreso por fabricación' : 'Material usado en fabricación';
                    $details       = 'Mueble: ' . ($movement->movementable->product->name ?? 'Desconocido');
                    $referenceId   = $movement->movementable->id ?? null;
                    break;

                case InventoryAdjustment::class:
                    $originType    = 'Ajuste';
                    $reason        = $movement->movementable->concept ?? 'Ajuste de inventario'; 
                    $details       = $movement->quantity > 0 ? 'Sobrante (Ingreso)' : 'Merma / Pérdida (Salida)';
                    $user          = $movement->movementable->user->name ?? 'Desconocido';
                    $referenceId   = $movement->movementable->id ?? null;
                    break;

                case \App\Models\Commission::class:
                    $originType    = 'Encargo';
                    $reason        = $movement->quantity > 0 ? 'Ingreso de mueble por encargo' : 'Salida por encargo';
                    $details       = 'Encargo N° ' . ($movement->movementable->code ?? 'N/A');
                    $user          = $movement->movementable->user->name ?? 'Cliente Desconocido';
                    $referenceId   = $movement->movementable->id ?? null;
                    $referenceCode = $movement->movementable->code ?? null;
                    break;
            }

            return [
                'id'         => $movement->id,
                'datetime'   => $movement->movement_date ?? $movement->created_at,
                'type'       => $movement->quantity > 0 ? 'Entrada' : 'Salida',
                'quantity'   => abs($movement->quantity),
                
                'variant'    => $movement->color ? $movement->color->name : 'Sin variante',
                'hex'        => $movement->color ? $movement->color->hex : null, 
                
                'reason'     => $reason,
                'details'    => $details,
                'user'       => $user,
                'reference'  => [
                    'type'   => $originType,
                    'id'     => $referenceId,
                    'code'   => $referenceCode
                ]
            ];
        });

        return response()->json($movements);
    }

    //Obtener movimiento por ID
    public function show($id)
    {
        $productMovement = ProductMovement::with(['product'])->find($id); //Busca el movimiento por ID

        if(!$productMovement){
            return response()->json(['message'=>'Movimiento no encontrado'], 404);
        }

        return response()->json($productMovement, 200);
    }

    //Crear un movimiento
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric',
            'color_id' => 'sometimes|required|numeric|exists:colors,id',
            'movement_date' => 'sometimes|required|date'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('movement_date')){
            $productMovement = ProductMovement::create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'color_id' => $request->color_id,
                'movement_date' => $request->movement_date
            ]);
        }else{
            $productMovement = ProductMovement::create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'color_id' => $request->color_id
            ]);
        }

        return response()->json($productMovement, 201);
    }

    // Método de creacion reutilizable
    public function createProductMovement($productId, $quantity, $movementDate, $colorId)
    {
        return ProductMovement::create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'color_id' => $colorId,
            'movement_date' => $movementDate
        ]);
    }

    //Actualizar un movimiento
    public function update(Request $request, $id){
        $productMovement = ProductMovement::find($id);

        if(!$productMovement){
            return response()->json(['message'=>'Movimiento no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'sometimes|required|exists:products,id',
            'quantity' => 'sometimes|required|numeric',
            'color_id' => 'sometimes|required|numeric|exists:colors,id',
            'movement_date' => 'sometimes|required|date'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('product_id')){
            $productMovement->product_id = $request->product_id;
        }

        if($request->has('quantity')){
            $productMovement->quantity = $request->quantity;
        }

        if($request->has('movement_date')){
            $productMovement->movement_date = $request->movement_date;
        }

        if($request->has('color_id')){
            $productMovement->color_id = $request->color_id;
        }

        $productMovement->save();

        return response()->json($productMovement, 200);
    }

    //Eliminar movimiento
    public function destroy($id){
        $productMovement = ProductMovement::find($id); //Busca el movimiento por ID

        if(!$productMovement){
            return response()->json(['message'=>'Movimiento no encontrado'], 404);
        }

        $productMovement->delete();

        return response()->json(['message' => 'Movimiento eliminado correctamente'], 200);
    }
}
