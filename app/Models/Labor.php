<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Labor extends Model
{
    protected $fillable = [
        'name',
        'daily_pay'
    ];

    public function furnitures(){
        return $this->belongsToMany(Furniture::class, 'furnitures_labors', 'labor_id', 'furniture_id');
    }
}