<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Set;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SetController extends Controller
{
    //Obtener todos los juegos
    public function index(){
        $sets = Set::with(['setType', 'product.images', 'product.colors'])->get()->map(function ($set) {
            $product = $set->product;

            if($product->images->isNotEmpty()){
                // Agregar las URL completas de las imágenes del producto
                $product->images = $product->images->map(function ($image) {
                    return asset('storage/' . $image->url);
                });

                $product->image = $product->images[0];
            }

            return $set;
        });
        
        return response()->json($sets);
    }

    //Obtener juego por ID
    public function show($id)
    {
        $set = Set::with(['setType', 'product.images', 'product.colors'])->find($id); //Busca el juego por ID

        if(!$set){
            return response()->json(['message'=>'Juego no encontrado'], 404);
        }

        $product = $set->product;

        $product->images = $product->images->map(function ($image) {
            return asset('storage/' . $image->url); // Generar las URLs completas de las imágenes
        });

        return response()->json($set);
    }

    //Obtener una cantidad especifica de juegos en orden aleatorio
    public function rand($quantity)
    {
        // Validar que el parámetro es un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Obtener registros aleatorios
        $sets = Product::with(['setType', 'product.images'])
            ->whereHas('product', function ($query) {
                $query->where('sell', true); // Filtrar por 'sell = true'
            })
            ->inRandomOrder() // Seleccionar en orden aleatorio
            ->take($quantity) // Limitar la cantidad
            ->get()
            ->map(function ($set) {
                // Obtener solo la primera imagen del producto, si existe
                $set->product->image = $set->product->images->first() 
                    ? asset('storage/' . $set->product->images->first()->url) 
                    : null;
                return $set;
            });

        return response()->json($sets);
    }

    //Crear juego
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'setType_id' => 'required|integer|exists:furniture_types,id',
            'furnitures' => 'required|array',
            'furnitures.*.id' => 'integer|exists:furnitures,id',
            'furnitures.*.quantity' => 'required|numeric|min:0',
            'profit_per' => 'required|numeric|min:0',
            'paint_per' => 'required|numeric|min:0',
            'labor_fab_per' => 'required|numeric|min:0',
            'sell' => 'required|boolean',
            'discount' => 'required|numeric|min:0|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'colors' => 'nullable|array',
            'colors.*' => 'string|regex:/^#([A-Fa-f0-9]{6})$/'
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
                'sell' => $request->sell
            ]);

            // Crear juego
            $set = Set::create([
                'product_id' => $product->id,
                'set_types_id' => $request->setType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per
            ]);

            //procesado de imagen
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            // Procesar muebles
            $furnituresData = [];
            foreach ($request->furnitures as $furniture) {
                $furnituresData[$furniture['id']] = ['quantity' => $furniture['quantity']];
            }
            $set->furnitures()->sync($furnituresData);

            // Procesar colores
            if ($request->has('colors')) {
                $colorIds = app(ColorController::class)->getOrCreateColors($request->colors);
                $product->colors()->sync($colorIds);
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json($set, 201);

        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            
            // Devolver el error
            return response()->json([
                'message' => 'Error al guardar el juego.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    //actualizar juego
    public function update(Request $request, $id){
        $set = Set::find($id);

        if(!$set){
            return response()->json(['message'=>'Juego no encontrado'], 404);
        }

        $product = $set->product;

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:products',
            'description' => 'sometimes|required|string|max:500',
            'setType_id' => 'sometimes|required|integer|exists:set_types,id',
            'furnitures' => 'sometimes|required|array',
            'furnitures.*.id' => 'required|integer|exists:furnitures,id',
            'furnitures.*.quantity' => 'required|numeric|min:0',
            'profit_per' => 'sometimes|required|numeric|min:0',
            'paint_per' => 'sometimes|required|numeric|min:0',
            'labor_fab_per' => 'sometimes|required|numeric|min:0',
            'sell' => 'sometimes|required|boolean',
            'discount' => 'sometimes|required|numeric|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'colors' => 'nullable|array',
            'colors.*' => 'string|regex:/^#([A-Fa-f0-9]{6})$/'
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

            if($request->has('discount')){
                $product->discount = $request->discount;
            }

            // Procesar y almacenar nuevas imágenes
            if ($request->hasFile('images')) {
                // Eliminar las imágenes anteriores relacionadas con este producto (si es necesario)
                $product->productImages()->delete(); // Elimina todas las imágenes actuales

                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            if($request->has('setType_id')){
                $set->setType_id = $request->setType_id;
            }

            if($request->has('profit_per')){
                $set->profit_per = $request->profit_per;
            }

            if($request->has('paint_per')){
                $set->paint_per = $request->paint_per;
            }

            if($request->has('labor_fab_per')){
                $set->labor_fab_per = $request->labor_fab_per;
            }

            // Sincronizar muebles con cantidades
            if ($request->has('furnitures')) {
                $furnituresData = collect($request->furnitures)->mapWithKeys(function ($furniture) {
                    return [$furniture['id'] => ['quantity' => $furniture['quantity']]];
                })->toArray();
                $set->furnitures()->sync($furnituresData);
            }

            // Procesar colores
            if ($request->has('colors')) {
                $colorIds = app(ColorController::class)->getOrCreateColors($request->colors);
                $product->colors()->sync($colorIds);
            }

            $product->save();
            $set->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Devolver el error
            return response()->json([
                'message' => 'Error al actualizar el juego.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        return response()->json($set);
    }

    //Eliminar juego
    public function destroy($id){
        DB::beginTransaction();

        try{
            $set = Set::find($id);

            if(!$set){
                return response()->json(['message' => 'Juego no encontrado'], 404);
            }

            $product = $set->product;

            $set->delete();
            if ($product) {
                // Llamar al controlador de colores para eliminar los colores asociadas
                app(ColorController::class)->detachAndDeleteOrphanColors($product->id);

                // Llamar al controlador de imágenes para eliminar las imágenes asociadas
                app(ProductImageController::class)->deleteImages($product->id);
                
                $product->delete();
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Juego eliminado correctamente'], 200);

        } catch (\Exception $e){
            // Deshacer la transacción si ocurre un error
            DB::rollBack();

            // Devolver el error
            return response()->json([
                'message' => 'Error al eliminar el juego.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
