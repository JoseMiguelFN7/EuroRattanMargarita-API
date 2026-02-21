<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Furniture extends Model
{
    protected $table = 'furnitures';

    protected $fillable = [
        'product_id',
        'profit_per',
        'paint_per',
        'labor_fab_per',
        'furniture_type_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function furnitureType(){
        return $this->belongsTo(FurnitureType::class);
    }

    public function materials(){
        return $this->belongsToMany(Material::class, 'furnitures_materials', 'furniture_id', 'material_id')
                    ->withPivot('quantity', 'color_id');
    }

    public function labors(){
        return $this->belongsToMany(Labor::class, 'furnitures_labors', 'furniture_id', 'labor_id')
                    ->withPivot('days');
    }

    public function sets(){
        return $this->belongsToMany(Set::class, 'sets_furnitures', 'furniture_id', 'set_id')
                    ->withPivot('quantity');
    }

    public function calcularPrecios()
    {
        $insumosCost = $this->materials->filter(function ($material) {
            return $material->materialTypes->contains('name', 'Insumo');
        })->sum(function ($material) {
            return $material->pivot->quantity * $material->price;
        });

        $tapiceriaCost = $this->materials->filter(function ($material) {
            return $material->materialTypes->contains('name', 'Tapicería');
        })->sum(function ($material) {
            return $material->pivot->quantity * $material->price;
        });

        $manoObraCost = $this->labors->sum(function ($labor) {
            return $labor->pivot->days * $labor->daily_pay;
        });

        $profitPer = $this->profit_per ?? 0;
        $paintPer = $this->paint_per ?? 0;
        $laborFabPer = $this->labor_fab_per ?? 0;
        $discount = $this->product->discount ?? 0;

        $pvpNatural = (
            ($insumosCost + $manoObraCost + $tapiceriaCost * (1 + $laborFabPer / 100))
            * (1 + $profitPer / 100)
        ) * (1 - $discount / 100);

        $pvpColor = (
            (
                ($insumosCost + $manoObraCost) * (1 + $paintPer / 100)
                + ($tapiceriaCost * (1 + $laborFabPer / 100))
            ) * (1 + $profitPer / 100)
        ) * (1 - $discount / 100);

        return [
            'insumos' => round($insumosCost, 2),
            'tapiceria' => round($tapiceriaCost, 2),
            'mano_obra' => round($manoObraCost, 2),
            'pvp_natural' => round($pvpNatural, 2),
            'pvp_color' => round($pvpColor, 2),
        ];
    }
}