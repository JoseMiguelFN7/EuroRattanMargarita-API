<?php

namespace App\Http\Controllers;

use App\Models\Labor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LaborController extends Controller
{
    //Obtener todas las MO
    public function index(){
        $labors = Labor::all();
        return response()->json($labors);
    }

    //Obtener MO por ID
    public function show($id)
    {
        $labor = Labor::find($id); //Busca el tipo de mueble por ID

        if(!$labor){
            return response()->json(['message'=>'MO no encontrada'], 404);
        }

        return response()->json($labor);
    }

    //Crear MO
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'daily_pay' => 'required|numeric|min:0'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $labor = Labor::create([
            'name' => $request->name,
            'daily_pay' => $request->daily_pay
        ]);

        return response()->json($labor, 201);
    }

    //Actualizar MO
    public function update(Request $request, $id){
        $labor = Labor::find($id);

        if(!$labor){
            return response()->json(['message'=>'MO no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'daily_pay' => 'sometimes|required|numeric|min:0'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('name')){
            $labor->name = $request->name;
        }

        if($request->has('daily_pay')){
            $labor->daily_pay = $request->daily_pay;
        }

        $labor->save();

        return response()->json($labor);
    }

    //Eliminar MO
    public function destroy($id){
        $labor = Labor::find($id); //Busca la MO por ID

        if(!$labor){
            return response()->json(['message'=>'MO no encontrada'], 404);
        }

        $labor->delete();

        return response()->json(['message' => 'MO eliminada correctamente'], 200);
    }
}
