<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialCategory extends Model
{
    protected $guarded = ['*'];

    // Una categoría tiene muchas subcategorías (Tipos)
    public function materialTypes()
    {
        return $this->hasMany(MaterialType::class);
    }

    // Opcional pero MUY útil: Obtener todos los materiales de esta categoría directamente
    public function materials()
    {
        return $this->hasManyThrough(Material::class, MaterialType::class);
    }
}