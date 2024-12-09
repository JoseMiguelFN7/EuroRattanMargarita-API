<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
