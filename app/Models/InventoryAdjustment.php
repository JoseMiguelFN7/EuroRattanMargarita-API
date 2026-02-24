<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'concept',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'inventory_adjustment_product')
                    ->withPivot('color_id', 'quantity')
                    ->withTimestamps();
    }

    public function movements()
    {
        return $this->morphMany(ProductMovement::class, 'movementable');
    }
}
