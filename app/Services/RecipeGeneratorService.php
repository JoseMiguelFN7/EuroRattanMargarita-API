<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Labor;
use App\Models\Furniture;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecipeGeneratorService
{
    protected $apiKey;
    protected $model = 'gemini-2.5-flash-lite'; 
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    public function suggestRecipe(string $userDescription, int $furnitureTypeId)
    {
        // ------------------------------------------------------------------
        // 1. CONTEXTO DE MATERIALES (Optimizado con Pipes para ahorrar tokens)
        // ------------------------------------------------------------------        
        $materialsContext = Material::with(['product', 'unit', 'materialTypes'])
            ->get()
            ->map(function ($m) {
                $name  = $m->product->name ?? 'N/A';
                $unit  = $m->unit->name ?? 'u';
                $types = $m->materialTypes->pluck('name')->implode(',');
                
                // Limpieza de descripción para no romper el formato de tubería (|)
                $desc = $m->product->description ?? '';
                $desc = str_replace(["\n", "\r", "|"], " ", $desc);
                $desc = Str::limit($desc, 80); 

                return sprintf("%d|%s|%s|%s|%s", $m->id, $name, $unit, $types, $desc);
            })->implode("\n");

        // ------------------------------------------------------------------
        // 2. CONTEXTO DE MANO DE OBRA
        // ------------------------------------------------------------------
        $laborsContext = Labor::all()
            ->map(fn($l) => sprintf("%d|%s", $l->id, $l->name))
            ->implode("\n");

        // ------------------------------------------------------------------
        // 3. REFERENCIAS (Aprender de muebles existentes)
        // ------------------------------------------------------------------
        $references = $this->getReferences($furnitureTypeId);

        // ------------------------------------------------------------------
        // 4. CONSTRUCCIÓN DEL PROMPT
        // ------------------------------------------------------------------
        $prompt = <<<EOT
Actúa como el Jefe de Producción de mi fábrica de muebles.
Genera una "Hoja de Fabricación" técnica para el siguiente pedido: "{$userDescription}".

INVENTARIO DISPONIBLE (Formato: ID|NOMBRE|UNIDAD|TIPOS|DESCRIPCION):
{$materialsContext}

ROLES DE MANO DE OBRA (Formato: ID|NOMBRE):
{$laborsContext}

{$references}

REGLAS:
1. Usa EXCLUSIVAMENTE los IDs proporcionados.
2. Define cantidades realistas para fabricar 1 unidad.
3. El campo 'days' en labor puede ser decimal (ej: 0.5 para medio día).
4. La respuesta debe ser ESTRICTAMENTE un objeto JSON.

FORMATO JSON:
{
    "suggested_name": "Nombre comercial",
    "description": "Explicación del diseño imaginado",
    "materials": [
        { "material_id": 1, "quantity": 0.5, "reason": "explicación" }
    ],
    "labors": [
        { "labor_id": 1, "days": 0.5, "reason": "explicación" }
    ]
}
EOT;

        // ------------------------------------------------------------------
        // 5. LLAMADA A LA API (Con Timeout extendido)
        // ------------------------------------------------------------------
        try {
            $response = Http::withoutVerifying()
                ->timeout(60)         // 60 segundos de espera total
                ->connectTimeout(30)  // 30 segundos para conectar
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'temperature' => 0.4,
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Error Gemini API', ['body' => $response->body()]);
                throw new \Exception('La IA no pudo procesar la solicitud.');
            }

            $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'];
            return json_decode(str_replace(['```json', '```'], '', $rawText), true);

        } catch (\Exception $e) {
            Log::error("RecipeGenerator Fail: " . $e->getMessage());
            throw $e;
        }

        return $prompt;
    }

    private function getReferences(int $furnitureTypeId)
    {
        // Buscamos muebles que pertenezcan a la MISMA categoría
        $examples = Furniture::where('furniture_type_id', $furnitureTypeId)
            ->has('materials') // Solo si ya tienen receta
            ->with(['product', 'materials.product', 'labors'])
            ->latest()
            ->take(3) // Traemos las 3 más recientes de esa categoría
            ->get();

        if ($examples->isEmpty()) return "";

        $text = "REFERENCIAS DE NUESTRAS RECETAS PARA ESTA CATEGORÍA:\n";
        foreach ($examples as $f) {
            $mats = $f->materials->map(fn($m) => "- {$m->product->name}: {$m->pivot->quantity}")->implode(", ");
            $labs = $f->labors->map(fn($l) => "- {$l->name}: {$l->pivot->days}d")->implode(", ");
            
            $text .= "PRODUCTO: {$f->product->name} | Materiales: [{$mats}] | Mano de Obra: [{$labs}]\n";
        }

        return $text;
    }
}