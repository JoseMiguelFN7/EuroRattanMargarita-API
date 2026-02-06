<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Set extends Model
{
    protected $fillable = [
        'product_id',
        'profit_per',
        'paint_per',
        'labor_fab_per',
        'set_types_id'
    ];

    public function calcularPrecios()
    {
        $totalInsumos = 0;
        $totalTapiceria = 0;
        $totalManoObra = 0;

        // Verificamos que la relación furnitures esté cargada para evitar errores
        if ($this->relationLoaded('furnitures')) {
            foreach ($this->furnitures as $furniture) {
                
                $qtyInSet = $furniture->pivot->quantity ?? 0;

                // A. Materiales
                if ($furniture->relationLoaded('materials')) {
                    foreach ($furniture->materials as $material) {
                        $costoTotalMaterial = $material->price * $material->pivot->quantity * $qtyInSet;

                        // Clasificación
                        if ($material->materialTypes->contains('name', 'Tapicería')) {
                            $totalTapiceria += $costoTotalMaterial;
                        } 
                        elseif ($material->materialTypes->contains('name', 'Insumo')) {
                            $totalInsumos += $costoTotalMaterial;
                        }
                    }
                }

                // B. Mano de Obra
                if ($furniture->relationLoaded('labors')) {
                    foreach ($furniture->labors as $labor) {
                        // Nota: Usamos daily_pay según tu último snippet
                        $costoLabor = $labor->daily_pay * $labor->pivot->days * $qtyInSet;
                        $totalManoObra += $costoLabor;
                    }
                }
            }
        }

        // --- APLICACIÓN DE FÓRMULAS ---

        // 1. Base Tapicería (aplica % Fabricación)
        $baseTapiceria = $totalTapiceria * (1 + ($this->labor_fab_per / 100));

        // 2. Base Estructura (Insumo + Mano de Obra)
        $baseEstructura = $totalInsumos + $totalManoObra;

        // 3. Base Estructura con Pintura (aplica % Pintura)
        $baseEstructuraPintada = $baseEstructura * (1 + ($this->paint_per / 100));

        // 4. Factor de Ganancia
        $factorGanancia = (1 + ($this->profit_per / 100));

        return [
            'pvp_natural' => round(($baseTapiceria + $baseEstructura) * $factorGanancia, 2),
            'pvp_color'   => round(($baseTapiceria + $baseEstructuraPintada) * $factorGanancia, 2)
        ];
    }

    public function calcularColoresDisponibles()
    {
        $availableColors = [];

        // 1. Obtener lista ÚNICA de nombres de colores presentes en los componentes
        $allColorNames = collect();
        
        // Verificamos que la relación furnitures esté cargada
        if ($this->relationLoaded('furnitures')) {
            foreach ($this->furnitures as $furniture) {
                // Verificamos que stocks (la vista) esté cargada
                if ($furniture->product && $furniture->product->relationLoaded('stocks')) {
                    // Usamos 'color_name' como indicaste
                    $allColorNames = $allColorNames->concat($furniture->product->stocks->pluck('color_name'));
                }
            }
        }
        
        // Filtramos para tener solo nombres únicos (ej: ['Blanco', 'Wengué'])
        $uniqueColors = $allColorNames->unique()->values();

        // 2. Iterar por cada color para calcular el "Cuello de Botella"
        foreach ($uniqueColors as $colorName) {
            
            $maxSetsPossible = 999999; // Iniciamos alto (infinito)
            $colorHex = null;         // Aquí guardaremos el HEX de la columna 'color'

            foreach ($this->furnitures as $furniture) {
                $qtyRequired = $furniture->pivot->quantity; 

                // Buscamos en el stock del mueble el registro que coincida con el NOMBRE del color
                $stockData = $furniture->product->stocks
                                ->where('color_name', $colorName)
                                ->first();

                // Stock actual del mueble en ese color
                $stockAvailable = $stockData ? $stockData->stock : 0;
                
                // Aprovechamos para capturar el HEX de la columna 'color'
                if (!$colorHex && $stockData) {
                    $colorHex = $stockData->color; // <--- AQUÍ USAMOS TU COLUMNA 'color' (HEX)
                }

                // CÁLCULO DEL LIMITANTE
                if ($qtyRequired > 0) {
                    $possibleWithThisFurniture = floor($stockAvailable / $qtyRequired);
                } else {
                    $possibleWithThisFurniture = 999999; 
                }

                // El stock del juego es el stock del componente que menos permita fabricar
                if ($possibleWithThisFurniture < $maxSetsPossible) {
                    $maxSetsPossible = $possibleWithThisFurniture;
                }
            }

            // Si es posible armar al menos 1 juego y obtuvimos el HEX correctamente
            if ($maxSetsPossible > 0 && $maxSetsPossible < 999999 && $colorHex) {
                $availableColors[] = [
                    'name'  => $colorName,      // Columna 'color_name'
                    'hex'   => $colorHex,       // Columna 'color'
                    'stock' => $maxSetsPossible
                ];
            }
        }

        return $availableColors;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function furnitures(){
        return $this->belongsToMany(Furniture::class, 'sets_furnitures', 'set_id', 'furniture_id')
                    ->withPivot('quantity');
    }

    public function setType(){
        return $this->belongsTo(SetType::class, 'set_types_id');
    }
}
