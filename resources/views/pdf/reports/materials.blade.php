<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Materiales</title>
    <style>
        @page {
            margin: 120px 30px 60px 30px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        header {
            position: fixed;
            top: -90px;
            left: 0px;
            right: 0px;
            height: 80px;
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
        }

        .logo-container {
            float: left;
            width: 30%;
        }

        .logo-container img {
            max-height: 65px;
        }

        .company-info {
            float: right;
            width: 70%;
            text-align: right;
            font-size: 10px;
            color: #444;
            line-height: 1.4;
        }

        .company-info strong {
            color: #111;
            font-size: 14px;
        }

        body {
            font-size: 11px;
            color: #333;
        }

        h2 {
            text-align: center;
            margin-bottom: 15px;
            color: #222;
            text-transform: uppercase;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #f0f0f0;
            color: #222;
            font-weight: bold;
            text-align: center;
            padding: 8px 5px;
            border-bottom: 2px solid #ccc;
            font-size: 10px;
        }

        td {
            padding: 6px 5px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            text-align: center;
        }

        .text-left { text-align: left; }
        .text-right { text-align: right; }

        tbody tr:nth-child(even) { background-color: #fafafa; }

        .product-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .color-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #999;
            border-radius: 50%;
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

        footer {
            position: fixed;
            bottom: -40px;
            left: 0px;
            right: 0px;
            height: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }

        .page-number:after { content: counter(page); }
        
        /* Nueva clase para el badge de la categoría */
        .category-badge {
            font-size: 8px;
            color: #666;
            background-color: #e9e9e9;
            padding: 2px 4px;
            border-radius: 3px;
            display: inline-block;
            margin-top: 3px;
            text-transform: uppercase;
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
        <h2>Reporte de Materiales e Insumos</h2>

        <table>
            <thead>
                <tr>
                    <th width="14%">CÓDIGO</th>
                    <th width="46%" class="text-left">NOMBRE / CATEGORÍA.</th>
                    <th width="12%">PRECIO</th>
                    <th width="8%">DESC.</th>
                    <th width="20%" class="text-right">STOCK</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($materials as $material)
                    <tr>
                        <td>{{ $material->product->code ?? 'N/A' }}</td>

                        <td class="text-left">
                            <strong>{{ $material->product->name ?? 'S/N' }}</strong><br>
                            
                            {{-- NUEVO: Aquí mostramos la Categoría y el Tipo --}}
                            @if($material->materialType)
                                <span class="category-badge">
                                    {{ $material->materialType->category->name ?? 'Sin Categoría' }} &rsaquo; {{ $material->materialType->name }}
                                </span>
                            @endif
                        </td>

                        <td>
                            ${{ number_format($material->price, 2) }}
                        </td>

                        <td>
                            @if($material->product && $material->product->discount > 0)
                                <span style="color: red;">-{{ $material->product->discount }}%</span>
                            @else
                                <span style="color: #999;">-</span>
                            @endif
                        </td>

                        <td class="text-right">
                            @if($material->product && $material->product->stocks->count() > 0)
                                
                                @php
                                    // Filtramos para ver si existen stocks que tengan un color_name y color definido
                                    $stocksConColor = $material->product->stocks->filter(function($item) {
                                        return !empty($item->color_name) && !empty($item->color);
                                    });
                                @endphp

                                <div style="margin-bottom: 5px; font-size: 11px;">
                                    <strong>Total: {{ $material->product->stocks->sum('stock') }}</strong>
                                </div>
                                
                                {{-- Si hay stocks segmentados por color, los listamos --}}
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
                        {{-- Corregido el colspan a 5 --}}
                        <td colspan="5" style="text-align: center; padding: 25px; color: #666;">
                            No se encontraron materiales registrados o que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>