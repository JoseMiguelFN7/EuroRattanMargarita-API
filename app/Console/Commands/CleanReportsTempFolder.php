<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanReportsTempFolder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:clean-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia todos los reportes PDF temporales generados por el sistema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Usamos allFiles() para buscar recursivamente en todas las subcarpetas
        $files = Storage::disk('public')->allFiles('reports_temp');
        
        if (!empty($files)) {
            Storage::disk('public')->delete($files);
            $this->info('Se eliminaron ' . count($files) . ' reportes PDF temporales de todas las subcarpetas.');
        } else {
            $this->info('La carpeta de reportes temporales ya estaba vacía.');
        }
    }
}
