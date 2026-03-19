<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialType extends Model
{
    protected $fillable = [
        'name',
        'material_category_id'
    ];

    public function category()
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function materials(){
        return $this->hasMany(Material::class);
    }
}