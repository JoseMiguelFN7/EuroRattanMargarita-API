<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
{
    //Obtener todas las unidades
    public function index(){
        $units = Unit::all();
        return response()->json($units);
    }

    //Obtener unidad por ID
    public function show($id)
    {
        $unit = Unit::find($id); //Busca la unidad por ID

        if(!$unit){
            return response()->json(['message'=>'Unidad no encontrada'], 404);
        }

        return response()->json($unit);
    }

    //Crear unidad
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:units,name',
            'allows_decimals' => 'required|boolean'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $unit = Unit::create([
            'name' => $request->name,
            'allows_decimals' => $request->boolean('allows_decimals')
        ]);

        return response()->json($unit, 201);
    }

    //Actualizar unidad
    public function update(Request $request, $id){
        $unit = Unit::find($id);

        if(!$unit){
            return response()->json(['message'=>'Unidad no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:units,name,' . $id,
            'allows_decimals' => 'sometimes|required|boolean'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('name')){
            $unit->name = $request->name;
        }

        if($request->has('allows_decimals')){
            $unit->allows_decimals = $request->boolean('allows_decimals');
        }

        $unit->save();

        return response()->json($unit);
    }

    //Eliminar unidad
    public function destroy($id){
        $unit = Unit::find($id); //Busca la unidad por ID

        if(!$unit){
            return response()->json(['message'=>'Unidad no encontrada'], 404);
        }

        $unit->delete();

        return response()->json(['message' => 'Unidad eliminada correctamente'], 200);
    }
}
