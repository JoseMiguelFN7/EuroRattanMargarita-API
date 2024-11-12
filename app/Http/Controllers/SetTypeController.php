<?php

namespace App\Http\Controllers;

use App\Models\SetType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SetTypeController extends Controller
{
    //Obtener todos los tipos de juegos
    public function index(){
        $setType = SetType::all();
        return response()->json($setType);
    }

    //Obtener tipo de juego por ID
    public function show($id)
    {
        $setType = SetType::find($id); //Busca el tipo de juego por ID

        if(!$setType){
            return response()->json(['message'=>'Tipo de juego no encontrado'], 404);
        }

        return response()->json($setType);
    }

    //Crear tipo de juego
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $setType = SetType::create([
            'name' => $request->name
        ]);

        return response()->json($setType, 201);
    }

    //Actualizar tipo de juego
    public function update(Request $request, $id){
        $setType = SetType::find($id);

        if(!$setType){
            return response()->json(['message'=>'Tipo de juego no encontrado'], 404);
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
            $setType->name = $request->name;
        }

        $setType->save();

        return response()->json($setType);
    }

    //Eliminar tipo de juego
    public function destroy($id){
        $setType = SetType::find($id); //Busca el tipo de juego por ID

        if(!$setType){
            return response()->json(['message'=>'Tipo de juego no encontrado'], 404);
        }

        $setType->delete();

        return response()->json(['message' => 'Tipo de juego eliminado correctamente'], 200);
    }
}
