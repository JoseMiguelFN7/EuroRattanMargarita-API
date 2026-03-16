<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Órdenes</title>
    <style>
        @page { margin: 120px 20px 60px 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        header { position: fixed; top: -90px; left: 0px; right: 0px; height: 80px; border-bottom: 2px solid #222; padding-bottom: 10px; }
        .logo-container { float: left; width: 30%; }
        .logo-container img { max-height: 65px; }
        .company-info { float: right; width: 70%; text-align: right; font-size: 10px; color: #444; line-height: 1.4; }
        .company-info strong { color: #111; font-size: 14px; }
        body { font-size: 10px; color: #333; }
        h2 { text-align: center; margin-bottom: 15px; color: #222; text-transform: uppercase; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #fcfcfc; color: #444; font-weight: bold; text-align: center; padding: 10px 4px; border-bottom: 2px solid #eaeaea; font-size: 9px; text-transform: uppercase; }
        td { padding: 8px 4px; border-bottom: 1px solid #f3f3f3; vertical-align: middle; text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        
        /* PALETA DE COLORES EXACTA DE ANT DESIGN PARA LOS TAGS */
        .ant-tag { display: inline-block; padding: 3px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid transparent; }
        .tag-blue       { background-color: #e6f4ff; color: #0958d9; border-color: #91caff; }
        .tag-warning    { background-color: #fffbe6; color: #d46b08; border-color: #ffe58f; }
        .tag-processing { background-color: #e6f4ff; color: #1677ff; border-color: #91caff; }
        .tag-cyan       { background-color: #e6fffb; color: #08979c; border-color: #87e8de; }
        .tag-geekblue   { background-color: #f0f5ff; color: #1d39c4; border-color: #adc6ff; }
        .tag-green      { background-color: #f6ffed; color: #389e0d; border-color: #b7eb8f; }
        .tag-purple     { background-color: #f9f0ff; color: #531dab; border-color: #d3adf7; }
        .tag-orange     { background-color: #fff2e8; color: #d4380d; border-color: #ffbb96; }
        .tag-gold       { background-color: #fffbe6; color: #d46b08; border-color: #ffe58f; }
        .tag-volcano    { background-color: #fff2f0; color: #cf1322; border-color: #ffa39e; }
        .tag-default    { background-color: #fafafa; color: #444444; border-color: #d9d9d9; }
        
        footer { position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>

    <header>
        <div class="logo-container">
            <img src="{{ public_path('ERM_logo.webp') }}" alt="Euro Rattan Logo">
        </div>
        <div class="company-info">
            <strong>Euro Rattan Margarita</strong><br>
            RIF: J-30626389-6<br>
            AV. 4 DE MAYO EDIF. BOLIMAR PISO PB LOCAL 1 URB. SABANAMAR PORLAMAR NUEVA ESPARTA 6301<br>
            Teléfono: +58 414-7894819 | eurorattan62@gmail.com
        </div>
    </header>

    <footer>
        Página <span class="page-number"></span> | Generado el: {{ now()->format('d/m/Y h:i A') }}
    </footer>

    <main>
        <h2>Reporte de Órdenes</h2>

        <table>
            <thead>
                <tr>
                    <th width="18%" class="text-left">CLIENTE</th>
                    <th width="12%" class="text-left">CÓDIGO</th>
                    <th width="14%">ESTATUS</th>
                    <th width="12%">FECHA</th>
                    <th width="8%">TASA</th>
                    <th width="12%" class="text-right">TOTAL BS</th>
                    <th width="12%" class="text-right">TOTAL USD</th>
                    <th width="12%" class="text-left">NOTAS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td class="text-left">
                            <strong>{{ $order->user->name ?? 'Usuario Desconocido' }}</strong><br>
                            <span style="font-size: 9px; color: #888;">
                                {{ $order->user->email ?? 'N/A' }}
                            </span>
                        </td>

                        <td class="text-left">{{ $order->code }}</td>

                        <td>
                            @php
                                $rawStatus = strtolower($order->status);
                                $statusText = strtoupper(str_replace('_', ' ', $rawStatus));
                                $statusClass = 'tag-default';

                                switch ($rawStatus) {
                                    case 'created':
                                        $statusText = 'NUEVO ENCARGO'; $statusClass = 'tag-blue'; break;
                                    case 'suggestion_sent':
                                        $statusText = 'SUGERENCIA ENVIADA'; $statusClass = 'tag-warning'; break;
                                    case 'suggestion_replied':
                                        $statusText = 'RESPUESTA RECIBIDA'; $statusClass = 'tag-processing'; break;
                                    case 'approved':
                                        $statusText = 'LISTO PARA FABRICAR'; $statusClass = 'tag-cyan'; break;
                                    case 'quoted':
                                        $statusText = 'COTIZADO'; $statusClass = 'tag-geekblue'; break;
                                    case 'order_created':
                                        $statusText = 'ORDEN CREADA'; $statusClass = 'tag-green'; break;
                                    case 'paid':
                                        $statusText = 'PAGADO'; $statusClass = 'tag-green'; break;
                                    case 'completed':
                                        $statusText = 'COMPLETADO'; $statusClass = 'tag-green'; break;
                                    case 'verifying_payment':
                                        $statusText = 'VERIFICANDO PAGO'; $statusClass = 'tag-purple'; break;
                                    case 'pending_payment':
                                        $statusText = 'PENDIENTE POR PAGO'; $statusClass = 'tag-orange'; break;
                                    case 'pending':
                                        $statusText = 'PENDIENTE'; $statusClass = 'tag-gold'; break;
                                    case 'verified':
                                        $statusText = 'VERIFICADO'; $statusClass = 'tag-green'; break;
                                    case 'rejected':
                                    case 'cancelled':
                                        $statusText = 'CANCELADO'; $statusClass = 'tag-volcano'; break;
                                }
                            @endphp
                            
                            <span class="ant-tag {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </td>

                        <td>
                            {{ $order->created_at->format('d/m/Y,') }}<br>
                            <span style="font-size: 9px; color: #666;">{{ strtolower($order->created_at->format('h:i A')) }}</span>
                        </td>

                        <td>{{ number_format($order->exchange_rate, 2, '.', '') }}</td>

                        <td class="text-right">
                            <strong>Bs. {{ number_format($order->total_bs, 2, ',', '.') }}</strong>
                        </td>

                        <td class="text-right">
                            <strong>${{ number_format($order->total_usd, 2, '.', ',') }}</strong>
                        </td>
                        
                        <td class="text-left" style="font-size: 9px; color: #666;">
                            {{ $order->notes ?: '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 25px; color: #666;">
                            No se encontraron órdenes registradas o que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>