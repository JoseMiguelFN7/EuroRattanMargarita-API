<?php

namespace App\Jobs;

use App\Models\Set;
use App\Events\ReportGenerated;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateSetsPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $search;
    protected $typeId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($search, $typeId, $userId)
    {
        $this->search = $search;
        $this->typeId = $typeId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $query = Set::with([
            'setType',
            'furnitures.materials.materialTypes', 
            'furnitures.labors',
            'furnitures.product.stocks'
        ]);

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'LIKE', "%{$this->search}%")
                  ->orWhere('code', 'LIKE', "%{$this->search}%");
            });
        }

        if ($this->typeId) {
            $query->where('set_types_id', $this->typeId);
        }

        $sets = $query->get();

        // Calculamos los datos derivados
        $sets->each(function ($set) {
            $precios = $set->calcularPrecios();
            $set->pvp_natural = $precios['pvp_natural'];
            $set->pvp_color = $precios['pvp_color'];
            $set->available_colors = $set->calcularColoresDisponibles();
        });

        $pdf = Pdf::loadView('pdf.reports.sets', compact('sets'));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $fileName = 'reporte_juegos_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.pdf';
        $filePath = 'reports_temp/sets/' . $fileName;
        
        Storage::disk('public')->put($filePath, $pdf->output());

        $fileUrl = asset('storage/' . $filePath);
        event(new ReportGenerated($this->userId, $fileUrl, 'Reporte de Juegos'));
    }
}
