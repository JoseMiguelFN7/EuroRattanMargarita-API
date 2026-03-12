<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- 1. CREACIÓN DE ROLES ---
        // Usamos firstOrCreate para buscar por nombre y crear con slug si no existe
        $admin = Role::firstOrCreate(
            ['name' => 'Administrador'], 
            ['slug' => 'admin']
        );

        $client = Role::firstOrCreate(
            ['name' => 'Cliente'], 
            ['slug' => 'client']
        );

        $asesor = Role::firstOrCreate(
            ['name' => 'Asesor'], 
            ['slug' => null]
        );

        $coordinador = Role::firstOrCreate(
            ['name' => 'Coordinador de Inventario'], 
            ['slug' => null]
        );

        // --- 2. ASIGNACIÓN DE PERMISOS ---

        // A. Administrador: Todos los permisos existentes
        $allPermissions = Permission::pluck('id');
        $admin->permissions()->sync($allPermissions);

        // B. Cliente
        $clientPermissions = Permission::whereIn('slug', [
            'view.client_dashboard',
            'products.buy',
            'commissions.create'
        ])->pluck('id');
        $client->permissions()->sync($clientPermissions);

        // C. Asesor
        $asesorPermissions = Permission::whereIn('slug', [
            'view.admin_dashboard',
            'furnitures.view',
            'materials.view',
            'sets.view',
            'sales.view',
            'sales.cancel',
            'payment_methods.view',
            'payments.view',
            'payments.verify',
            'invoices.view',
            'commissions.view',
            'commissions.reject',
            'commissions.suggestions.create',
            'commissions.quotations.view',
            'commissions.quotations.confirm'
        ])->pluck('id');
        $asesor->permissions()->sync($asesorPermissions);

        // D. Coordinador de Inventario
        $coordinadorPermissions = Permission::whereIn('slug', [
            'view.admin_dashboard',
            'products.movements.view',
            'products.inventory_adjustments.view',
            'products.inventory_adjustments.create',
            'products.inventory_adjustments.edit',
            'products.inventory_adjustments.delete',
            'furnitures.view',
            'furnitures.create',
            'furnitures.edit',
            'furnitures.delete',
            'furnitures.stock.add',
            'furnitures.parameters.view',
            'furnitures.parameters.create',
            'furnitures.parameters.edit',
            'furnitures.parameters.delete',
            'materials.view',
            'materials.create',
            'materials.edit',
            'materials.delete',
            'materials.costs.view',
            'materials.parameters.view',
            'materials.parameters.create',
            'materials.parameters.edit',
            'materials.parameters.delete',
            'sets.view',
            'sets.create',
            'sets.edit',
            'sets.delete',
            'sets.parameters.view',
            'sets.parameters.create',
            'sets.parameters.edit',
            'sets.parameters.delete',
            'purchases.view',
            'purchases.create',
            'purchases.edit',
            'purchases.delete',
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete'
        ])->pluck('id');
        $coordinador->permissions()->sync($coordinadorPermissions);

        $this->command->info('Roles creados y permisos asignados correctamente.');
    }
}