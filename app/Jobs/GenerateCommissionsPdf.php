<?php

namespace App\Jobs;

use App\Models\Commission;
use App\Events\ReportGenerated;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateCommissionsPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $search;
    protected $status;
    protected $startDate;
    protected $endDate;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($search, $status, $startDate, $endDate, $userId)
    {
        $this->search = $search;
        $this->status = $status;
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

        $query = Commission::with([
            'user:id,name,email',
            'order:id,code'
        ])
        ->when(!empty($this->search), function ($query) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($qUser) use ($search) {
                      $qUser->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('order', function ($qOrder) use ($search) {
                      $qOrder->where('code', 'like', '%' . $search . '%');
                  });
            });
        })
        ->when(!empty($this->status), function ($query) {
            $query->where('status', $this->status);
        })
        ->when(!empty($this->startDate), function ($query) {
            $query->whereDate('created_at', '>=', $this->startDate);
        })
        ->when(!empty($this->endDate), function ($query) {
            $query->whereDate('created_at', '<=', $this->endDate);
        });

        $commissions = $query->orderBy('created_at', 'desc')->get();

        $pdf = Pdf::loadView('pdf.reports.commissions', compact('commissions'));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $fileName = 'reporte_encargos_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.pdf';
        $filePath = 'reports_temp/commissions/' . $fileName;
        
        Storage::disk('public')->put($filePath, $pdf->output());

        $fileUrl = asset('storage/' . $filePath);
        event(new ReportGenerated($this->userId, $fileUrl, 'Reporte de Encargos'));
    }
}
