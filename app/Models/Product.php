<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'code',
        'sell',
        'image'
    ];

    public function material(){
        return $this->hasOne(Material::class);
    }

    public function furniture(){
        return $this->hasOne(Furniture::class);
    }
}