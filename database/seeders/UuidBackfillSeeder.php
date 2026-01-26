<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class UuidBackfillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buscamos usuarios que NO tengan UUID
        $usersCount = User::whereNull('uuid')->count();
        
        if ($usersCount === 0) {
            $this->command->info('Todos los usuarios ya tienen UUID. No hay nada que hacer.');
            return;
        }

        $this->command->info("Generando UUIDs para {$usersCount} usuarios...");

        // Usamos chunkById para no saturar la memoria si tienes miles de usuarios
        User::whereNull('uuid')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $user->uuid = (string) Str::uuid();
                $user->save();
            }
        });

        $this->command->info('¡UUIDs generados correctamente!');
    }
}
