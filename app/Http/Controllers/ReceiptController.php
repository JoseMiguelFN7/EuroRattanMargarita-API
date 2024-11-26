<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\Color;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\ProductMovementController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class ReceiptController extends Controller
{
    //Obtener todas las facturas
    public function index(){
        $receipts = Receipt::with(['products', 'user'])->get();
        return response()->json($receipts);
    }

    //Obtener factura por ID
    public function show($id)
    {
        $receipt = Receipt::with(['products', 'user'])->find($id); //Busca la factura por ID

        if(!$receipt){
            return response()->json(['message'=>'Factura no encontrada'], 404);
        }

        return response()->json($receipt);
    }

    //Crear factura
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'products' => 'required|array',
            'products.*.id' => 'required|integer|exists:products,id',
            'products.*.color' => 'required|integer|exists:colors,id',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'required|numeric|min:0|max:100'
        ]);

        //enviar error si es necesario
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try{
            // Crear la factura
            $receipt = Receipt::create([
                'user_id' => $request->user_id, // Asociar al cliente
            ]);

            $now = $receipt->created_at; // Fecha de creación de la factura

            // Instancia el controlador de movimientos
            $movementController = new ProductMovementController();

            // Asociar productos a la factura y crear movimientos
            foreach ($request->products as $productData) {
                // Verificar si el producto y el color están relacionados correctamente
                $product = Product::find($productData['id']);
                $color = Color::find($productData['color']);

                // Comprobar si el producto y el color existen
                if (!$product || !$color) {
                    return response()->json([
                        'message' => 'Producto o color no encontrado',
                    ], 404);
                }

                // Asociar producto a la factura (tabla intermedia)
                $receipt->products()->attach($product->id, [
                    'quantity' => $productData['quantity'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'color_id' => $productData['color']
                ]);

                // Llamar a la función del controlador de movimientos
                $movementController->createProductMovement(
                    $product->id,
                    -abs($productData['quantity']), // Cantidad negativa
                    $now,
                    $color->id
                );
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json($receipt->load('products'), 201);
        } catch(\Exception $e){
            // Revertir la transacción en caso de error
            DB::rollback();

            // Devolver el error
            return response()->json([
                'message' => 'Error al crear la factura.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    //eliminar factura
    public function destroy($id)
    {
        // Buscar la factura
        $receipt = Receipt::find($id);

        if (!$receipt) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }

        // Iniciar una transacción para asegurar consistencia
        DB::beginTransaction();

        try {
            // Instancia del controlador de movimientos
            $movementController = new ProductMovementController();

            // Obtener productos asociados a la factura con su información adicional
            $products = $receipt->products()->withPivot('quantity', 'color_id')->get();

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
            $receipt->products()->detach();

            // Eliminar la factura
            $receipt->delete();

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Factura eliminada correctamente'], 200);
        } catch (\Exception $e) {
            // Revertir la transacción si ocurre un error
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar la factura.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}