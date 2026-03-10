<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'name',
        'allows_decimals'
    ];

    protected $casts = [
        'allows_decimals' => 'boolean',
    ];

    public function material()
    {
        return $this->hasMany(Material::class);
    }
}