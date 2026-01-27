<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockView extends Model
{
    protected $table = 'product_stocks';

    public $timestamps = false;

    protected $guarded = ['*'];
}
