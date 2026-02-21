<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; }
        .header { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        .company-details { float: left; width: 55%; }
        .invoice-details { float: right; width: 45%; text-align: right; }
        .clear { clear: both; }
        .client-details { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f4f4f4; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { width: 45%; float: right; margin-top: 20px; }
        .totals table th, .totals table td { text-align: right; font-size: 14px; }
        .rate-info { font-size: 11px; color: #555; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-details">
            <h2>EURO RATTAN MARGARITA, C.A.</h2>
            <p>RIF: J-306263896<br>
            Domicilio Fiscal: AV. 4 DE MAYO EDIF. BOLIMAR PISO PB LOCAL 1 URB. SABANAMAR PORLAMAR NUEVA ESPARTA 6301<br>
            Teléfono: (0295) 263-7418</p>
        </div>
        <div class="invoice-details">
            <h2>FACTURA</h2>
            <p><strong>N° Factura:</strong> {{ $invoice->invoice_number }}<br>
            <strong>N° Control:</strong> {{ $invoice->control_number }}<br>
            <strong>Fecha de Emisión:</strong> {{ \Carbon\Carbon::parse($invoice->emitted_at ?? now())->format('d/m/Y H:i A') }}</p>
        </div>
        <div class="clear"></div>
    </div>

    <div class="client-details">
        <p><strong>Razón Social / Nombre:</strong> {{ $invoice->client_name }}<br>
        <strong>CI / RIF:</strong> {{ $invoice->client_document }}<br>
        <strong>Dirección:</strong> {{ $invoice->client_address }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="10%">Cant.</th>
                <th width="40%">Descripción</th>
                <th width="15%">Precio Unit.</th>
                <th width="15%">Descuento</th>
                <th width="20%">Total Bs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->products as $item)
            @php
                // Tasa de cambio de la orden
                $rate = $order->exchange_rate;
                
                // Conversión a Bolívares
                $precioBs = $item->pivot->price * $rate;
                $descuentoBs = ($item->pivot->discount ?? 0) * $rate;
                
                // Cálculo de la línea
                $totalLineaBs = $item->pivot->quantity * ($precioBs - $descuentoBs);
            @endphp
            <tr>
                <td class="text-center">{{ $item->pivot->quantity }}</td>
                <td>{{ $item->name }}</td>
                <td class="text-right">{{ number_format($precioBs, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($descuentoBs, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totalLineaBs, 2, ',', '.') }} (E)</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="rate-info">
        * Documento expresado en Bolívares (Bs.) calculado a una tasa de cambio de Bs. {{ number_format($order->exchange_rate, 2, ',', '.') }}
    </div>

    <div class="totals">
        <table>
            <tr>
                <th>Base Imponible:</th>
                <td>Bs. 0,00</td>
            </tr>
            <tr>
                <th>Monto Exento:</th>
                <td>Bs. {{ number_format($invoice->exempt_amount, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <th>IVA (0%):</th>
                <td>Bs. 0,00</td>
            </tr>
            <tr>
                <th>TOTAL A PAGAR:</th>
                <td><strong>Bs. {{ number_format($invoice->total_amount, 2, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

</body>
</html>