<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sell',
        'discount'
    ];

    public function material(){
        return $this->hasOne(Material::class);
    }

    public function furniture(){
        return $this->hasOne(Furniture::class);
    }

    public function set(){
        return $this->hasOne(Set::class);
    }

    public function productMovements(){
        return $this->hasMany(ProductMovement::class);
    }

    public function orders(){
        return $this->belongsToMany(Order::class, 'receipts_products', 'product_id', 'receipt_id')
                    ->withPivot('quantity', 'price', 'discount');
    }

    public function images(){
        return $this->hasMany(ProductImage::class);
    }

    public function colors(){
        return $this->belongsToMany(Color::class, 'products_colors', 'product_id', 'color_id');
    }

    public function stocks(){
        return $this->hasMany(ProductStockView::class, 'productID', 'id');
    }
}