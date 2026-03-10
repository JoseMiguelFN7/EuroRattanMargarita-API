<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name'
    ];

    // --- NUEVO MÉTODO BLINDADO ---
    public static function getClientId()
    {
        // Ahora busca por la columna inmutable
        return self::where('slug', 'client')->firstOrFail()->id;
    }

    // Opcional: Puedes agregar uno para el admin si lo llegas a necesitar en el back
    public static function getAdminId()
    {
        return self::where('slug', 'admin')->firstOrFail()->id;
    }

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }
}
