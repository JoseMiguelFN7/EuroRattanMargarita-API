<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetType extends Model
{
    protected $fillable = [
        'name'
    ];

    public function sets(){
        return $this->hasMany(Set::class);
    }
}
