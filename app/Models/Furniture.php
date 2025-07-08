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
                    ->withPivot('quantity');
    }

    public function labors(){
        return $this->belongsToMany(Labor::class, 'furnitures_labors', 'furniture_id', 'labor_id')
                    ->withPivot('days');
    }

    public function sets(){
        return $this->belongsToMany(Set::class, 'sets_furnitures', 'furniture_id', 'set_id')
                    ->withPivot('quantity');
    }
}