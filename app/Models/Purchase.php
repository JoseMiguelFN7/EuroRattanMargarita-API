<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'code',
        'date',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Relación: Una compra pertenece a un proveedor
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // Relación N:N con Productos
    public function products()
    {
        return $this->belongsToMany(Product::class, 'purchase_product')
                    ->withPivot(['quantity', 'cost', 'discount', 'color_id']) // Campos extra de la tabla intermedia
                    ->withTimestamps();
    }

    // Relación Polimórfica con Movimientos
    public function movements()
    {
        return $this->morphMany(ProductMovement::class, 'movementable');
    }
}
