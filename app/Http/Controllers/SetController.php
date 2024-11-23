<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Set;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SetController extends Controller
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

    //Obtener todos los juegos
    public function index(){
        $sets = Set::with(['setType', 'product'])->get();
        return response()->json($sets);
    }

    //Obtener juego por ID
    public function show($id)
    {
        $set = Set::with(['setType', 'product'])->find($id); //Busca el juego por ID

        if(!$set){
            return response()->json(['message'=>'Juego no encontrado'], 404);
        }

        return response()->json($set);
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
                'description' => $request->description,
                'sell' => $request->sell,
                'image' => $image
            ]);

            // Crear mueble
            $set = Set::create([
                'product_id' => $product->id,
                'set_types_id' => $request->setType_id,
                'profit_per' => $request->profit_per,
                'paint_per' => $request->paint_per,
                'labor_fab_per' => $request->labor_fab_per
            ]);

            // Procesar materiales
            $furnituresData = [];
            foreach ($request->furnitures as $furniture) {
                $furnituresData[$furniture['id']] = ['quantity' => $furniture['quantity']];
            }
            $set->furnitures()->sync($furnituresData);

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
            if($product){
                if($product->image){
                    // Eliminar la imagen anterior
                    Storage::disk('public')->delete($product->image);
                }
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
