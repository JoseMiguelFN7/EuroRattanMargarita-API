<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Color;

class ColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colors = [
            // --- NEUTROS ---
            ['name' => 'Blanco', 'hex' => '#FFFFFF', 'is_natural' => False],
            ['name' => 'Negro', 'hex' => '#000000', 'is_natural' => False],
            ['name' => 'Gris Claro', 'hex' => '#D3D3D3', 'is_natural' => False],
            ['name' => 'Gris Ratón', 'hex' => '#696969', 'is_natural' => False],
            ['name' => 'Gris Plomo', 'hex' => '#2F4F4F', 'is_natural' => False],
            ['name' => 'Crema', 'hex' => '#FFFDD0', 'is_natural' => False],
            
            // --- TIERRAS Y MADERAS ---
            ['name' => 'Rattan Natural', 'hex' => '#D4B483', 'is_natural' => True],
            ['name' => 'Beige', 'hex' => '#F5F5DC', 'is_natural' => False],
            ['name' => 'Arena', 'hex' => '#C2B280', 'is_natural' => False],
            ['name' => 'Marrón Café', 'hex' => '#4B3621', 'is_natural' => False],
            ['name' => 'Marrón Chocolate', 'hex' => '#D2691E', 'is_natural' => False],
            ['name' => 'Terracota', 'hex' => '#E2725B', 'is_natural' => False],
            ['name' => 'Wengué', 'hex' => '#645452', 'is_natural' => False],
            ['name' => 'Caoba', 'hex' => '#C04000', 'is_natural' => False],
            ['name' => 'Roble', 'hex' => '#806517', 'is_natural' => False],
            
            // --- VIVOS Y TAPICERÍA ---
            ['name' => 'Rojo', 'hex' => '#FF0000', 'is_natural' => False],
            ['name' => 'Vino Tinto', 'hex' => '#800020', 'is_natural' => False],
            ['name' => 'Azul Rey', 'hex' => '#4169E1', 'is_natural' => False],
            ['name' => 'Azul Marino', 'hex' => '#000080', 'is_natural' => False],
            ['name' => 'Azul Cielo', 'hex' => '#87CEEB', 'is_natural' => False],
            ['name' => 'Verde Bosque', 'hex' => '#228B22', 'is_natural' => False],
            ['name' => 'Verde Oliva', 'hex' => '#808000', 'is_natural' => False],
            ['name' => 'Amarillo Mostaza', 'hex' => '#FFDB58', 'is_natural' => False],
            ['name' => 'Naranja', 'hex' => '#FFA500', 'is_natural' => False],
            ['name' => 'Morado', 'hex' => '#800080', 'is_natural' => False],
            
            // --- METÁLICOS (Simulados) ---
            ['name' => 'Dorado', 'hex' => '#FFD700', 'is_natural' => False],
            ['name' => 'Plateado', 'hex' => '#C0C0C0', 'is_natural' => False],
        ];

        foreach ($colors as $color) {
            // Buscamos por HEX para no repetir el código de color
            Color::firstOrCreate(
                ['hex' => $color['hex']], 
                ['name' => $color['name']],
                ['is_natural' => $color['is_natural']]
            );
        }
    }
}
