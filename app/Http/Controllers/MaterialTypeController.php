<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaterialType;
use Illuminate\Support\Facades\Validator;

class MaterialTypeController extends Controller
{
    //Obtener todos los tipos de materiales
    public function index(){
        $materialTypes = MaterialType::all();
        return response()->json($materialTypes);
    }

    //Obtener tipo de material por ID
    public function show($id)
    {
        $materialType = MaterialType::find($id); //Busca el tipo de material por ID

        if(!$materialType){
            return response()->json(['message'=>'Tipo de material no encontrado'], 404);
        }

        return response()->json($materialType);
    }

    //Crear tipo de material
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:material_types,name'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $materialType = MaterialType::create([
            'name' => $request->name
        ]);

        return response()->json($materialType, 201);
    }

    //Actualizar tipo de material
    public function update(Request $request, $id){
        $materialType = MaterialType::find($id);

        if(!$materialType){
            return response()->json(['message'=>'Tipo de material no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:material_types,name,' . $id
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('name')){
            $materialType->name = $request->name;
        }

        $materialType->save();

        return response()->json($materialType);
    }

    //Eliminar tipo de material
    public function destroy($id){
        $materialType = MaterialType::find($id); //Busca el tipo de material por ID

        if(!$materialType){
            return response()->json(['message'=>'Tipo de material no encontrado'], 404);
        }

        $materialType->delete();

        return response()->json(['message' => 'Tipo de material eliminado correctamente'], 200);
    }
}
