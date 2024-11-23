<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'price',
        'product_id',
        'unit_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function materialTypes(){
        return $this->belongsToMany(MaterialType::class, 'material_types_materials', 'material_id', 'material_type_id');
    }

    public function unit(){
        return $this->belongsTo(Unit::class);
    }

    public function furnitures(){
        return $this->belongsToMany(Furniture::class, 'furnitures_materials', 'material_id', 'furniture_id');
    }
}
