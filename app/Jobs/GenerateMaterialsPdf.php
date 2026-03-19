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
    protected $typeId;
    protected $categoryId;
    protected $userId;

    public function __construct($search, $typeId, $categoryId, $userId)
    {
        $this->search = $search;
        $this->typeId = $typeId;
        $this->categoryId = $categoryId;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $query = Material::with([
            'product:id,name,code,discount',
            'materialType.category', 
            'unit', 
            'product.stocks'
        ])->select('id', 'product_id', 'material_type_id', 'price');

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'LIKE', "%{$this->search}%")
                  ->orWhere('code', 'LIKE', "%{$this->search}%");
            });
        }

        if (!empty($this->typeId)) {
            $query->where('material_type_id', $this->typeId);
        } elseif (!empty($this->categoryId)) {
            $query->whereHas('materialType', function ($q) {
                $q->where('material_category_id', $this->categoryId);
            });
        }

        $materials = $query->get();

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
        event(new ReportGenerated($this->userId, $fileUrl, 'Reporte de Materiales'));
    }
}