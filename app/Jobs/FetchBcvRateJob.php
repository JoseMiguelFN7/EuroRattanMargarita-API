<?php

namespace App\Jobs;

use App\Events\BcvRateUpdated; // <-- Importamos el evento
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\Currency;
use App\Models\CurrencyExchange;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FetchBcvRateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300];
    
    protected $userId; // <-- NUEVA PROPIEDAD

    /**
     * Create a new job instance.
     */
    public function __construct($userId = null) // <-- Acepta null para el cronjob
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiKey = env('DOLAR_API_KEY'); 

        if (!$apiKey) {
            Log::error('ERROR: No se ha configurado DOLAR_API_KEY en el archivo .env');
            if ($this->userId) event(new BcvRateUpdated($this->userId, null, 'Error: API Key no configurada', true));
            return;
        }

        $url = 'https://bcvapi.tech/api/v1/dolar';
        Log::info('Consultando API del BCV...');

        try {
            $response = Http::withoutVerifying()->withToken($apiKey)->get($url);

            if ($response->failed()) {
                Log::error('Error al conectar con la API: ' . $response->status());
                if ($this->userId) event(new BcvRateUpdated($this->userId, null, 'Error al conectar con el servidor del BCV', true));
                return;
            }

            $data = $response->json();

            if (!isset($data['tasa']) || !isset($data['fecha'])) {
                Log::error('La estructura del JSON no es la esperada.');
                if ($this->userId) event(new BcvRateUpdated($this->userId, null, 'La API del BCV devolvió datos inválidos', true));
                return;
            }

            $vesCurrency = Currency::where('code', 'VES')->first();
            if (!$vesCurrency) {
                Log::error('No se encontró la moneda con código VES en la base de datos.');
                return;
            }

            $fechaString = $data['fecha'];
            preg_match('/(\d+)\s+(\w+)\s+(\d+)/u', $fechaString, $matches);
            
            if (count($matches) < 4) {
                Log::error("Formato de fecha no reconocido: " . $fechaString);
                return;
            }

            $dia = $matches[1];
            $mesTexto = strtolower($matches[2]); 
            $anio = $matches[3];

            $meses = [
                'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
                'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
                'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'
            ];

            if (!isset($meses[$mesTexto])) {
                Log::error("Mes no válido: " . $mesTexto);
                return;
            }

            $validAt = Carbon::create($anio, $meses[$mesTexto], $dia, 0, 0, 0);

            $rate = CurrencyExchange::updateOrCreate(
                [
                    'currency_id' => $vesCurrency->id,
                    'valid_at'    => $validAt, 
                ],
                [
                    'rate' => $data['tasa']
                ]
            );

            if ($rate->wasRecentlyCreated) {
                Log::info("✅ Nueva tasa registrada para el {$validAt->toDateString()}: Bs. {$data['tasa']}");
            } else {
                Log::info("🔄 Tasa actualizada para el {$validAt->toDateString()}: Bs. {$data['tasa']}");
            }

            // --- NUEVO: AVISAR AL USUARIO POR WEBSOCKETS ---
            if ($this->userId) {
                event(new BcvRateUpdated($this->userId, $data['tasa'], "Tasa actualizada: Bs. {$data['tasa']}"));
            }

        } catch (\Exception $e) {
            Log::error('Excepción crítica: ' . $e->getMessage());
            if ($this->userId) {
                event(new BcvRateUpdated($this->userId, null, 'Error interno al procesar la tasa', true));
            }
        }
    }
}