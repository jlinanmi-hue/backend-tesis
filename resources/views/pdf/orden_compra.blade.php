<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra - {{ $orden->Orden_CompraId }}</title>
    <style>
        @page {
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* Contenedor tipo Tarjeta Web */
        .card-container {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            background-color: #ffffff;
        }

        /* Encabezado */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .company-subtitle {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }

        .order-badge-box {
            text-align: right;
        }
        .order-badge-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .order-badge-code {
            font-size: 17px;
            font-weight: 800;
            color: #2563eb;
            margin-top: 2px;
        }
        .order-badge-date {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }

        /* Mensaje formal */
        .statement-banner {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 14px;
            padding: 8px 12px;
            background-color: #f8fafc;
            border-left: 3px solid #2563eb;
            border-radius: 0 6px 6px 0;
        }

        /* Ficha Proveedor (Estilo Web Modal) */
        .info-card {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-card td {
            padding: 10px 14px;
            vertical-align: top;
        }
        .info-label {
            font-size: 9.5px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.3px;
            display: block;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        /* Badge de Estado */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            font-size: 10px;
            font-weight: 700;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .status-P {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .status-C {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .status-A {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* Tabla de Productos */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 9px 10px;
            border-bottom: 1px solid #cbd5e1;
        }
        .items-table td {
            padding: 8px 10px;
            font-size: 10.5px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* Resumen Financiero */
        .summary-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .summary-wrapper td {
            vertical-align: top;
        }
        .observation-box {
            font-size: 10px;
            color: #475569;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-right: 20px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 4px 8px;
            font-size: 11px;
            text-align: right;
        }
        .totals-table .total-label {
            color: #64748b;
            font-weight: 500;
        }
        .totals-table .total-amount {
            color: #0f172a;
            font-weight: 700;
        }
        .totals-table tr.grand-total td {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #e2e8f0;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .totals-table tr.grand-total .total-amount {
            color: #2563eb;
        }

        /* Pie de página */
        .footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="card-container">
@php
    $emp = $empresa ?? \App\Models\Empresa::getDatos();
    $nombreEmpresa = $emp?->EmpresaNombreComercial ?: ($emp?->EmpresaRazonSocial ?: 'COMERCIAL VALENCIA');
    $rucEmpresa = $emp?->EmpresaRuc ?: '10181935451';
    $telEmpresa = $emp?->EmpresaTelefono ?: ($emp?->EmpresaWhatsapp ?: '(01) 456-7890');
    $dirEmpresa = $emp?->EmpresaDireccion ?: 'Av. Principal 123, Lima - Perú';
@endphp
        <!-- Encabezado -->
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="company-title">{{ $nombreEmpresa }}</div>
                    <div class="company-subtitle">
                        RUC: {{ $rucEmpresa }} &nbsp;•&nbsp; Tel: {{ $telEmpresa }}<br>
                        {{ $dirEmpresa }}
                    </div>
                </td>
                <td style="width: 40%;" class="order-badge-box">
                    <div class="order-badge-title">Orden de Compra</div>
                    <div class="order-badge-code">{{ $orden->Orden_CompraId }}</div>
                    <div class="order-badge-date">
                        Fecha: {{ $orden->Orden_CompraFecha ? $orden->Orden_CompraFecha->format('d/m/Y H:i') : now()->format('d/m/Y') }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- Frase Formal -->
        <div class="statement-banner">
            Solicito a usted la atención del siguiente pedido de compra:
        </div>

        <!-- Ficha de Datos del Proveedor y Estado (Igual que en la interfaz web) -->
        <table class="info-card">
            <tr>
                <td style="width: 40%;">
                    <span class="info-label">Proveedor:</span>
                    <span class="info-value">{{ $orden->proveedor?->ProveedorRazonSocial ?? 'Por asignar' }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="info-label">RUC:</span>
                    <span class="info-value">{{ $orden->proveedor?->ProveedorRuc ?? '-' }}</span>
                </td>
                <td style="width: 20%;">
                    <span class="info-label">Teléfono:</span>
                    <span class="info-value">+51 {{ $orden->proveedor?->ProveedorTelefono ?? '-' }}</span>
                </td>
                <td style="width: 15%;" class="text-right">
                    <span class="info-label">Estado:</span>
                    <div style="margin-top: 2px;">
                        <span class="status-badge status-{{ $orden->Orden_CompraEstado }}">
                            {{ $orden->Orden_CompraEstado === 'P' ? 'Pendiente' : ($orden->Orden_CompraEstado === 'C' ? 'Atendida' : 'Anulada') }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Tabla de Productos Solicitados -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 45%;" class="text-left">Producto</th>
                    <th style="width: 15%;" class="text-center">Unidad</th>
                    <th style="width: 12%;" class="text-center">Cantidad</th>
                    <th style="width: 14%;" class="text-right">P. Unitario</th>
                    <th style="width: 14%;" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orden->detalles as $item)
                <tr>
                    <td class="text-left">
                        <strong style="color: #0f172a;">{{ $item->producto?->ProductoNombre ?? 'Producto no especificado' }}</strong>
                        @if(!empty($item->producto?->ProductoMarca))
                            <span style="font-size: 9px; color: #64748b;"> ({{ $item->producto->ProductoMarca }})</span>
                        @endif
                    </td>
                    <td class="text-center" style="color: #475569;">
                        {{ $item->unidadMedida?->unidades_medidaAbreviatura ?? ($item->unidadMedida?->unidades_medidaDescripcionUnidades ?? 'UND') }}
                    </td>
                    <td class="text-center" style="font-weight: 700; color: #0f172a;">
                        {{ number_format($item->Detalle_Orden_CompraCantidad, 2) }}
                    </td>
                    <td class="text-right" style="color: #475569;">
                        S/ {{ number_format($item->Detalle_Orden_CompraPrecioUnitario, 2) }}
                    </td>
                    <td class="text-right" style="font-weight: 700; color: #0f172a;">
                        S/ {{ number_format($item->Detalle_Orden_CompraSubtotal, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Resumen Financiero y Observación -->
        <table class="summary-wrapper">
            <tr>
                <td style="width: 55%;">
                    @if(!empty($orden->Orden_CompraObservacion) && !str_contains($orden->Orden_CompraObservacion, 'WhatsApp enviado'))
                        <div class="observation-box">
                            <strong>Observación:</strong> {{ $orden->Orden_CompraObservacion }}
                        </div>
                    @endif
                </td>
                <td style="width: 45%;">
                    <table class="totals-table">
                        <tr>
                            <td class="total-label">Subtotal:</td>
                            <td class="total-amount">S/ {{ number_format($orden->Orden_CompraSubtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="total-label">IGV (18%):</td>
                            <td class="total-amount">S/ {{ number_format($orden->Orden_CompraIgv, 2) }}</td>
                        </tr>
                        <tr class="grand-total">
                            <td class="total-label" style="font-weight: 800; color: #0f172a;">TOTAL:</td>
                            <td class="total-amount">S/ {{ number_format($orden->Orden_CompraTotal, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Pie de página simplificado y moderno -->
        <div class="footer">
            Emitido el {{ now()->format('d/m/Y H:i') }} &nbsp;•&nbsp; {{ $nombreEmpresa }} &nbsp;•&nbsp; Documento Oficial de Solicitud de Compra
        </div>
    </div>
</body>
</html>
