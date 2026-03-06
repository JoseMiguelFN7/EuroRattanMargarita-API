<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Commission extends Model
{
    protected $fillable = [
        'code',
        'user_id',
        'order_id',
        'description',
        'status'
    ];

    protected static function booted()
    {
        static::creating(function ($commission) {
            do {
                // Genera un código de 10 caracteres
                $code = Str::random(10); 
            } while (self::where('code', $code)->exists()); // Asegura unicidad

            $commission->code = $code;
        });
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function order() {
        return $this->belongsTo(Order::class);
    }

    public function images() {
        return $this->hasMany(CommissionImage::class);
    }

    public function suggestions() {
        return $this->hasMany(CommissionSuggestion::class);
    }

    public function furnitures() {
        return $this->hasMany(Furniture::class);
    }
}
