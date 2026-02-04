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
            ['name' => 'Blanco', 'hex' => '#FFFFFF'],
            ['name' => 'Negro', 'hex' => '#000000'],
            ['name' => 'Gris Claro', 'hex' => '#D3D3D3'],
            ['name' => 'Gris Ratón', 'hex' => '#696969'],
            ['name' => 'Gris Plomo', 'hex' => '#2F4F4F'],
            ['name' => 'Crema', 'hex' => '#FFFDD0'],
            
            // --- TIERRAS Y MADERAS (Importante para Muebles) ---
            ['name' => 'Rattan Natural', 'hex' => '#D4B483'],
            ['name' => 'Beige', 'hex' => '#F5F5DC'],
            ['name' => 'Arena', 'hex' => '#C2B280'],
            ['name' => 'Marrón Café', 'hex' => '#4B3621'],
            ['name' => 'Marrón Chocolate', 'hex' => '#D2691E'],
            ['name' => 'Terracota', 'hex' => '#E2725B'],
            ['name' => 'Wengué', 'hex' => '#645452'],
            ['name' => 'Caoba', 'hex' => '#C04000'],
            ['name' => 'Roble', 'hex' => '#806517'],
            
            // --- VIVOS Y TAPICERÍA ---
            ['name' => 'Rojo', 'hex' => '#FF0000'],
            ['name' => 'Vino Tinto', 'hex' => '#800020'],
            ['name' => 'Azul Rey', 'hex' => '#4169E1'],
            ['name' => 'Azul Marino', 'hex' => '#000080'],
            ['name' => 'Azul Cielo', 'hex' => '#87CEEB'],
            ['name' => 'Verde Bosque', 'hex' => '#228B22'],
            ['name' => 'Verde Oliva', 'hex' => '#808000'],
            ['name' => 'Amarillo Mostaza', 'hex' => '#FFDB58'],
            ['name' => 'Naranja', 'hex' => '#FFA500'],
            ['name' => 'Morado', 'hex' => '#800080'],
            
            // --- METÁLICOS (Simulados) ---
            ['name' => 'Dorado', 'hex' => '#FFD700'],
            ['name' => 'Plateado', 'hex' => '#C0C0C0'],
        ];

        foreach ($colors as $color) {
            // Buscamos por HEX para no repetir el código de color
            Color::firstOrCreate(
                ['hex' => $color['hex']], 
                ['name' => $color['name']]
            );
        }
    }
}
