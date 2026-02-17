<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear Dólar (Moneda Base)
        $usd = Currency::firstOrCreate(
            ['code' => 'USD'],
            [
                'symbol' => '$',
                'name' => 'Dólar Americano',
                'is_primary' => true,
            ]
        );

        // 2. Crear Bolívar (Moneda Secundaria)
        $ves = Currency::firstOrCreate(
            ['code' => 'VES'],
            [
                'symbol' => 'Bs.',
                'name' => 'Bolívar Digital',
                'is_primary' => false,
            ]
        );
    }
}
