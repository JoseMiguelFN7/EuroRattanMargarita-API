<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    //Obtener todos los colores
    public function index(){
        $colors = Color::all();
        $colors->makeHidden(['created_at', 'updated_at']);
        return response()->json($colors);
    }

    //Crear color
    public function store(Request $request){
        $request->validate([
            'hex' => 'required|string|unique:colors,hex',
            'name' => 'required|string|unique:colors,name'
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

    public function detachAndDeleteOrphanColors(int $productId)
    {
        // Obtener los colores asociados al producto
        $colors = Color::whereHas('products', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        })->withCount('products')->get();

        // Eliminar las relaciones en la tabla intermedia
        foreach ($colors as $color) {
            // Eliminar la relación del producto con el color
            $color->products()->detach($productId);
        }

        // Comprobar si los colores asociados al producto ya no están relacionados con otros productos ni con movimientos
        foreach ($colors as $color) {
            // Comprobar si el color no tiene productos relacionados ni movimientos asociados
            $hasMovements = $color->productMovements()->exists();

            if ($color->products_count === 0 && !$hasMovements) {
                // Si el color no tiene productos ni movimientos asociados, lo eliminamos
                $color->delete();
            }
        }
    }
}