<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = [
        'name',
        'hex',
        'is_natural'
    ];

    protected $casts = [
        'is_natural' => 'boolean',
    ];

    public function products(){
        return $this->belongsToMany(Product::class, 'products_colors', 'color_id', 'product_id');
    }

    public function productMovements(){
        return $this->hasMany(ProductMovement::class);
    }
}
