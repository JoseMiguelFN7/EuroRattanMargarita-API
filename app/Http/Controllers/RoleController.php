<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    //Obtener todos los usuarios
    public function index(){
        $roles = Role::all();
        return response()->json($roles);
    }

    //Obtener rol por ID
    public function show($id)
    {
        $role = Role::find($id); //Busca el rol por ID

        if(!$role){
            return response()->json(['message'=>'Rol no encontrado'], 404);
        }

        return response()->json($role);
    }

    //Crear rol
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name
        ]);

        return response()->json($role, 201);
    }

    //Actualizar rol
    public function update(Request $request, $id){
        $role = Role::find($id);

        if(!$role){
            return response()->json(['message'=>'Rol no encontrado'], 404);
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
            $role->name = $request->name;
        }

        $role->save();

        return response()->json($role);
    }

    //Eliminar tipo de material
    public function destroy($id){
        $role = Role::find($id); //Busca el rol por ID

        if(!$role){
            return response()->json(['message'=>'Rol no encontrado'], 404);
        }

        $role->delete();

        return response()->json(['message' => 'Rol eliminado correctamente'], 200);
    }
}
