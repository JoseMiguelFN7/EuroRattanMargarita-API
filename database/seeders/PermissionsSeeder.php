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

            // 2. PRODUCTOS
            'Productos' => [
                ['slug' => 'products.buy',   'name' => 'Comprar Productos'],
            ],

            // 3. MUEBLES
            'Muebles' => [
                ['slug' => 'furnitures.view',   'name' => 'Ver Muebles'],
                ['slug' => 'furnitures.create', 'name' => 'Crear Muebles'],
                ['slug' => 'furnitures.edit',   'name' => 'Editar Muebles'],
                ['slug' => 'furnitures.delete', 'name' => 'Eliminar Muebles'],
                ['slug' => 'furnitures.stock.add', 'name' => 'Adicionar Stocks de Muebles'],
                ['slug' => 'furnitures.parameters.view', 'name' => 'Ver Parámetros de Muebles'],
                ['slug' => 'furnitures.parameters.create', 'name' => 'Crear Parámetros de Muebles'],
                ['slug' => 'furnitures.parameters.edit', 'name' => 'Editar Parámetros de Muebles'],
                ['slug' => 'furnitures.parameters.delete', 'name' => 'Eliminar Parámetros de Muebles'],
            ],

            // 4. MATERIALES (Insumos/Tapicería)
            'Materiales' => [
                ['slug' => 'materials.view',   'name' => 'Ver Materiales'],
                ['slug' => 'materials.create', 'name' => 'Crear Materiales'],
                ['slug' => 'materials.edit',   'name' => 'Editar Materiales'],
                ['slug' => 'materials.delete', 'name' => 'Eliminar Materiales'],
                ['slug' => 'materials.parameters.view', 'name' => 'Ver Parámetros de Materiales'],
                ['slug' => 'materials.parameters.create', 'name' => 'Crear Parámetros de Materiales'],
                ['slug' => 'materials.parameters.edit', 'name' => 'Editar Parámetros de Materiales'],
                ['slug' => 'materials.parameters.delete', 'name' => 'Eliminar Parámetros de Materiales'],
            ],

            // 5. JUEGOS DE MUEBLES
            'Juegos de Muebles' => [
                ['slug' => 'sets.view',   'name' => 'Ver Juegos'],
                ['slug' => 'sets.create', 'name' => 'Crear Juegos'],
                ['slug' => 'sets.edit',   'name' => 'Editar Juegos'],
                ['slug' => 'sets.delete', 'name' => 'Eliminar Juegos'],
                ['slug' => 'sets.parameters.view', 'name' => 'Ver Parámetros de Juegos'],
                ['slug' => 'sets.parameters.create', 'name' => 'Crear Parámetros de Juegos'],
                ['slug' => 'sets.parameters.edit', 'name' => 'Editar Parámetros de Juegos'],
                ['slug' => 'sets.parameters.delete', 'name' => 'Eliminar Parámetros de Juegos'],
            ],

            // 6. USUARIOS (Gestión de Staff y Clientes)
            'Usuarios' => [
                ['slug' => 'users.view',   'name' => 'Ver Usuarios'],
                ['slug' => 'users.create', 'name' => 'Crear Usuarios'],
                ['slug' => 'users.edit',   'name' => 'Editar Usuarios'],
                ['slug' => 'users.delete', 'name' => 'Eliminar Usuarios'],
            ],

            // 7. ROLES Y PERMISOS
            'Roles y Permisos' => [
                ['slug' => 'roles.view',        'name' => 'Ver Roles'],
                ['slug' => 'roles.create',      'name' => 'Crear Roles'],
                ['slug' => 'roles.edit',        'name' => 'Editar Roles'],
                ['slug' => 'roles.delete',      'name' => 'Eliminar Roles'],
                ['slug' => 'permissions.view',  'name' => 'Ver Permisos'],
                ['slug' => 'permissions.asign', 'name' => 'Asignar Permisos'],
            ],

            // 8. COMPRAS
            'Compras' => [
                ['slug' => 'purchases.view',   'name' => 'Ver Compras'],
                ['slug' => 'purchases.create', 'name' => 'Crear Compras'],
                ['slug' => 'purchases.edit',   'name' => 'Editar Compras'],
                ['slug' => 'purchases.delete', 'name' => 'Eliminar Compras'],
            ],

            // 9. PROVEEDORES
            'Proveedores' => [
                ['slug' => 'suppliers.view',   'name' => 'Ver Proveedores'],
                ['slug' => 'suppliers.create', 'name' => 'Crear Proveedores'],
                ['slug' => 'suppliers.edit',   'name' => 'Editar Proveedores'],
                ['slug' => 'suppliers.delete', 'name' => 'Eliminar Proveedores'],
            ],

            // 10. VENTAS
            'Ventas' => [
                ['slug' => 'sales.view',   'name' => 'Ver Ventas'],
                ['slug' => 'sales.cancel',   'name' => 'Cancelar Ventas'],
            ],

            // 11. MÉTODOS DE PAGO
            'Métodos de pago' => [
                ['slug' => 'payment_methods.view',   'name' => 'Ver Métodos de Pago'],
                ['slug' => 'payment_methods.create',   'name' => 'Crear Métodos de Pago'],
                ['slug' => 'payment_methods.edit',   'name' => 'Editar Métodos de Pago'],
                ['slug' => 'payment_methods.delete',   'name' => 'Eliminar Métodos de Pago'],
            ],

            // 12. PAGOS
            'Pagos' => [
                ['slug' => 'payments.view',   'name' => 'Ver Pagos'],
                ['slug' => 'payments.verify',   'name' => 'Verificar Pagos'],
            ],

            // 13. FACTURAS
            'Facturas' => [
                ['slug' => 'invoices.view',   'name' => 'Ver Facturas'],
            ],

            // 14. CONFIGURACIONES
            'Configuraciones' => [
                ['slug' => 'banner_images.view',   'name' => 'Ver Imágenes del Banner'],
                ['slug' => 'banner_images.create',   'name' => 'Crear Imágenes del Banner'],
                ['slug' => 'banner_images.edit',   'name' => 'Editar Imágenes del Banner'],
                ['slug' => 'banner_images.delete',   'name' => 'Eliminar Imágenes del Banner'],
            ]
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
