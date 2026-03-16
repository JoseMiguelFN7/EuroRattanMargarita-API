<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Muebles</title>
    <style>
        @page {
            margin: 120px 30px 60px 30px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        header { position: fixed; top: -90px; left: 0px; right: 0px; height: 80px; border-bottom: 2px solid #222; padding-bottom: 10px; }
        .logo-container { float: left; width: 30%; }
        .logo-container img { max-height: 65px; }
        .company-info { float: right; width: 70%; text-align: right; font-size: 10px; color: #444; line-height: 1.4; }
        .company-info strong { color: #111; font-size: 14px; }
        body { font-size: 11px; color: #333; }
        h2 { text-align: center; margin-bottom: 15px; color: #222; text-transform: uppercase; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f0f0f0; color: #222; font-weight: bold; text-align: center; padding: 8px 5px; border-bottom: 2px solid #ccc; font-size: 10px; }
        td { padding: 6px 5px; border-bottom: 1px solid #eee; vertical-align: middle; text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        tbody tr:nth-child(even) { background-color: #fafafa; }
        .color-box { display: inline-block; width: 12px; height: 12px; border: 1px solid #999; border-radius: 50%; margin-bottom: -2px; margin-right: 2px; }
        .stock-list { list-style: none; padding: 0; margin: 0; font-size: 9px; text-align: right; }
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
        <h2>Catálogo de Muebles</h2>

        <table>
            <thead>
                <tr>
                    <th width="10%">CÓDIGO</th>
                    <th width="28%" class="text-left">NOMBRE / TIPO</th>
                    <th width="14%">PVP NAT.</th>
                    <th width="14%">PVP COL.</th>
                    <th width="10%">DESC.</th>
                    <th width="24%" class="text-right">STOCK</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($furnitures as $furniture)
                    <tr>
                        <td>{{ $furniture->product->code ?? 'N/A' }}</td>

                        <td class="text-left">
                            <strong>{{ $furniture->product->name ?? 'S/N' }}</strong><br>
                            <span style="font-size: 9px; color: #666;">
                                {{ $furniture->furnitureType->name ?? 'Sin tipo' }}
                            </span>
                        </td>

                        <td>
                            ${{ number_format($furniture->pvp_natural, 2) }}
                        </td>

                        <td>
                            ${{ number_format($furniture->pvp_color, 2) }}
                        </td>

                        <td>
                            @if($furniture->product && $furniture->product->discount > 0)
                                <span style="color: red; font-weight: bold;">-{{ $furniture->product->discount }}%</span>
                            @else
                                <span style="color: #999;">-</span>
                            @endif
                        </td>

                        <td class="text-right">
                            @if($furniture->product && $furniture->product->stocks->count() > 0)
                                @php
                                    $stocksConColor = $furniture->product->stocks->filter(function($item) {
                                        return !empty($item->color_name) && !empty($item->color);
                                    });
                                @endphp

                                <div style="margin-bottom: 5px; font-size: 11px;">
                                    <strong>Total: {{ $furniture->product->stocks->sum('stock') }}</strong>
                                </div>
                                
                                @if($stocksConColor->count() > 0)
                                    <ul class="stock-list">
                                        @foreach($stocksConColor as $stockItem)
                                            <li style="color: #444; margin-bottom: 2px;">
                                                <span class="color-box" style="background-color: {{ $stockItem->color }}; width: 8px; height: 8px;"></span>
                                                {{ $stockItem->color_name }}: <strong>{{ $stockItem->stock }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @else
                                <span style="color: red; font-weight: bold;">0</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 25px; color: #666;">
                            No se encontraron muebles registrados o que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>