<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
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

    //Obtener todos los materiales
    public function index(){
        $materials = Material::with(['materialTypes', 'product', 'unit'])->get();
        return response()->json($materials);
    }

    //Obtener material por ID
    public function show($id)
    {
        $material = Material::with(['materialTypes', 'product', 'unit'])->find($id); //Busca el material por ID

        if(!$material){
            return response()->json(['message'=>'Material no encontrado'], 404);
        }

        return response()->json($material);
    }

    //Crear material
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products',
            'material_type_ids' => 'required|array',
            'material_type_ids.*' => 'integer|exists:material_types,id',
            'cost' => 'required|numeric',
            'unit_id' => 'required|numeric',
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

        try{
            //procesado de imagen
            if ($request->hasFile('image')) {
                $image = $this->uploadPhoto($request);
            } else{
                $image = null;
            }
            //Crear el producto
            $product = Product::create([
                'name' => $request->name,
                'code' => $request->code,
                'sell' => $request->sell,
                'image' => $image
            ]);

            //Crear el material
            $material = Material::create([
                'product_id' => $product->id,
                'unit_id' => $request->unit_id,
                'cost' => $request->cost,
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
            'sell' => 'sometimes|required|boolean',
            'material_type_ids' => 'sometimes|required|array',
            'material_type_ids.*' => 'integer|exists:material_types,id',
            'cost' => 'sometimes|required|numeric|min:0',
            'unit_id' => 'sometimes|required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
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
            if($product){
                if($product->image){
                    // Eliminar la imagen anterior
                    Storage::disk('public')->delete($product->image);
                }
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
