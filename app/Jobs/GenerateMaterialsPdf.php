<?php

namespace App\Jobs;

use App\Models\Material;
use App\Events\ReportGenerated;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateMaterialsPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $search;
    protected $userId;
    protected $typeIds;

    public function __construct($search, $typeIds, $userId)
    {
        $this->search = $search;
        $this->typeIds = $typeIds;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');
        \Log::info('Iniciando Job de PDF a las: ' . now()->format('H:i:s.v'));

        $query = Material::with([
            'product:id,name,code,discount',
            'materialTypes', 
            'unit', 
            'product.stocks'
        ])->select('id', 'product_id', 'price');

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'LIKE', "%{$this->search}%")
                  ->orWhere('code', 'LIKE', "%{$this->search}%");
            });
        }

        if (!empty($this->typeIds)) {
            $typeIdsArray = is_array($this->typeIds) ? $this->typeIds : [$this->typeIds];
            
            $query->whereHas('materialTypes', function ($q) use ($typeIdsArray) {
                $q->whereIn('material_types.id', $typeIdsArray);
            });
        }

        $materials = $query->get();

        $materials->each(function ($material) {
            if ($material->product && $material->product->images) {
                $material->product->images->each(function ($image) {
                    $image->url = public_path('storage/' . $image->url); 
                });
            }
        });

        $pdf = Pdf::loadView('pdf.reports.materials', compact('materials'));

        // --- SOLUCIÓN AL PDF CORRUPTO ---
        // Limpiamos cualquier carácter basura o espacio en blanco que se haya 
        // filtrado en el buffer de salida de PHP durante la ejecución.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $fileName = 'reporte_materiales_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.pdf';
        $filePath = 'reports_temp/materials/' . $fileName;
        
        Storage::disk('public')->put($filePath, $pdf->output());

        $fileUrl = asset('storage/' . $filePath);
        \Log::info('PDF guardado, enviando a Reverb a las: ' . now()->format('H:i:s.v'));
        event(new ReportGenerated($this->userId, $fileUrl, 'Reporte de Materiales')); // Descomentar cuando uses Reverb
    }
}