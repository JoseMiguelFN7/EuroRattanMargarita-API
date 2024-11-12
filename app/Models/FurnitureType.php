<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FurnitureType extends Model
{
    protected $table = 'furniture_types';

    protected $fillable = [
        'name'
    ];

    public function furnitures(){
        return $this->hasMany(Furniture::class);
    }
}
