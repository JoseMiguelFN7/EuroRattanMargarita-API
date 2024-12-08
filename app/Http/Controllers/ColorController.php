<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function store($hex){
        $color = Color::create([
            'hex' => $hex
        ]);

        return $color;
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