<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getChartData(Request $request)
    {
        // 1. Leer parámetros. Si no se envían, por defecto mostramos ambos.
        // $request->boolean() evalúa 'true', '1', 'on' como true.
        $showIngresos = $request->boolean('income', true);
        $showEgresos  = $request->boolean('expenses', true);

        $monthsCount = 6;
        $startDate = Carbon::now()->subMonths($monthsCount - 1)->startOfMonth();

        $chartData = [];
        $mesesEspanol = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        // 2. Pre-construir la estructura de los últimos 6 meses cronológicamente
        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m'); // Clave interna "2025-09"
            
            $baseObj = ['month' => $mesesEspanol[$date->month - 1]];
            
            // Solo creamos las llaves si fueron solicitadas
            if ($showIngresos) $baseObj['ingresos'] = 0;
            if ($showEgresos) $baseObj['egresos'] = 0;

            $chartData[$monthKey] = $baseObj;
        }

        // 3. Obtener y sumar Ingresos (Órdenes completadas)
        if ($showIngresos) {
            $orders = Order::with('products')
                ->where('status', 'completed')
                ->where('created_at', '>=', $startDate)
                ->get();

            foreach ($orders as $order) {
                $monthKey = $order->created_at->format('Y-m');
                
                if (isset($chartData[$monthKey])) {
                    $subtotal = $order->products->sum(function ($product) {
                        $base = $product->pivot->quantity * $product->pivot->price;
                        $percent = $product->pivot->discount ?? 0;
                        
                        return $base * (1 - ($percent / 100)); // Descuento porcentual
                    });

                    $totalOrder = $subtotal + ($order->igtf_amount ?? 0);
                    $chartData[$monthKey]['ingresos'] += $totalOrder;
                }
            }
        }

        // 4. Obtener y sumar Egresos (Compras)
        if ($showEgresos) {
            $purchases = Purchase::with('products')
                ->where('date', '>=', $startDate)
                ->get();

            foreach ($purchases as $purchase) {
                // El campo 'date' se formatea directo porque lo tienes en $casts
                $monthKey = $purchase->date->format('Y-m'); 
                
                if (isset($chartData[$monthKey])) {
                    $totalPurchase = $purchase->products->sum(function ($product) {
                        // Según la fórmula de tu modelo Purchase (monto fijo, no porcentaje)
                        $netCost = $product->pivot->cost - ($product->pivot->discount ?? 0);
                        return $netCost * $product->pivot->quantity;
                    });

                    $chartData[$monthKey]['egresos'] += $totalPurchase;
                }
            }
        }

        // 5. Redondear valores para evitar decimales infinitos en la gráfica
        foreach ($chartData as $key => $data) {
            if (isset($data['ingresos'])) $chartData[$key]['ingresos'] = round($data['ingresos'], 2);
            if (isset($data['egresos']))  $chartData[$key]['egresos']  = round($data['egresos'], 2);
        }

        // 6. array_values elimina las llaves "Y-m" y deja un arreglo limpio [{}, {}, {}]
        return response()->json(array_values($chartData));
    }
}