<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    //Obtener todos los usuarios
    public function index(Request $request)
    {
        // 1. Configuración de parámetros
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search'); // <-- Capturamos el término de búsqueda

        // 2. Consulta con filtro dinámico y paginación
        $roles = Role::query()
            ->when($search, function ($query, $search) {
                // Buscamos coincidencias parciales en el nombre (ignorando mayúsculas/minúsculas)
                $query->where('name', 'like', "%{$search}%");
            })
            ->paginate($perPage);

        return response()->json($roles);
    }

    public function all()
    {
        $roles = Role::orderBy('name', 'asc')->get();
        return response()->json($roles);
    }

    //Obtener rol por ID
    public function show($id)
    {
        $role = Role::with('permissions')->find($id); //Busca el rol por ID

        if(!$role){
            return response()->json(['message'=>'Rol no encontrado'], 404);
        }

        $role->permissions->makeHidden(['pivot', 'slug', 'created_at', 'updated_at']);

        return response()->json($role);
    }

    //Crear rol
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:roles,name',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'integer|exists:permissions,id'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json($role, 201);
    }

    //Actualizar rol
    public function update(Request $request, $id){
        $role = Role::find($id);

        if(!$role){
            return response()->json(['message'=>'Rol no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:50|unique:roles,name,' . $role->id,
            'permissions' => 'sometimes|array',
            'permissions.*' => 'integer|exists:permissions,id'
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if($request->has('name')){
            $role->name = $request->name;
            $role->save();
        }

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        $role->load('permissions');

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
