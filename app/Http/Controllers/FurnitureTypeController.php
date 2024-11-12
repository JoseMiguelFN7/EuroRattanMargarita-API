<?php

namespace App\Http\Controllers;

use App\Models\FurnitureType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FurnitureTypeController extends Controller
{
    //Obtener todos los tipos de muebles
    public function index(){
        $furnitureTypes = FurnitureType::all();
        return response()->json($furnitureTypes);
    }

    //Obtener tipo de mueble por ID
    public function show($id)
    {
        $furnitureType = FurnitureType::find($id); //Busca el tipo de mueble por ID

        if(!$furnitureType){
            return response()->json(['message'=>'Tipo de mueble no encontrado'], 404);
        }

        return response()->json($furnitureType);
    }

    //Crear tipo de mueble
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $furnitureType = FurnitureType::create([
            'name' => $request->name
        ]);

        return response()->json($furnitureType, 201);
    }

    //Actualizar tipo de mueble
    public function update(Request $request, $id){
        $furnitureType = FurnitureType::find($id);

        if(!$furnitureType){
            return response()->json(['message'=>'Tipo de mueble no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('name')){
            $furnitureType->name = $request->name;
        }

        $furnitureType->save();

        return response()->json($furnitureType);
    }

    //Eliminar tipo de mueble
    public function destroy($id){
        $furnitureType = FurnitureType::find($id); //Busca el tipo de mueble por ID

        if(!$furnitureType){
            return response()->json(['message'=>'Tipo de mueble no encontrado'], 404);
        }

        $furnitureType->delete();

        return response()->json(['message' => 'Tipo de mueble eliminado correctamente'], 200);
    }
}
