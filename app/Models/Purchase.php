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

    public function getTotalAttribute()
    {
        // Verificación de seguridad: 
        // Si no has cargado los productos con 'with()', evitamos que intente calcular
        // para no generar errores o consultas N+1 inesperadas.
        if (!$this->relationLoaded('products')) {
            return 0; 
        }

        return $this->products->sum(function ($product) {
            // Tu fórmula: (Costo - Descuento) * Cantidad
            $netCost = $product->pivot->cost - $product->pivot->discount;
            return $netCost * $product->pivot->quantity;
        });
    }
}
