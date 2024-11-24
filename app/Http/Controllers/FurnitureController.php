<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Furniture;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class FurnitureController extends Controller
{
    //Obtener todos los muebles
    public function index(){
        $furnitures = Furniture::with(['furnitureType', 'product'])->get()->map(function ($furniture) {
            $product = $furniture->product;

            // Agregar las URL completas de las imágenes del producto
            $product->images = $product->images->map(function ($image) {
                return asset('storage/' . $image->url);
            });
            
            return $furniture;
        });

        return response()->json($furnitures);
    }

    //Obtener mueble por ID
    public function show($id)
    {
        $furniture = Furniture::with(['furnitureType', 'product'])->find($id); //Busca el mueble por ID

        if(!$furniture){
            return response()->json(['message'=>'Mueble no encontrado'], 404);
        }

        $product = $furniture->product;

        $product->images = $product->images->map(function ($image) {
            return asset('storage/' . $image->url); // Generar las URLs completas de las imágenes
        });

        return response()->json($furniture);
    }

    //Obtener una cantidad especifica de muebles en orden aleatorio
    public function rand($quantity)
    {
        // Validar que el parámetro es un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Obtener registros aleatorios
        $furnitures = Product::with(['furnitureType', 'product', 'unit'])
            ->whereHas('product', function ($query) {
                $query->where('sell', true); // Filtrar por 'sell = true'
            })
            ->inRandomOrder() // Seleccionar en orden aleatorio
            ->take($quantity) // Limitar la cantidad
            ->get()
            ->map(function ($furniture) {
                // Obtener solo la primera imagen del producto, si existe
                $furniture->product->image = $furniture->product->images->first() 
                    ? asset('storage/' . $furniture->product->images->first()->url) 
                    : null;
                return $furniture;
            });

        return response()->json($furnitures);
    }

    //Crear mueble
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'furnitureType_id' => 'required|integer|exists:furniture_types,id',
            'materials' => 'required|array',
            'materials.*.id' => 'required|integer|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
            'labors' => 'required|array',
            'labors.*.id' => 'required|integer|exists:labors,id',
            'labors.*.days' => 'required|numeric|min:0',
            'profit_per' => 'required|numeric|min:0',
            'paint_per' => 'required|numeric|min:0',
            'labor_fab_per' => 'required|numeric|min:0',
            'sell' => 'required|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        //enviar error si es necesario
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Crear producto
            $product = Product::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'sell' => $request->sell,
            ]);

            // Crear mueble
            $furniture = Furniture::create([
                'product_id' => $product->id,
                'furniture_types_id' => $request->furnitureType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per
            ]);

            //procesado de imagen
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            // Procesar materiales
            $materialsData = [];
            foreach ($request->materials as $material) {
                $materialsData[$material['id']] = ['quantity' => $material['quantity']];
            }
            $furniture->materials()->sync($materialsData);

            // Procesar manos de obra
            $laborsData = [];
            foreach ($request->labors as $labor) {
                $laborsData[$labor['id']] = ['days' => $labor['days']];
            }
            $furniture->labors()->sync($laborsData);

            // Confirmar la transacción
            DB::commit();

            return response()->json($furniture, 201);

        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            
            // Devolver el error
            return response()->json([
                'message' => 'Error al guardar el mueble.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    //actualizar mueble
    public function update(Request $request, $id){
        $furniture = Furniture::find($id);

        if(!$furniture){
            return response()->json(['message'=>'Mueble no encontrado'], 404);
        }

        $product = $furniture->product;

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:products',
            'description' => 'sometimes|required|string|max:500',
            'furnitureType_id' => 'sometimes|required|integer|exists:furniture_types,id',
            'materials' => 'sometimes|required|array',
            'materials.*.id' => 'required|integer|exists:materials,id',
            'materials.*.quantity' => 'required|numeric|min:0',
            'labors' => 'sometimes|required|array',
            'labors.*.id' => 'required|integer|exists:labors,id',
            'labors.*.days' => 'required|numeric|min:0',
            'profit_per' => 'sometimes|required|numeric|min:0',
            'paint_per' => 'sometimes|required|numeric|min:0',
            'labor_fab_per' => 'sometimes|required|numeric|min:0',
            'sell' => 'sometimes|required|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try {
            if($request->has('name')){
                $product->name = $request->name;
            }

            if($request->has('code')){
                $product->code = $request->code;
            }

            if($request->has('description')){
                $product->description = $request->description;
            }

            if($request->has('sell')){
                $product->sell = $request->sell;
            }

            // Procesar y almacenar nuevas imágenes
            if ($request->hasFile('images')) {
                // Eliminar las imágenes anteriores relacionadas con este producto (si es necesario)
                $product->productImages()->delete(); // Elimina todas las imágenes actuales

                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            if($request->has('furnitureType_id')){
                $furniture->furnitureType_id = $request->furnitureType_id;
            }

            if($request->has('profit_per')){
                $furniture->profit_per = $request->profit_per;
            }

            if($request->has('paint_per')){
                $furniture->paint_per = $request->paint_per;
            }

            if($request->has('labor_fab_per')){
                $furniture->labor_fab_per = $request->labor_fab_per;
            }

            // Sincronizar materiales con cantidades
            if ($request->has('materials')) {
                $materialsData = collect($request->materials)->mapWithKeys(function ($material) {
                    return [$material['id'] => ['quantity' => $material['quantity']]];
                })->toArray();
                $furniture->materials()->sync($materialsData);
            }

            // Sincronizar mano de obra con días
            if ($request->has('labors')) {
                $laborsData = collect($request->labors)->mapWithKeys(function ($labor) {
                    return [$labor['id'] => ['days' => $labor['days']]];
                })->toArray();
                $furniture->labors()->sync($laborsData);
            }

            $product->save();
            $furniture->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Devolver el error
            return response()->json([
                'message' => 'Error al actualizar el mueble.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        return response()->json($furniture);
    }

    //Eliminar mueble
    public function destroy($id){
        DB::beginTransaction();

        try{
            $furniture = Furniture::find($id);

            if(!$furniture){
                return response()->json(['message' => 'Mueble no encontrado'], 404);
            }

            $product = $furniture->product;

            $furniture->delete();
            if ($product) {
                // Llamar al controlador de imágenes para eliminar las imágenes asociadas
                app(ProductImageController::class)->deleteImages($product->id);
                $product->delete();
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Mueble eliminado correctamente'], 200);

        } catch (\Exception $e){
            // Deshacer la transacción si ocurre un error
            DB::rollBack();

            // Devolver el error
            return response()->json([
                'message' => 'Error al eliminar el mueble.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
