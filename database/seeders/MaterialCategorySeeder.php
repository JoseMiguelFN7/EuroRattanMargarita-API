<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear o actualizar las categorías principales
        DB::table('material_categories')->updateOrInsert(
            ['name' => 'Estructural'],
            ['updated_at' => now()]
        );

        DB::table('material_categories')->updateOrInsert(
            ['name' => 'Tapicería'],
            ['updated_at' => now()]
        );

        // 2. Obtener los IDs de las categorías
        $estructuralId = DB::table('material_categories')->where('name', 'Estructural')->value('id');
        $tapiceriaId   = DB::table('material_categories')->where('name', 'Tapicería')->value('id');

        // 3. Listas de subcategorías
        $tiposEstructurales = [
            'Pegamentos', 
            'Grapas', 
            'Clavos', 
            'Tornillos', 
            'Esterilla',
            'Bambu',
            'Madera',
            'Fórmicas',
            'Lijas',
            'Pintura', 
            'Barniz',
            'Vidrio',
        ];
        
        $tiposTapiceria = [
            'Relleno', 
            'Tela', 
            'Hilo', 
            'Espuma', 
            'Cuerina',
            'Cierre',
            'Velcro',
            'Botones',
            'Grapas',
            'Guata',
            'Pegamentos', 
            'Vivos'
        ];

        // 4. Enlazar (crear/actualizar) las subcategorías Estructurales
        foreach ($tiposEstructurales as $tipo) {
            DB::table('material_types')->updateOrInsert(
                [
                    'name' => $tipo, 
                    'material_category_id' => $estructuralId
                ], 
                [
                    'updated_at' => now()
                ]
            );
        }

        // 5. Enlazar (crear/actualizar) las subcategorías de Tapicería
        foreach ($tiposTapiceria as $tipo) {
            DB::table('material_types')->updateOrInsert(
                [
                    'name' => $tipo, 
                    'material_category_id' => $tapiceriaId
                ], 
                [
                    'updated_at' => now()
                ]
            );
        }
    }
}