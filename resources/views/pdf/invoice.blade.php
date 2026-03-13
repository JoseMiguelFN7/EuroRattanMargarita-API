<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante Digital {{ $invoice->invoice_number }}</title>
    <link rel="icon" href="{{ public_path('favicon.ico') }}" type="image/x-icon">
    
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; }
        
        /* Ajustes del Header para acomodar el logo */
        .header { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px; display: table; }
        .logo-container { display: table-cell; width: 15%; vertical-align: top; }
        .company-details { display: table-cell; width: 50%; vertical-align: top; padding-left: 10px; }
        .invoice-details { display: table-cell; width: 35%; text-align: right; vertical-align: top; }
        
        .clear { clear: both; }
        .client-details { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        
        /* Estilos para la tabla invisible de los datos del cliente */
        .client-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .client-table th, .client-table td { border: none; padding: 3px 0; text-align: left; font-size: 13px; }
        .client-table th { width: 25%; font-weight: bold; background-color: transparent; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f4f4f4; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Contenedor inferior dividido en 2 columnas */
        .bottom-section { width: 100%; margin-top: 20px; }
        .qr-section { width: 45%; float: left; padding-top: 10px; }
        .totals { width: 50%; float: right; }
        
        .totals table th, .totals table td { text-align: right; font-size: 13px; padding: 4px 8px; }
        .rate-info { font-size: 11px; color: #555; margin-top: 10px; font-style: italic; }
        .footer-note { margin-top: 50px; font-size: 10px; text-align: center; color: #777; }
        .qr-text { font-size: 10px; color: #555; margin-top: 5px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo-container">
            <img src="{{ public_path('ERM_logo.webp') }}" alt="Logo Euro Rattan" style="max-width: 100px; height: auto;">
        </div>

        <div class="company-details">
            <h2 style="margin:0; color: #2c3e50; font-size: 16px;">EURO RATTAN MARGARITA, C.A.</h2>
            <p style="margin:5px 0;">RIF: J-306263896<br>
            AV. 4 DE MAYO EDIF. BOLIMAR PISO PB LOCAL 1 URB. SABANAMAR PORLAMAR NUEVA ESPARTA 6301<br>
            Teléfono: (0295) 263-7418</p>
        </div>
        
        <div class="invoice-details">
            <h2 style="margin:0; font-size: 16px;">COMPROBANTE DIGITAL</h2>
            <p style="margin:5px 0;"><strong>N° Comprobante:</strong> {{ $invoice->invoice_number }}<br>
            <strong>N° Control:</strong> {{ $invoice->control_number }}<br>
            <strong>Fecha de Emisión:</strong> {{ \Carbon\Carbon::parse($invoice->emitted_at ?? now())->format('d/m/Y h:i A') }}</p>
        </div>
    </div>

    <div class="client-details">
        <table class="client-table">
            <tr>
                <th>Razón Social / Nombre:</th>
                <td>{{ $invoice->client_name }}</td>
            </tr>
            <tr>
                <th>CI / RIF:</th>
                <td>{{ $invoice->client_document }}</td>
            </tr>
            <tr>
                <th>Dirección:</th>
                <td>{{ $invoice->client_address }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th width="8%">Cant.</th>
                <th width="42%">Descripción</th>
                <th width="15%">Precio Unit. Bs.</th>
                <th width="15%">Desc. (%)</th>
                <th width="20%">Total Bs.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->products as $item)
            @php
                $rate = $order->exchange_rate;
                $precioBs = $item->pivot->price * $rate;
                $descuentoPorcentaje = $item->pivot->discount ?? 0;
                
                $montoBaseBs = $item->pivot->quantity * $precioBs;
                $montoDescuentoBs = $montoBaseBs * ($descuentoPorcentaje / 100);
                $totalLineaBs = $montoBaseBs - $montoDescuentoBs;

                $colorName = '';
                if (!empty($item->pivot->variant_id)) {
                    $color = $item->colors->firstWhere('id', $item->pivot->variant_id);
                    if ($color) {
                        $colorName = ' - ' . $color->name;
                    }
                }
            @endphp
            <tr>
                <td class="text-center">{{ number_format($item->pivot->quantity, 2, ',', '.') }}</td>
                <td>{{ $item->name }}{{ $colorName }}</td>
                <td class="text-right">{{ number_format($precioBs, 2, ',', '.') }}</td>
                <td class="text-center">{{ $descuentoPorcentaje }}%</td>
                <td class="text-right">{{ number_format($totalLineaBs, 2, ',', '.') }} (E)</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="rate-info">
        * Documento expresado en Bolívares (Bs.) calculado a una tasa de cambio de 1 USD = {{ number_format($order->exchange_rate, 4, ',', '.') }} Bs.<br>
        * (E): Operación Exenta de IVA según el régimen de Puerto Libre (Estado Nueva Esparta).
    </div>

    <div class="bottom-section">
        <div class="qr-section">
            @if($invoice->verification_token)
                <img src="data:image/svg+xml;base64,{{ base64_encode(SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->margin(0)->size(90)->generate($invoice->verification_token)) }}" alt="QR Code">
                <p class="qr-text">Escanee este código para<br>verificar la autenticidad<br>del comprobante.</p>
            @endif
        </div>

        <div class="totals">
            <table>
                <tr>
                    <th style="border:none;">Subtotal Exento:</th>
                    <td style="border:none;">Bs. {{ number_format($invoice->exempt_amount, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th style="border:none;">IVA (0%):</th>
                    <td style="border:none;">Bs. 0,00</td>
                </tr>
                
                @if($invoice->igtf_amount > 0)
                <tr>
                    <th style="border:none;">IGTF (3%):</th>
                    <td style="border:none;">Bs. {{ number_format($invoice->igtf_amount, 2, ',', '.') }}</td>
                </tr>
                @endif

                <tr style="background-color: #f4f4f4;">
                    <th style="font-size: 15px;">TOTAL A PAGAR:</th>
                    <td style="font-size: 15px;"><strong>Bs. {{ number_format($invoice->total_amount, 2, ',', '.') }}</strong></td>
                </tr>

                <tr>
                    <th style="border:none;">Monto Pagado:</th>
                    <td style="border:none;">Bs. {{ number_format($invoice->paid_amount, 2, ',', '.') }}</td>
                </tr>

                @php
                    $vuelto = $invoice->paid_amount - $invoice->total_amount;
                @endphp

                @if($vuelto > 0)
                <tr>
                    <th style="border:none;">Vuelto a Favor:</th>
                    <td style="border:none;"><strong>Bs. {{ number_format($vuelto, 2, ',', '.') }}</strong></td>
                </tr>
                @endif
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="footer-note">
        <p>Gracias por su compra.</p>
    </div>

</body>
</html>