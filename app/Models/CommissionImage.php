<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CommissionImage extends Model
{
    protected $fillable = [
        'commission_id',
        'image_path'
    ];

    // Aprovechamos de ocultar la basura de una vez aquí
    protected $hidden = [
        'commission_id',
        'created_at',
        'updated_at'
    ];

    /**
     * ACCESOR: Intercepta 'image_path' y le concatena el dominio del servidor
     */
    protected function imagePath(): Attribute
    {
        return Attribute::make(
            // asset() tomará tu APP_URL del .env y le sumará 'storage/'
            get: fn ($value) => asset('storage/' . $value)
        );
    }

    public function commission() {
        return $this->belongsTo(Commission::class);
    }
}