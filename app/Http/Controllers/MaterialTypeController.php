<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaterialType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MaterialTypeController extends Controller
{
    //Obtener todos los tipos de materiales
    public function index(){
        $materialTypes = MaterialType::all();
        return response()->json($materialTypes);
    }

    public function indexByCategory(Request $request)
    {
        // 1. Capturamos los parámetros del frontend
        $perPage = $request->input('per_page', 10); // 10 por defecto
        $categoryId = $request->input('category_id');
        $search = $request->input('search');

        // 2. Iniciamos el Query con carga ansiosa de la categoría
        $query = MaterialType::with('category');

        // 3. Filtro exacto por Categoría (Estructural o Tapicería)
        if (!empty($categoryId)) {
            $query->where('material_category_id', $categoryId);
        }

        // 4. Filtro opcional de búsqueda por nombre (ej: "Grapas")
        if (!empty($search)) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // 5. Ejecutamos la paginación
        $types = $query->paginate($perPage);

        // 6. Limpieza de datos para el JSON
        $types->through(function ($type) {
            // Ocultamos fechas y la llave foránea (ya que la categoría viene anidada)
            $type->makeHidden(['created_at', 'updated_at', 'material_category_id']);
            
            if ($type->category) {
                $type->category->makeHidden(['created_at', 'updated_at']);
            }
            
            return $type;
        });

        return response()->json($types);
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
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            // 1. Validamos que la categoría exista
            'material_category_id' => 'required|integer|exists:material_categories,id',
            
            // 2. Validación de nombre único pero SCOPED (limitado) a la categoría
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('material_types')->where(function ($query) use ($request) {
                    return $query->where('material_category_id', $request->material_category_id);
                }),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        // 3. Creamos el registro con ambas columnas
        $materialType = MaterialType::create([
            'name' => $request->name,
            'material_category_id' => $request->material_category_id
        ]);

        // Cargamos la relación para devolver la respuesta completa
        $materialType->load('category');

        return response()->json($materialType, 201);
    }

    //Actualizar tipo de material
    public function update(Request $request, $id){
        $materialType = MaterialType::find($id);

        if(!$materialType){
            return response()->json(['message'=>'Tipo de material no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            // 1. Validamos la nueva categoría si la envían
            'material_category_id' => 'sometimes|required|integer|exists:material_categories,id',
            
            // 2. Validación de nombre único adaptada a la categoría
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('material_types')->where(function ($query) use ($request, $materialType) {
                    $categoryId = $request->input('material_category_id', $materialType->material_category_id);
                    return $query->where('material_category_id', $categoryId);
                })->ignore($id), // Ignoramos el ID actual para que no choque consigo mismo
            ]
        ]);

        if($validator->fails()){
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        // 3. Usamos fill() que es más limpio para actualizar los datos que vengan en el request
        $materialType->fill($request->only(['name', 'material_category_id']));
        $materialType->save();

        // 4. Cargamos la relación para que el frontend reciba el objeto actualizado y anidado
        $materialType->load('category');

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
