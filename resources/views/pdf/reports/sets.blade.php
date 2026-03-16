<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Juegos</title>
    <style>
        @page { margin: 120px 30px 60px 30px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
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
        footer { position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
        .page-number:after { content: counter(page); }
        
        /* ESTAS SON LAS CLASES QUE FALTABAN PARA EL CÍRCULO Y LA LISTA */
        .color-box { 
            display: inline-block; 
            width: 12px; 
            height: 12px; 
            border: 1px solid #999; 
            border-radius: 50%; /* Esto lo hace redondo */
            margin-bottom: -2px; 
            margin-right: 2px; 
        }
        .stock-list { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
            font-size: 9px; 
            text-align: right; 
        }
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
        <h2>Catálogo de Juegos de Muebles</h2>

        <table>
            <thead>
                <tr>
                    <th width="12%">CÓDIGO</th>
                    <th width="32%" class="text-left">NOMBRE / TIPO</th>
                    <th width="14%">PVP NAT.</th>
                    <th width="14%">PVP COL.</th>
                    <th width="8%">DESC.</th>
                    <th width="20%" class="text-right">DISPONIBILIDAD</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sets as $set)
                    <tr>
                        <td>{{ $set->product->code ?? 'N/A' }}</td>

                        <td class="text-left">
                            <strong>{{ $set->product->name ?? 'S/N' }}</strong><br>
                            <span style="font-size: 9px; color: #666;">
                                {{ $set->setType->name ?? 'Sin tipo' }}
                            </span>
                        </td>

                        <td>${{ number_format($set->pvp_natural, 2) }}</td>
                        
                        <td>${{ number_format($set->pvp_color, 2) }}</td>

                        <td>
                            @if($set->product && $set->product->discount > 0)
                                <span style="color: red; font-weight: bold;">-{{ $set->product->discount }}%</span>
                            @else
                                <span style="color: #999;">-</span>
                            @endif
                        </td>

                        <td class="text-right">
                            @if(!empty($set->available_colors) && count($set->available_colors) > 0)
                                
                                @php
                                    $totalDisponible = 0;
                                    foreach($set->available_colors as $colorData) {
                                        $totalDisponible += $colorData['stock'] ?? 0;
                                    }
                                @endphp

                                <div style="margin-bottom: 5px; font-size: 11px;">
                                    <strong>Total: {{ $totalDisponible }}</strong>
                                </div>

                                <ul class="stock-list">
                                    @foreach($set->available_colors as $colorData)
                                        <li style="color: #444; margin-bottom: 2px;">
                                            <span class="color-box" style="background-color: {{ $colorData['hex'] ?? '#ccc' }}; width: 8px; height: 8px;"></span>
                                            {{ $colorData['name'] ?? 'N/A' }}: <strong>{{ $colorData['stock'] ?? 0 }}</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <span style="color: red; font-weight: bold;">0</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 25px; color: #666;">
                            No se encontraron juegos registrados o que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>