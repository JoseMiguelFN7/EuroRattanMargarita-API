<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buscamos el ID del rol Administrador usando el slug que definiste en el RoleSeeder
        $adminRole = Role::where('slug', 'admin')->first();

        if (!$adminRole) {
            $this->command->error('El rol de administrador no fue encontrado. Por favor, asegúrate de correr el RoleSeeder primero.');
            return;
        }

        // 2. Creamos o actualizamos tu usuario
        User::updateOrCreate(
            ['email' => 'josemiguelf2001@gmail.com'], // Condición de búsqueda (no duplicará si ya existe)
            [
                'name'              => 'José Ferreira',
                'password'          => Hash::make('ERM-Admin123'), // Cifrado con Bcrypt
                'document'          => 'V28315655',
                'cellphone'         => '0424-8423743',
                'address'           => 'Porlamar',
                'role_id'           => $adminRole->id,
                'email_verified_at' => now(), // Te validamos el correo de una vez
            ]
        );

        $this->command->info('Usuario Administrador (José Ferreira) creado/actualizado exitosamente.');
    }
}