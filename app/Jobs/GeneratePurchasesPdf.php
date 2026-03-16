<?php

namespace App\Jobs;

use App\Models\Purchase;
use App\Events\ReportGenerated;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeneratePurchasesPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $search;
    protected $startDate;
    protected $endDate;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($search, $startDate, $endDate, $userId)
    {
        $this->search = $search;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $query = Purchase::with(['supplier', 'products']);

        if (!empty($this->search)) {
            $searchTerm = $this->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('code', 'like', "%{$searchTerm}%")
                  ->orWhereHas('supplier', function ($supplierQuery) use ($searchTerm) {
                      $supplierQuery->where('name', 'like', "%{$searchTerm}%")
                                    ->orWhere('rif', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // Usamos la columna 'date' igual que en tu index
        if (!empty($this->startDate)) {
            $query->where('date', '>=', $this->startDate);
        }

        if (!empty($this->endDate)) {
            $query->where('date', '<=', $this->endDate);
        }

        $purchases = $query->orderBy('date', 'desc')->get();

        // Ejecutamos los accessors para tener los totales en la vista
        $purchases->each(function ($purchase) {
            $purchase->append(['total', 'total_ves']);
        });

        $pdf = Pdf::loadView('pdf.reports.purchases', compact('purchases'));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $fileName = 'reporte_compras_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.pdf';
        $filePath = 'reports_temp/purchases/' . $fileName;
        
        Storage::disk('public')->put($filePath, $pdf->output());

        $fileUrl = asset('storage/' . $filePath);
        event(new ReportGenerated($this->userId, $fileUrl, 'Reporte de Compras'));
    }
}
