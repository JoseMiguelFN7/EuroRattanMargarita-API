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
    public function uploadPhoto(Request $r){
        // Obtener el archivo de la solicitud
        $file = $r->file('image');

        // Generar un nombre único para la imagen
        $filename = time() . '-' . $file->getClientOriginalName();

        // Subir la imagen al directorio 'productPics' dentro de 'storage/app/public/assets'
        $url = $file->storeAs('assets/productPics', $filename, 'public');

        return $url;
    }

    //Obtener todos los muebles
    public function index(){
        $furnitures = Furniture::with(['furnitureType', 'product'])->get();
        return response()->json($furnitures);
    }

    //Obtener mueble por ID
    public function show($id)
    {
        $furniture = Furniture::with(['furnitureType', 'product'])->find($id); //Busca el mueble por ID

        if(!$furniture){
            return response()->json(['message'=>'Mueble no encontrado'], 404);
        }

        return response()->json($furniture);
    }

    //Crear mueble
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'description' => 'required|string|max:500',
            'furnitureType_id' => 'required|integer|exists:furniture_types,id',
            'material_ids' => 'required|array',
            'material_ids.*' => 'integer|exists:materials,id',
            'labor_ids' => 'required|array',
            'labor_ids.*' => 'integer|exists:labors,id',
            'profit_per' => 'required|numeric|min:0',
            'paint_per' => 'required|numeric|min:0',
            'labor_fab_per' => 'required|numeric|min:0',
            'sell' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        //enviar error si es necesario
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Procesado de imagen
            if ($request->hasFile('image')) {
                $image = $this->uploadPhoto($request);
            } else {
                $image = null;
            }

            // Crear producto
            $product = Product::create([
                'name' => $request->name,
                'code' => $request->code,
                'sell' => $request->sell,
                'image' => $image
            ]);

            // Crear mueble
            $furniture = Furniture::create([
                'product_id' => $product->id,
                'furniture_types_id' => $request->furnitureType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per
            ]);

            // Asociar los materiales con el mueble creado
            $furniture->materials()->sync($request->material_ids);

            // Asociar las MO con el mueble creado
            $furniture->labors()->sync($request->labor_ids);

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
            'material_ids' => 'sometimes|required|array',
            'material_ids.*' => 'integer|exists:materials,id',
            'labor_ids' => 'sometimes|required|array',
            'labor_ids.*' => 'integer|exists:labors,id',
            'profit_per' => 'sometimes|required|numeric|min:0',
            'paint_per' => 'sometimes|required|numeric|min:0',
            'labor_fab_per' => 'sometimes|required|numeric|min:0',
            'sell' => 'sometimes|required|boolean',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
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

            if ($request->hasFile('image')) {
                if($product->image){
                    // Eliminar la imagen anterior
                    Storage::disk('public')->delete($product->image);
                }
                $product->image = $this->uploadPhoto($request);
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

            // Sincronizar los materiales
            if ($request->has('material_ids')) {
                $furniture->materials()->sync($request->material_ids);
            }

            // Sincronizar las MO
            if ($request->has('labor_ids')) {
                $furniture->labors()->sync($request->labor_ids);
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
            if($product){
                if($product->image){
                    // Eliminar la imagen anterior
                    Storage::disk('public')->delete($product->image);
                }
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
