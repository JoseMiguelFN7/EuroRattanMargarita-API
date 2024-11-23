<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Receipt extends Model
{
    protected $fillable = [
        'user_id'
    ];

    protected static function booted()
    {
        static::creating(function ($factura) {
            do {
                $code = Str::random(10); // Genera un código de 10 caracteres
            } while (self::where('code', $code)->exists()); // Asegura unicidad

            $factura->code = $code;
        });
    }

    public function products(){
        return $this->belongsToMany(Product::class, 'receipts_products', 'receipt_id', 'product_id')
                    ->withPivot('quantity', 'price', 'discount');
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
}
