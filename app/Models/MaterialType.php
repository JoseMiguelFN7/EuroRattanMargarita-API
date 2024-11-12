<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialType extends Model
{
    protected $fillable = [
        'name'
    ];

    public function materials(){
        return $this->belongsToMany(Material::class, 'material_types_materials', 'material_type_id', 'material_id');
    }
}