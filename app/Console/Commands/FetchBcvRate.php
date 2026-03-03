<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\FetchBcvRateJob;

class FetchBcvRate extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     */
    protected $signature = 'bcv:fetch';

    /**
     * La descripción del comando.
     */
    protected $description = 'Obtiene la tasa oficial del BCV y la programa para su fecha de validez';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $this->info('Enviando la petición del BCV a la cola de trabajos en segundo plano...');
        FetchBcvRateJob::dispatch();
        $this->info('¡Trabajo encolado exitosamente!');
    }
}