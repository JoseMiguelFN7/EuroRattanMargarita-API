<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanChatTempFolder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:clean-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia todas las imágenes temporales enviadas al asesor IA de Euro Rattan';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Obtenemos todos los archivos dentro de la carpeta
        $files = Storage::disk('public')->files('chat_temp');
        
        // 2. Si hay archivos, los eliminamos todos de golpe
        if (!empty($files)) {
            Storage::disk('public')->delete($files);
            $this->info('Se eliminaron ' . count($files) . ' archivos temporales del chat.');
        } else {
            $this->info('La carpeta temporal ya estaba vacía.');
        }
    }
}
