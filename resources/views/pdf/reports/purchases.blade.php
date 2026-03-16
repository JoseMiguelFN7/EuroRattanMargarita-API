<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Compras</title>
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
        <h2>Reporte de Compras</h2>

        <table>
            <thead>
                <tr>
                    <th width="15%" class="text-left">CÓDIGO</th>
                    <th width="35%" class="text-left">PROVEEDOR</th>
                    <th width="16%">FECHA DE EMISIÓN</th>
                    <th width="17%" class="text-right">TOTAL BS</th>
                    <th width="17%" class="text-right">TOTAL USD</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td class="text-left"><strong>{{ $purchase->code }}</strong></td>

                        <td class="text-left">
                            {{ $purchase->supplier->name ?? 'Proveedor Desconocido' }}<br>
                            <span style="font-size: 9px; color: #666;">
                                RIF: {{ $purchase->supplier->rif ?? 'N/A' }}
                            </span>
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse($purchase->date)->format('d/m/Y') }}
                        </td>

                        <td class="text-right">
                            <strong>Bs. {{ number_format($purchase->total_ves, 2, ',', '.') }}</strong>
                        </td>

                        <td class="text-right">
                            <strong>${{ number_format($purchase->total, 2, '.', ',') }}</strong>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 25px; color: #666;">
                            No se encontraron compras registradas o que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>