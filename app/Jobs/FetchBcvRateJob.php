<?php

namespace App\Jobs;

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

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Obtener la API Key del archivo .env
        $apiKey = env('DOLAR_API_KEY'); 

        if (!$apiKey) {
            Log::error('ERROR: No se ha configurado DOLAR_API_KEY en el archivo .env');
            return;
        }

        $url = 'https://bcvapi.tech/api/v1/dolar';
        Log::info('Consultando API del BCV...');

        try {
            // 2. HACER LA PETICIÓN
            // withToken() agrega automáticamente el header: "Authorization: Bearer {token}"
            $response = Http::withoutVerifying()->withToken($apiKey)->get($url);

            if ($response->failed()) {
                Log::error('Error al conectar con la API: ' . $response->status());
                return;
            }

            $data = $response->json();

            // Validar que la respuesta tenga lo necesario
            if (!isset($data['tasa']) || !isset($data['fecha'])) {
                Log::error('La estructura del JSON no es la esperada.');
                return;
            }

            // 3. OBTENER LA MONEDA VES
            $vesCurrency = Currency::where('code', 'VES')->first();
            if (!$vesCurrency) {
                Log::error('No se encontró la moneda con código VES en la base de datos.');
                return;
            }

            // 4. PARSEAR LA FECHA (Ej: "Miércoles, 18 Febrero 2026")
            // Usamos Regex para ignorar el día de la semana y extraer solo "18 Febrero 2026"
            // Esto es más seguro que Carbon::parse() porque no depende del idioma del servidor.
            
            $fechaString = $data['fecha'];
            preg_match('/(\d+)\s+(\w+)\s+(\d+)/u', $fechaString, $matches);
            
            if (count($matches) < 4) {
                Log::error("Formato de fecha no reconocido: " . $fechaString);
                return;
            }

            $dia = $matches[1];
            $mesTexto = strtolower($matches[2]); // febrero
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

            // Crear objeto Carbon para las 00:00:00 de ESE día
            $validAt = Carbon::create($anio, $meses[$mesTexto], $dia, 0, 0, 0);

            // 5. GUARDAR O ACTUALIZAR (Lógica Upsert)
            // Buscamos una tasa que coincida en Moneda y Fecha exacta
            $rate = CurrencyExchange::updateOrCreate(
                [
                    'currency_id' => $vesCurrency->id,
                    'valid_at'    => $validAt, 
                ],
                [
                    'rate' => $data['tasa']
                ]
            );

            // Feedback en consola
            if ($rate->wasRecentlyCreated) {
                Log::info("✅ Nueva tasa registrada para el {$validAt->toDateString()}: Bs. {$data['tasa']}");
            } else {
                Log::info("🔄 Tasa actualizada para el {$validAt->toDateString()}: Bs. {$data['tasa']}");
            }

            // Advertencia visual si la fecha es futura
            if ($validAt->isFuture()) {
                Log::warning("⚠️  ATENCIÓN: Esta tasa es para el futuro. El sistema la usará automáticamente cuando llegue la fecha.");
            }

        } catch (\Exception $e) {
            Log::error('Excepción crítica: ' . $e->getMessage());
        }
    }
}
