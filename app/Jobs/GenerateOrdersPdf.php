<?php

namespace App\Jobs;

use App\Models\Order;
use App\Events\ReportGenerated;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateOrdersPdf implements ShouldQueue
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

        $query = Order::with(['user:id,name,email', 'products']);

        // Filtro de Estado
        if ($this->status) {
            $query->where('status', $this->status);
        }

        // Filtro Abierto
        if ($this->search) {
            $searchTerm = $this->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('code', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'like', '%' . $searchTerm . '%')
                                ->orWhere('email', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Filtros de Fecha
        if ($this->startDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $query->where('created_at', '>=', $start);
        }

        if ($this->endDate) {
            $end = Carbon::parse($this->endDate)->endOfDay();
            $query->where('created_at', '<=', $end);
        }

        // Obtenemos las órdenes ordenadas
        $orders = $query->orderBy('created_at', 'desc')->get();

        // Calculamos los totales para cada orden
        $orders->each(function ($order) {
            $subtotalCalculated = $order->products->sum(function ($product) {
                $base = $product->pivot->quantity * $product->pivot->price;
                $percent = $product->pivot->discount ?? 0;
                return $base * (1 - ($percent / 100));
            });

            // Totales en USD
            $order->subtotal_usd = round($subtotalCalculated, 2);
            $order->total_usd = round($subtotalCalculated + $order->igtf_amount, 2);
            
            // NUEVO: Total en Bolívares
            $order->total_bs = round($order->total_usd * $order->exchange_rate, 2);
        });

        $pdf = Pdf::loadView('pdf.reports.orders', compact('orders'));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $fileName = 'reporte_ordenes_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.pdf';
        $filePath = 'reports_temp/orders/' . $fileName;
        
        Storage::disk('public')->put($filePath, $pdf->output());

        $fileUrl = asset('storage/' . $filePath);
        event(new ReportGenerated($this->userId, $fileUrl, 'Reporte de Órdenes'));
    }
}
