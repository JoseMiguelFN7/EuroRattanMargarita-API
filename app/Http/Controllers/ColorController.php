<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    //Obtener todos los colores
    public function indexAll(){
        $colors = Color::all();
        $colors->makeHidden(['created_at', 'updated_at']);
        return response()->json($colors);
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        // 1. Consulta dinámica y paginación
        $colors = Color::query()
            ->when($search, function ($query, $search) {
                // Agrupamos la búsqueda para evitar conflictos de lógica SQL
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('hex', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);

        // 2. Limpieza de datos usando through() para el paginador
        $colors->through(function ($color) {
            $color->makeHidden(['created_at', 'updated_at']);
            return $color;
        });

        return response()->json($colors);
    }

    //Crear color
    public function store(Request $request){
        $request->validate([
            'hex' => 'required|string|max:16|unique:colors,hex',
            'name' => 'required|string|max:100|unique:colors,name'
        ]);

        $color = Color::create([
            'hex' => $request->hex,
            'name' => $request->name
        ]);

        return response()->json($color, 201);
    }

    public function update(Request $request, $id){
        $color = Color::find($id);

        if(!$color){
            return response()->json(['message' => 'Color not found'], 404);
        }

        $request->validate([
            'hex' => 'required|string|unique:colors,hex,' . $color->id,
            'name' => 'required|string|unique:colors,name,' . $color->id
        ]);

        $color->update([
            'hex' => $request->hex,
            'name' => $request->name
        ]);

        return response()->json($color);
    }

    public function delete($id){
        $color = Color::find($id);

        if(!$color){
            return response()->json(['message' => 'Color not found'], 404);
        }

        if ($color->products()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el color.',
                'reason' => 'El color está asignado a uno o más productos.'
            ], 409);
        }

        $color->delete();

        return response()->json(['message' => 'Color eliminado correctamente.'], 200);
    }

    public function getOrCreateColors(array $colors){
        return collect($colors)->map(function ($hex) {
            return Color::firstOrCreate(['hex' => $hex])->id;
        });
    }
}