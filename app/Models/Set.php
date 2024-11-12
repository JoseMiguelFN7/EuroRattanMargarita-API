<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Set extends Model
{
    protected $fillable = [
        'product_id',
        'profit_per',
        'paint_per',
        'labor_fab_per',
        'setType_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function furnitures(){
        return $this->belongsToMany(Furniture::class, 'sets_furnitures', 'set_id', 'furniture_id');
    }

    public function setType(){
        return $this->belongsTo(SetType::class);
    }
}
