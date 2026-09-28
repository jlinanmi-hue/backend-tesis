<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Reabastecimiento — {{ $p['producto_nombre'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm 8mm 10mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #1a1a1a;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }

        /* ── Tablas de Maquetación ── */
        .layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5pt;
        }
        .layout-table td {
            vertical-align: top;
            padding: 0;
        }

        /* ── Encabezado ── */
        .header-table {
            width: 100%;
            border-bottom: 2pt solid #1e3a5f;
            padding-bottom: 4pt;
            margin-bottom: 5pt;
        }
        .empresa-nombre { font-size: 12pt; font-weight: bold; color: #1e3a5f; }
        .empresa-sub    { font-size: 7.5pt; color: #555; }
        .badge-semana {
            background: #1e3a5f;
            color: white;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 2pt 5pt;
        }

        /* ── Título ── */
        .ficha-title {
            background: #eef3fa;
            border-left: 3pt solid #1e3a5f;
            padding: 3pt 6pt;
            margin-bottom: 5pt;
            font-size: 9.5pt;
            font-weight: bold;
            color: #1e3a5f;
        }
        .ficha-title small {
            font-size: 7.5pt;
            font-weight: normal;
            color: #555;
        }

        /* ── Tarjetas con Borde ── */
        .card {
            border: 0.75pt solid #c8d6e8;
            padding: 4pt 6pt;
            background: #fff;
        }
        .card-title {
            font-size: 7pt;
            font-weight: bold;
            color: #1e3a5f;
            text-transform: uppercase;
            border-bottom: 0.5pt solid #c8d6e8;
            padding-bottom: 2pt;
            margin-bottom: 3pt;
        }

        /* ── Tablas de Datos ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        .data-table th {
            background: #1e3a5f;
            color: white;
            text-align: center;
            padding: 2pt 3pt;
            font-size: 7pt;
        }
        .data-table td {
            text-align: center;
            padding: 2pt 3pt;
            border-bottom: 0.5pt solid #eee;
        }
        .data-table td.left { text-align: left; }
        .data-table td.bold { font-weight: bold; }
        .quiebre { color: #c0392b; font-weight: bold; }

        /* ── KPIs ── */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5pt;
        }
        .kpi-cell {
            width: 25%;
            text-align: center;
            padding: 4pt 2pt;
            border: 0.75pt solid #c8d6e8;
            background: #fbfdff;
        }
        .kpi-val { font-size: 11pt; font-weight: bold; color: #1e3a5f; }
        .kpi-lbl { font-size: 6.5pt; color: #666; margin-top: 1pt; }
        .critico .kpi-val { color: #c0392b; }

        /* ── Sugerencia Destacada ── */
        .sug-table {
            width: 100%;
            background: #e8f5e9;
            border: 1pt solid #27ae60;
            padding: 4pt 6pt;
            margin-bottom: 5pt;
        }
        .sug-cant { font-size: 13pt; font-weight: bold; color: #1a6e34; }
        .sug-total { font-size: 11pt; font-weight: bold; color: #1a6e34; text-align: right; }

        /* ── Score Badge ── */
        .score-badge {
            display: inline-block;
            padding: 1pt 5pt;
            font-weight: bold;
            font-size: 7.5pt;
        }
        .score-alto   { background: #fee2e2; color: #991b1b; }
        .score-medio  { background: #fef9c3; color: #854d0e; }
        .score-bajo   { background: #dcfce7; color: #166534; }

        /* ── Talón ── */
        .cut-line {
            border-top: 1pt dashed #888;
            margin: 4pt 0 4pt 0;
            text-align: center;
            color: #777;
            font-size: 6.5pt;
            padding-top: 1pt;
        }
        .talon-table {
            width: 100%;
            border: 0.75pt solid #1e3a5f;
            background: #f9fbff;
            padding: 4pt 6pt;
            font-size: 7pt;
        }
        .field-line { border-bottom: 0.5pt solid #333; height: 12pt; width: 95%; margin-bottom: 4pt; }
        .firma-box {
            border: 0.5pt solid #444;
            height: 24pt;
            text-align: center;
            line-height: 24pt;
            font-size: 6.5pt;
            color: #888;
        }

        .doc-footer {
            text-align: right;
            font-size: 6.5pt;
            color: #999;
            margin-top: 2pt;
        }
    </style>
</head>
<body>

{{-- ENCABEZADO --}}
<table class="header-table">
    <tr>
        <td style="width: 70%;">
            <div class="empresa-nombre">{{ $empresa->EmpresaNombre ?? 'Comercial Valencia' }}</div>
            <div class="empresa-sub">RUC {{ $empresa->EmpresaRuc ?? '—' }} &nbsp;|&nbsp; {{ $empresa->EmpresaDireccion ?? '' }}</div>
        </td>
        <td style="width: 30%; text-align: right; vertical-align: middle;">
            <span class="badge-semana">Semana {{ $p['historial_semanas'][0]['semana'] ?? '—' }}</span><br>
            <span style="font-size: 7pt; color: #555;">{{ date('d/m/Y H:i') }}</span>
        </td>
    </tr>
</table>

{{-- TÍTULO --}}
<div class="ficha-title">
    {{ $p['producto_nombre'] }}
    <small>({{ $p['producto_id'] }} &nbsp;|&nbsp; {{ $p['categoria_nombre'] }})</small>
</div>

{{-- FILA 1: PRODUCTO + PROVEEDOR --}}
<table class="layout-table">
    <tr>
        <td style="width: 50%; padding-right: 3pt;">
            <div class="card">
                <div class="card-title">Información del Producto</div>
                <table class="data-table">
                    <tr><td class="left bold" style="width: 45%;">Categoría</td><td class="left">{{ $p['categoria_nombre'] }}</td></tr>
                    <tr><td class="left bold">Unidad base</td><td class="left">{{ $p['unidad_base_desc'] }}</td></tr>
                    <tr><td class="left bold">Empaque compra</td><td class="left">{{ $p['empaque_preferido'] }} (x{{ $p['multiplo_empaque'] }})</td></tr>
                    <tr><td class="left bold">Score urgencia</td>
                        <td class="left">
                            @php $s = $p['score']; @endphp
                            <span class="score-badge {{ $s >= 0.7 ? 'score-alto' : ($s >= 0.4 ? 'score-medio' : 'score-bajo') }}">
                                {{ number_format($s * 100, 0) }}%
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
        <td style="width: 50%; padding-left: 3pt;">
            <div class="card">
                <div class="card-title">Proveedor Habitual</div>
                @if($p['proveedor_nombre'])
                    <table class="data-table">
                        <tr><td class="left bold" style="width: 35%;">Razón Social</td><td class="left">{{ $p['proveedor_nombre'] }}</td></tr>
                        <tr><td class="left bold">Código</td><td class="left">{{ $p['proveedor_id'] }}</td></tr>
                    </table>
                @else
                    <div style="color: #999; font-size: 7pt; padding: 6pt 0;">Sin proveedor habitual registrado</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- HISTORIAL DE CONSUMO --}}
<table class="layout-table">
    <tr>
        <td>
            <div class="card">
                <div class="card-title">Historial de Consumo (Últimas 4 semanas)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach($p['historial_semanas'] as $h)
                                <th>{{ $h['semana'] }}</th>
                            @endforeach
                            <th>Prom. Efectivo</th>
                            <th>Tendencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach($p['historial_semanas'] as $h)
                                <td class="{{ $h['quiebre'] ? 'quiebre' : '' }}">
                                    {{ number_format($h['ventas'], 1) }}
                                    @if($h['quiebre'])<br><span style="font-size: 6pt;">(quiebre)</span>@endif
                                </td>
                            @endforeach
                            <td class="bold">{{ number_format($p['demanda_semanal'], 1) }}</td>
                            <td>{{ number_format($p['tendencia'], 2) }}x</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- KPIS DE STOCK --}}
<table class="kpi-table">
    <tr>
        <td class="kpi-cell {{ $p['stock_actual'] <= 0 ? 'critico' : '' }}">
            <div class="kpi-val">{{ number_format($p['stock_actual'], 1) }}</div>
            <div class="kpi-lbl">Stock actual (uds base)</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-val">{{ number_format($p['stock_transito'], 1) }}</div>
            <div class="kpi-lbl">En tránsito</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-val">{{ number_format($p['stock_objetivo'], 1) }}</div>
            <div class="kpi-lbl">Stock objetivo (1.5 sem)</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-val" style="font-size: 8.5pt;">{{ $p['cobertura_dias_texto'] }}</div>
            <div class="kpi-lbl">Cobertura estimada</div>
        </td>
    </tr>
</table>

{{-- SUGERENCIA DE COMPRA --}}
<table class="sug-table">
    <tr>
        <td style="vertical-align: middle;">
            <span style="font-size: 7pt; color: #2d6a4f;">Cantidad sugerida de compra:</span><br>
            <span class="sug-cant">{{ $p['cantidad_empaques'] }} {{ $p['empaque_preferido'] }}</span>
            <span style="font-size: 8pt; color: #2d6a4f;">(= {{ number_format($p['cantidad_base'], 1) }} uds base)</span><br>
            <span style="font-size: 7pt; color: #333;">
                Precio ref. unitario: <strong>S/ {{ number_format($p['precio_referencia_empaque'], 2) }}</strong>
                @if($p['precio_fallback']) <em>(catálogo)</em> @endif
                @if($p['variacion_precio_pct'] !== null)
                    &nbsp;|&nbsp; Variación: <strong>{{ $p['variacion_precio_pct'] > 0 ? '+' : '' }}{{ $p['variacion_precio_pct'] }}%</strong>
                @endif
            </span>
        </td>
        <td style="text-align: right; vertical-align: middle; width: 35%;">
            <span style="font-size: 7pt; color: #2d6a4f;">Total estimado:</span><br>
            <span class="sug-total">S/ {{ number_format($p['total_estimado'], 2) }}</span>
        </td>
    </tr>
</table>

{{-- LÍNEA DE CORTE --}}
<div class="cut-line">✂ &nbsp; TALÓN DE COTIZACIÓN PARA PROVEEDOR &nbsp; ✂</div>

{{-- TALÓN DE COTIZACIÓN --}}
<table class="talon-table">
    <tr>
        <td colspan="3" style="font-weight: bold; color: #1e3a5f; padding-bottom: 3pt; border-bottom: 0.5pt solid #1e3a5f; margin-bottom: 3pt;">
            Solicitud de Cotización — {{ $p['producto_nombre'] }} ({{ $p['producto_id'] }}) &nbsp;|&nbsp; Cantidad: {{ $p['cantidad_empaques'] }} {{ $p['empaque_preferido'] }}
        </td>
    </tr>
    <tr>
        <td style="width: 35%; padding-top: 3pt;">
            Precio Unitario Ofertado (S/):
            <div class="field-line"></div>
            Condiciones de Pago:
            <div class="field-line"></div>
        </td>
        <td style="width: 35%; padding-top: 3pt;">
            Plazo de Entrega (días):
            <div class="field-line"></div>
            Vigencia de Cotización:
            <div class="field-line"></div>
        </td>
        <td style="width: 30%; padding-top: 3pt; text-align: center;">
            Firma y Sello del Proveedor:
            <div class="firma-box">Firma / Sello</div>
        </td>
    </tr>
</table>

<div class="doc-footer">
    Generado por Valencia AI · Comercial Valencia · {{ date('d/m/Y H:i:s') }}
</div>

</body>
</html>
