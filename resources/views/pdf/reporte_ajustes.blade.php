<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Mermas y Ajustes de Inventario</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #333333;
            line-height: 1.3;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 3px;
        }
        .company-subtitle {
            font-size: 9px;
            color: #64748b;
        }
        .report-card {
            border: 2px solid #b91c1c;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            background-color: #fef2f2;
        }
        .report-card .title {
            font-size: 12px;
            font-weight: bold;
            color: #991b1b;
            text-transform: uppercase;
        }
        .report-card .date {
            font-size: 9px;
            color: #64748b;
            margin-top: 3px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 15px;
        }
        .kpi-box {
            padding: 8px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        .kpi-box .val {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .kpi-box .val.loss {
            color: #dc2626;
        }
        .kpi-box .lbl {
            font-size: 8.5px;
            text-transform: uppercase;
            color: #64748b;
            margin-top: 2px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1.5px solid #1e3a8a;
            padding-bottom: 3px;
            margin-top: 15px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            padding: 5px 6px;
            text-align: left;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-merma { background-color: #fee2e2; color: #991b1b; }
        .badge-danado { background-color: #fef3c7; color: #92400e; }
        .badge-vencido { background-color: #f3e8ff; color: #6b21a8; }
        .badge-rotura { background-color: #ffedd5; color: #9a3412; }
        .badge-default { background-color: #e2e8f0; color: #334155; }
        .footer {
            margin-top: 30px;
            width: 100%;
            border-collapse: collapse;
        }
        .footer td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 40px;
        }
        .signature-line {
            border-top: 1px solid #334155;
            padding-top: 5px;
            font-size: 9px;
            font-weight: bold;
            color: #334155;
        }
        .signature-role {
            font-size: 8px;
            color: #64748b;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="company-title">COMERCIAL VALENCIA S.A.C.</div>
                <div class="company-subtitle">RUC: 20480922861 | Av. Los Laureles 340, Chiclayo</div>
                <div class="company-subtitle">Sistema Integrado de Control de Inventario y Almacén</div>
            </td>
            <td style="width: 40%;">
                <div class="report-card">
                    <div class="title">INFORME DE MERMAS Y DESPERDICIOS</div>
                    <div class="date">Fecha Emisión: {{ now()->format('d/m/Y H:i:s') }}</div>
                    @if(!empty($filtros['fechaDesde']) || !empty($filtros['fechaHasta']))
                        <div class="date">Período: {{ $filtros['fechaDesde'] ?? 'Inicio' }} al {{ $filtros['fechaHasta'] ?? 'Hoy' }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="kpi-table">
        <tr>
            <td class="kpi-box" style="width: 25%;">
                <div class="val">{{ $resumen['total_ajustes'] }}</div>
                <div class="lbl">Ajustes Registrados</div>
            </td>
            <td class="kpi-box" style="width: 25%;">
                <div class="val">{{ number_format($resumen['total_unidades'], 2) }}</div>
                <div class="lbl">Unidades Mermadas</div>
            </td>
            <td class="kpi-box" style="width: 25%;">
                <div class="val loss">S/ {{ number_format($resumen['costo_total_perdido'], 2) }}</div>
                <div class="lbl">Pérdida en Costo</div>
            </td>
            <td class="kpi-box" style="width: 25%;">
                <div class="val" style="color: #ea580c;">S/ {{ number_format($resumen['venta_total_perdida'], 2) }}</div>
                <div class="lbl">Pérdida en Venta</div>
            </td>
        </tr>
    </table>

    <div class="section-title">1. Resumen Consolidado por Tipo de Ajuste</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tipo de Merma / Ajuste</th>
                <th class="text-center">N° Registros</th>
                <th class="text-right">Unidades</th>
                <th class="text-right">Pérdida en Costo</th>
                <th class="text-right">Pérdida en Venta</th>
                <th class="text-right">% Participación</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumen['por_tipo'] as $tipo)
            @php
                $pct = $resumen['costo_total_perdido'] > 0 
                    ? ($tipo['costo_perdido'] / $resumen['costo_total_perdido']) * 100 
                    : 0;
            @endphp
            <tr>
                <td><strong>{{ $tipo['tipo'] }}</strong></td>
                <td class="text-center">{{ $tipo['total_registros'] }}</td>
                <td class="text-right">{{ number_format($tipo['total_unidades'], 2) }}</td>
                <td class="text-right" style="color: #dc2626; font-weight: bold;">S/ {{ number_format($tipo['costo_perdido'], 2) }}</td>
                <td class="text-right">S/ {{ number_format($tipo['venta_perdida'], 2) }}</td>
                <td class="text-right">{{ number_format($pct, 1) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">2. Detalle de Registros de Mermas y Desperdicios</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">Código</th>
                <th style="width: 11%;">Fecha</th>
                <th style="width: 23%;">Producto</th>
                <th style="width: 11%;">Tipo</th>
                <th class="text-right" style="width: 9%;">Cantidad</th>
                <th class="text-right" style="width: 10%;">Costo Unit.</th>
                <th class="text-right" style="width: 11%;">Pérdida S/</th>
                <th style="width: 15%;">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ajustes as $a)
            @php
                $badgeClass = match($a->Ajuste_inventario_tipo) {
                    'Merma' => 'badge-merma',
                    'Dañado' => 'badge-danado',
                    'Vencido' => 'badge-vencido',
                    'Rotura' => 'badge-rotura',
                    default => 'badge-default'
                };
            @endphp
            <tr>
                <td><strong>{{ $a->Ajuste_inventario_id }}</strong></td>
                <td>{{ $a->Ajuste_inventario_fecha ? $a->Ajuste_inventario_fecha->format('d/m/Y H:i') : '-' }}</td>
                <td>{{ $a->producto->ProductoNombre ?? $a->Ajuste_inventario_ProductoId }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $a->Ajuste_inventario_tipo }}</span></td>
                <td class="text-right">{{ number_format((float) $a->Ajuste_inventario_cantidad, 2) }}</td>
                <td class="text-right">S/ {{ number_format($a->costo_unitario ?? 0, 2) }}</td>
                <td class="text-right" style="color: #dc2626; font-weight: bold;">S/ {{ number_format($a->impacto_financiero_costo ?? 0, 2) }}</td>
                <td>{{ $a->Ajuste_inventario_motivo }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 15px; color: #64748b;">
                    No se encontraron registros de ajustes en el período especificado.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr>
            <td>
                <div class="signature-line">JEFE DE ALMACÉN Y CONTROL DE STOCK</div>
                <div class="signature-role">Comercial Valencia S.A.C.</div>
            </td>
            <td>
                <div class="signature-line">ADMINISTRACIÓN / AUDITORÍA INTERNA</div>
                <div class="signature-role">Comercial Valencia S.A.C.</div>
            </td>
        </tr>
    </table>

</body>
</html>
