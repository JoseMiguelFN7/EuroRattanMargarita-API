<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'name'
    ];

    public function material()
    {
        return $this->hasMany(Material::class);
    }
}