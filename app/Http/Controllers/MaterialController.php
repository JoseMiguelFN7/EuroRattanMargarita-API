<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    //Obtener todos los materiales
    public function index(){
        $materials = Material::with(['materialTypes', 'product', 'unit'])->get()->map(function ($material) {
            $product = $material->product;

            // Agregar las URL completas de las imágenes del producto
            $product->images = $product->images->map(function ($image) {
                return asset('storage/' . $image->url);
            });
            
            return $material;
        });

        return response()->json($materials);
    }

    //Obtener material por ID
    public function show($id)
    {
        $material = Material::with(['materialTypes', 'product', 'unit'])->find($id); //Busca el material por ID

        if(!$material){
            return response()->json(['message'=>'Material no encontrado'], 404);
        }

        $product = $material->product;

        $product->images = $product->images->map(function ($image) {
            return asset('storage/' . $image->url); // Generar las URLs completas de las imágenes
        });

        return response()->json($material);
    }

    //Obtener una cantidad especifica de materiales en orden aleatorio
    public function rand($quantity)
    {
        // Validar que el parámetro es un número entero positivo
        if (!is_numeric($quantity) || $quantity <= 0) {
            return response()->json([
                'error' => 'La cantidad debe ser un número entero positivo.'
            ], 400);
        }

        // Obtener registros aleatorios
        $materials = Material::with(['materialTypes', 'product', 'unit'])
            ->whereHas('product', function ($query) {
                $query->where('sell', true); // Filtrar por 'sell = true'
            })
            ->inRandomOrder() // Seleccionar en orden aleatorio
            ->take($quantity) // Limitar la cantidad
            ->get()
            ->map(function ($material) {
                // Obtener solo la primera imagen del producto, si existe
                $material->product->image = $material->product->images->first() 
                    ? asset('storage/' . $material->product->images->first()->url) 
                    : null;
                return $material;
            });

        return response()->json($materials);
    }

    //Crear material
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'material_type_ids' => 'required|array',
            'material_type_ids.*' => 'integer|exists:material_types,id',
            'price' => 'required|numeric',
            'unit_id' => 'required|numeric',
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

        try{
            //Crear el producto
            $product = Product::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'sell' => $request->sell
            ]);

            //procesado de imagen
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            //Crear el material
            $material = Material::create([
                'product_id' => $product->id,
                'unit_id' => $request->unit_id,
                'price' => $request->price,
            ]);

            // Asociar los tipos de materiales con el material creado
            $material->materialTypes()->sync($request->material_type_ids);

            // Confirmar la transacción
            DB::commit();

            return response()->json($material, 201);
        } catch(\Exception $e){
            // Revertir la transacción en caso de error
            DB::rollback();

            // Devolver el error
            return response()->json([
                'message' => 'Error al guardar el material.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    //actualizar material
    public function update(Request $request, $id){
        $material = Material::find($id);

        if(!$material){
            return response()->json(['message'=>'Material no encontrado'], 404);
        }

        $product = $material->product;

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:products',
            'description' => 'sometimes|required|string|max:500',
            'sell' => 'sometimes|required|boolean',
            'material_type_ids' => 'sometimes|required|array',
            'material_type_ids.*' => 'integer|exists:material_types,id',
            'price' => 'sometimes|required|numeric|min:0',
            'unit_id' => 'sometimes|required|numeric',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try{
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

            if($request->has('price')){
                $material->price = $request->price;
            }

            // Procesar y almacenar nuevas imágenes
            if ($request->hasFile('images')) {
                // Eliminar las imágenes anteriores relacionadas con este producto (si es necesario)
                $product->productImages()->delete(); // Elimina todas las imágenes actuales

                $files = $request->file('images');
                app(ProductImageController::class)->uploadImages($product->id, $files);
            }

            // Sincronizar los tipos de material
            if ($request->has('material_type_ids')) {
                $material->materialTypes()->sync($request->material_type_ids);
            }

            if($request->has('unit_id')){
                $material->unit_id = $request->unit_id;
            }

            if($request->has('profit_per')){
                $material->profit_per = $request->profit_per;
            }

            if($request->has('paint_per')){
                $material->paint_per = $request->paint_per;
            }

            if($request->has('labor_fab_per')){
                $material->labor_fab_per = $request->labor_fab_per;
            }

            $product->save();
            $material->save();
            DB::commit();
        }catch(Exception $e){
            DB::rollback();

            // Devolver el error
            return response()->json([
                'message' => 'Error al actualizar el material.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        return response()->json($material);
    }

    //Eliminar material
    public function destroy($id){
        DB::beginTransaction();

        try{
            $material = Material::find($id);

            if(!$material){
                return response()->json(['message' => 'Material no encontrado'], 404);
            }

            $product = $material->product;

            $material->delete();
            // Eliminar las imágenes asociadas al producto

            if ($product) {
                // Llamar al controlador de imágenes para eliminar las imágenes asociadas
                app(ProductImageController::class)->deleteImages($product->id);
                $product->delete();
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Material eliminado correctamente'], 200);

        } catch (\Exception $e){
            // Deshacer la transacción si ocurre un error
            DB::rollBack();

            // Devolver el error
            return response()->json([
                'message' => 'Error al eliminar el material.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
