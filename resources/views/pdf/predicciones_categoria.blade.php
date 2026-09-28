<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Predicciones de Compra — {{ $categoria }} — Semana {{ $datos['semana'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #1a1a1a; line-height: 1.3; }

        .header { border-bottom: 2.5pt solid #1e3a5f; padding-bottom: 5pt; margin-bottom: 8pt; display: table; width: 100%; }
        .header-left  { display: table-cell; width: 70%; vertical-align: middle; }
        .header-right { display: table-cell; width: 30%; text-align: right; vertical-align: middle; }
        .empresa-nombre { font-size: 13pt; font-weight: bold; color: #1e3a5f; }
        .empresa-sub { font-size: 7.5pt; color: #555; }
        .badge { background: #1e3a5f; color: #fff; font-size: 8pt; font-weight: bold; padding: 2pt 7pt; border-radius: 3pt; }

        .report-title { font-size: 11pt; font-weight: bold; color: #1e3a5f; margin-bottom: 6pt; }
        .report-subtitle { font-size: 8.5pt; color: #555; margin-bottom: 8pt; }

        table { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        thead th { background: #1e3a5f; color: white; padding: 3pt 4pt; text-align: center; }
        thead th.left { text-align: left; }
        tbody tr:nth-child(even) { background: #f4f7fb; }
        tbody tr:hover { background: #e8eff9; }
        td { padding: 2.5pt 4pt; border-bottom: 0.5pt solid #dde5f0; vertical-align: middle; }
        td.right { text-align: right; }
        td.center { text-align: center; }

        .badge-critico { background: #fee2e2; color: #991b1b; padding: 1pt 4pt; border-radius: 8pt; font-size: 6.5pt; font-weight: bold; }
        .badge-bajo    { background: #fef9c3; color: #854d0e; padding: 1pt 4pt; border-radius: 8pt; font-size: 6.5pt; font-weight: bold; }
        .badge-ok      { background: #dcfce7; color: #166534; padding: 1pt 4pt; border-radius: 8pt; font-size: 6.5pt; font-weight: bold; }

        .summary-row { display: table; width: 100%; margin-bottom: 8pt; }
        .summary-box { display: table-cell; text-align: center; padding: 5pt; border: 0.75pt solid #c8d6e8; border-radius: 3pt; }
        .summary-box + .summary-box { border-left: none; }
        .sum-val { font-size: 13pt; font-weight: bold; color: #1e3a5f; }
        .sum-lbl { font-size: 7pt; color: #666; }

        .footer { margin-top: 8pt; border-top: 0.75pt solid #c8d6e8; padding-top: 4pt; text-align: right; font-size: 7pt; color: #aaa; }
    </style>
</head>
<body>

{{-- Encabezado --}}
<div class="header">
    <div class="header-left">
        <div class="empresa-nombre">{{ $empresa->EmpresaNombre ?? 'Comercial Valencia' }}</div>
        <div class="empresa-sub">RUC {{ $empresa->EmpresaRuc ?? '—' }} &nbsp;|&nbsp; {{ $empresa->EmpresaDireccion ?? '' }}</div>
    </div>
    <div class="header-right">
        <span class="badge">Semana {{ $datos['semana'] }}</span><br>
        <span style="font-size:7.5pt; color:#555;">{{ date('d/m/Y H:i') }}</span>
    </div>
</div>

{{-- Título --}}
<div class="report-title">Sugerencias de Reabastecimiento — Categoría: {{ $categoria }}</div>
<div class="report-subtitle">
    Reporte semanal determinista (Fórmula + Estadística) · Cobertura objetivo: 1.5 semanas
</div>

{{-- Resumen KPIs --}}
<div class="summary-row">
    <div class="summary-box">
        <div class="sum-val">{{ $datos['total_productos'] }}</div>
        <div class="sum-lbl">Productos a reponer</div>
    </div>
    <div class="summary-box">
        <div class="sum-val" style="color: #c0392b;">{{ $datos['criticos'] }}</div>
        <div class="sum-lbl">Críticos (quiebre / bajo mín.)</div>
    </div>
    <div class="summary-box">
        <div class="sum-val">S/ {{ number_format($datos['total_estimado'], 2) }}</div>
        <div class="sum-lbl">Inversión estimada</div>
    </div>
</div>

{{-- Tabla de predicciones --}}
@if(!empty($datos['predicciones']))
<table>
    <thead>
        <tr>
            <th class="left" style="width:20%">Producto</th>
            <th>Stock<br>Actual</th>
            <th>Tránsito</th>
            <th>Demanda<br>Sem.</th>
            <th>Sugerencia<br>(Empaques)</th>
            <th>Sugerencia<br>(Base)</th>
            <th>Empaque</th>
            <th>P. Ref.<br>(S/)</th>
            <th>Total Est.<br>(S/)</th>
            <th>Cobertura</th>
            <th>Estado</th>
            <th>Score</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datos['predicciones'] as $p)
        <tr>
            <td>
                <strong>{{ $p['producto_nombre'] }}</strong><br>
                <span style="color:#888; font-size:6.5pt;">{{ $p['producto_id'] }}</span>
            </td>
            <td class="right {{ $p['stock_actual'] <= 0 ? 'quiebre' : '' }}">{{ number_format($p['stock_actual'], 1) }}</td>
            <td class="right">{{ number_format($p['stock_transito'], 1) }}</td>
            <td class="right">{{ number_format($p['demanda_semanal'], 1) }}</td>
            <td class="center"><strong>{{ $p['cantidad_empaques'] }}</strong></td>
            <td class="right">{{ number_format($p['cantidad_base'], 1) }}</td>
            <td class="center">{{ $p['empaque_preferido'] }}</td>
            <td class="right">{{ number_format($p['precio_referencia_empaque'], 2) }}</td>
            <td class="right"><strong>{{ number_format($p['total_estimado'], 2) }}</strong></td>
            <td class="center">{{ $p['cobertura_dias_texto'] }}</td>
            <td class="center">
                @if($p['stock_actual'] <= 0)
                    <span class="badge-critico">QUIEBRE</span>
                @elseif($p['stock_actual'] <= $p['stock_minimo'])
                    <span class="badge-bajo">BAJO MÍN</span>
                @else
                    <span class="badge-ok">OK</span>
                @endif
            </td>
            <td class="center">{{ number_format($p['score'] * 100, 0) }}%</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
    <p style="text-align:center; color:#999; padding:20pt;">No hay productos que reponer en esta categoría esta semana.</p>
@endif

<div class="footer">
    Generado por Valencia AI · Comercial Valencia · {{ date('d/m/Y H:i:s') }}
</div>

</body>
</html>
