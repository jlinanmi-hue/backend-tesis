<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Consolidado de Órdenes de Compra</title>
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
            border: 2px solid #1e3a8a;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
            background-color: #f8fafc;
        }
        .report-card .title {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
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
            background-color: #ffffff;
        }
        .kpi-box .val {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .kpi-box .val.accent {
            color: #1e3a8a;
        }
        .kpi-box .val.pending {
            color: #d97706;
        }
        .kpi-box .val.completed {
            color: #16a34a;
        }
        .kpi-box .val.canceled {
            color: #dc2626;
        }
        .kpi-box .lbl {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
            margin-top: 2px;
            font-weight: bold;
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
            font-size: 8.5px;
            font-weight: bold;
            padding: 5px 6px;
            text-align: left;
            text-transform: uppercase;
            border: 1px solid #1e3a8a;
        }
        .data-table td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            font-size: 8.5px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .badge-completed {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-canceled {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .footer {
            margin-top: 20px;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            font-size: 8px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
@php
    $emp = $empresa ?? \App\Models\Empresa::getDatos();
    $nombreComercial = $emp?->EmpresaNombreComercial ?: ($emp?->EmpresaRazonSocial ?: 'COMERCIAL VALENCIA');
    $razonSocial = $emp?->EmpresaRazonSocial ?: $nombreComercial;
    $rucEmpresa = $emp?->EmpresaRuc ?: '10181935451';
    $dirEmpresa = $emp?->EmpresaDireccion ?: 'Av. Principal 123, Lima - Perú';
    $telEmpresa = $emp?->EmpresaTelefono ?: ($emp?->EmpresaWhatsapp ?: '(01) 456-7890');
    $emailEmpresa = $emp?->EmpresaEmail ?: 'ventas@comercialvalencia.pe';
@endphp

    <!-- Header Corporativo -->
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="company-title">{{ $nombreComercial }}</div>
                <div class="company-subtitle">{{ $razonSocial }} | Gestión Logística y Abastecimiento</div>
                <div class="company-subtitle">RUC: {{ $rucEmpresa }} | {{ $dirEmpresa }}</div>
                <div class="company-subtitle">Teléfono: {{ $telEmpresa }} | Correo: {{ $emailEmpresa }}</div>
            </td>
            <td style="width: 40%;">
                <div class="report-card">
                    <div class="title">REPORTE CONSOLIDADO DE COMPRAS</div>
                    <div class="date">Generado: {{ now()->format('d/m/Y H:i:s') }}</div>
                    @if(!empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta']))
                        <div class="date" style="font-weight: bold; color: #1e3a8a;">
                            Periodo: {{ $filtros['fecha_desde'] ?? 'Inicio' }} al {{ $filtros['fecha_hasta'] ?? 'Actual' }}
                        </div>
                    @else
                        <div class="date">Histórico Completo</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Tarjetas de KPIs Consolidados -->
    <table class="kpi-table">
        <tr>
            <td style="width: 20%;">
                <div class="kpi-box">
                    <div class="val accent">{{ $kpis['total_ordenes'] }}</div>
                    <div class="lbl">Total Órdenes</div>
                </div>
            </td>
            <td style="width: 20%;">
                <div class="kpi-box">
                    <div class="val">S/ {{ number_format($kpis['total_subtotal'], 2) }}</div>
                    <div class="lbl">Subtotal Acumulado</div>
                </div>
            </td>
            <td style="width: 20%;">
                <div class="kpi-box">
                    <div class="val">S/ {{ number_format($kpis['total_igv'], 2) }}</div>
                    <div class="lbl">IGV (18%) Acumulado</div>
                </div>
            </td>
            <td style="width: 20%;">
                <div class="kpi-box">
                    <div class="val accent">S/ {{ number_format($kpis['total_comprado'], 2) }}</div>
                    <div class="lbl">Total Comprometido S/</div>
                </div>
            </td>
            <td style="width: 20%;">
                <div class="kpi-box">
                    <div class="val">
                        <span class="pending">{{ $kpis['por_estado']['P']['cantidad'] }} P</span> / 
                        <span class="completed">{{ $kpis['por_estado']['C']['cantidad'] }} C</span> / 
                        <span class="canceled">{{ $kpis['por_estado']['A']['cantidad'] }} A</span>
                    </div>
                    <div class="lbl">Distribución Estados</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Desglose por Proveedor -->
    @if(!empty($kpis['por_proveedor']))
    <div class="section-title">Resumen por Proveedor</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Código Prov.</th>
                <th style="width: 38%;">Razón Social</th>
                <th style="width: 15%;">RUC</th>
                <th style="width: 15%;">Teléfono</th>
                <th class="text-center" style="width: 10%;">Cant. Órdenes</th>
                <th class="text-right" style="width: 10%;">Total S/</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kpis['por_proveedor'] as $prov)
            <tr>
                <td class="text-center" style="font-weight: bold;">{{ $prov['proveedor_id'] }}</td>
                <td>{{ $prov['razon_social'] }}</td>
                <td>{{ $prov['ruc'] }}</td>
                <td>{{ $prov['telefono'] }}</td>
                <td class="text-center">{{ $prov['cantidad_ordenes'] }}</td>
                <td class="text-right" style="font-weight: bold;">S/ {{ number_format($prov['total_soles'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Detalle Cronológico de Órdenes -->
    <div class="section-title">Cronológico de Órdenes de Compra</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">Código</th>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 25%;">Proveedor</th>
                <th style="width: 12%;">RUC</th>
                <th class="text-center" style="width: 7%;">Ítems</th>
                <th class="text-right" style="width: 10%;">Subtotal</th>
                <th class="text-right" style="width: 8%;">IGV</th>
                <th class="text-right" style="width: 10%;">Total</th>
                <th class="text-center" style="width: 8%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ordenes as $ord)
            <tr>
                <td style="font-weight: bold; color: #1e3a8a;">{{ $ord->Orden_CompraId }}</td>
                <td>{{ $ord->Orden_CompraFecha ? $ord->Orden_CompraFecha->format('d/m/Y H:i') : '-' }}</td>
                <td>{{ $ord->proveedor->ProveedorRazonSocial ?? 'Sin Asignar' }}</td>
                <td>{{ $ord->proveedor->ProveedorRuc ?? '-' }}</td>
                <td class="text-center">{{ $ord->detalles->count() }}</td>
                <td class="text-right">S/ {{ number_format($ord->Orden_CompraSubtotal, 2) }}</td>
                <td class="text-right">S/ {{ number_format($ord->Orden_CompraIgv, 2) }}</td>
                <td class="text-right" style="font-weight: bold;">S/ {{ number_format($ord->Orden_CompraTotal, 2) }}</td>
                <td class="text-center">
                    @if($ord->Orden_CompraEstado === 'P')
                        <span class="badge badge-pending">Pendiente</span>
                    @elseif($ord->Orden_CompraEstado === 'C')
                        <span class="badge badge-completed">Completada</span>
                    @elseif($ord->Orden_CompraEstado === 'A')
                        <span class="badge badge-canceled">Anulada</span>
                    @else
                        <span class="badge">{{ $ord->Orden_CompraEstado }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 15px; color: #64748b;">
                    No se encontraron órdenes de compra en el periodo o con los filtros seleccionados.
                </td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="5" class="text-right">TOTALES DEL REPORTE:</td>
                <td class="text-right">S/ {{ number_format($kpis['total_subtotal'], 2) }}</td>
                <td class="text-right">S/ {{ number_format($kpis['total_igv'], 2) }}</td>
                <td class="text-right" style="color: #1e3a8a;">S/ {{ number_format($kpis['total_comprado'], 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table class="footer" style="width: 100%;">
        <tr>
            <td style="width: 50%;">
                Sistema de Control Logístico &copy; {{ date('Y') }} - {{ $nombreComercial }}
            </td>
            <td style="width: 50%; text-align: right;">
                Documento de Auditoría Interna | Página 1
            </td>
        </tr>
    </table>

</body>
</html>
