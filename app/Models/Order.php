<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'exchange_rate',
        'notes',
        'code'
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

    public function products()
    {
        return $this->belongsToMany(
            Product::class, 
            'order_items',
            'order_id',
            'product_id'
        )
        ->withPivot('quantity', 'price', 'discount', 'variant_id')
        ->withTimestamps();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
