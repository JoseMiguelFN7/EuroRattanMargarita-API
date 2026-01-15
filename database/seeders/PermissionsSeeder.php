<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lista Maestra de Permisos
        // Agrupada por módulos para que sea fácil de leer y mantener.
        $permissions = [
            // 1. CONTROL DE ACCESO (La llave del Dashboard)
            'Acceso' => [
                ['slug' => 'view.admin_dashboard', 'name' => 'Acceder al Panel Administrativo'],
                ['slug' => 'view.client_dashboard', 'name' => 'Acceder al Panel de Clientes'],
            ],

            // 2. MUEBLES
            'Muebles' => [
                ['slug' => 'furniture.view',   'name' => 'Ver Muebles'],
                ['slug' => 'furniture.create', 'name' => 'Crear Muebles'],
                ['slug' => 'furniture.edit',   'name' => 'Editar Muebles'],
                ['slug' => 'furniture.delete', 'name' => 'Eliminar Muebles'],
            ],

            // 3. MATERIALES (Insumos/Tapicería)
            'Materiales' => [
                ['slug' => 'materials.view',   'name' => 'Ver Materiales'],
                ['slug' => 'materials.create', 'name' => 'Crear Materiales'],
                ['slug' => 'materials.edit',   'name' => 'Editar Materiales'],
                ['slug' => 'materials.delete', 'name' => 'Eliminar Materiales'],
            ],

            // 4. USUARIOS (Gestión de Staff y Clientes)
            'Usuarios' => [
                ['slug' => 'users.view',   'name' => 'Ver Usuarios'],
                ['slug' => 'users.create', 'name' => 'Crear Usuarios'],
                ['slug' => 'users.edit',   'name' => 'Editar Usuarios'],
                ['slug' => 'users.delete', 'name' => 'Eliminar Usuarios'],
            ],

            // 5. ROLES Y PERMISOS
            'Roles y Permisos' => [
                ['slug' => 'roles.view',        'name' => 'Ver Roles'],
                ['slug' => 'roles.create',      'name' => 'Crear Roles'],
                ['slug' => 'roles.edit',        'name' => 'Editar Roles'],
                ['slug' => 'roles.delete',      'name' => 'Eliminar Roles'],
                ['slug' => 'permissions.view',  'name' => 'Ver Permisos'],
                ['slug' => 'permissions.asign', 'name' => 'Asignar Permisos'],
            ],
        ];

        // Lógica de Creación / Actualización
        foreach ($permissions as $module => $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                Permission::updateOrCreate(
                    ['slug' => $permission['slug']], // Buscamos por el Slug único
                    ['name' => $permission['name']]  // Actualizamos el nombre si cambió
                );
            }
        }
        
        $this->command->info('Tabla de permisos actualizada correctamente.');
    }
}
