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
        'notes',
        'exchange_rate',
        'document'
    ];

    protected $casts = [
        'date' => 'date',
        'exchange_rate' => 'float',
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
        // Verificación de seguridad
        if (!$this->relationLoaded('products')) {
            return 0; 
        }

        return $this->products->sum(function ($product) {
            $cost = $product->pivot->cost;
            $discountPercent = $product->pivot->discount ?? 0; // Por si viene null
            
            // Fórmula: Costo base aplicando el % de descuento
            $netCost = $cost * (1 - ($discountPercent / 100));
            
            return $netCost * $product->pivot->quantity;
        });
    }

    public function getTotalVesAttribute()
    {
        $totalUsd = $this->total;

        // Si no hay total o no hay tasa guardada, devolvemos 0
        if ($totalUsd == 0 || !$this->exchange_rate) {
            return 0;
        }

        // Multiplicamos directo por la tasa que quedó "congelada" en esta compra
        return round($totalUsd * $this->exchange_rate, 2);
    }

    public function getDocumentUrlAttribute()
    {
        return $this->document ? asset('storage/' . $this->document) : null;
    }
}
