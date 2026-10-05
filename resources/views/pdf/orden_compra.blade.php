<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra - {{ $orden->Orden_CompraId }}</title>
    <style>
        @page {
            margin: 12mm 15mm 15mm 15mm;
            size: A4 portrait;
        }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            color: #000000;
            line-height: 1.25;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* 1. Cabecera Superior: Código de Barras y Metadatos */
        .top-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .top-meta-table td {
            vertical-align: top;
        }
        .barcode-box {
            text-align: left;
        }
        .barcode-bars {
            display: inline-block;
            font-size: 26px;
            letter-spacing: 2px;
            font-weight: normal;
            line-height: 1;
        }
        .barcode-text {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-top: 2px;
        }
        .meta-text-box {
            text-align: right;
            font-size: 10px;
            line-height: 1.35;
        }

        /* 2. Título Central */
        .title-section {
            text-align: center;
            margin-bottom: 14px;
        }
        .title-text {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .subtitle-text {
            font-size: 11px;
            font-weight: bold;
            margin: 0;
        }

        /* 3. Ficha de la Entidad / Proveedor */
        .entity-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 11px;
        }
        .entity-table td {
            padding: 1.5px 0;
            vertical-align: top;
        }
        .lbl-bold {
            font-weight: bold;
        }

        /* 4. Tabla de Productos Oficial */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-bottom: 12px;
        }
        .items-table thead tr {
            border-top: 1px solid #000000;
            border-bottom: 2px solid #000000;
        }
        .items-table th {
            padding: 5px 4px;
            font-weight: bold;
            text-align: left;
        }
        .items-table td {
            padding: 4px 4px;
            vertical-align: top;
        }
        .items-table tbody tr.border-bottom-final {
            border-bottom: 1px solid #000000;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }

        /* 5. Totales a la Derecha */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 11px;
        }
        .totals-table td {
            padding: 2px 4px;
        }
        .totals-label {
            text-align: right;
            font-weight: bold;
            width: 82%;
        }
        .totals-val {
            text-align: right;
            font-weight: bold;
            width: 18%;
        }

        /* Observaciones al pie */
        .obs-box {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px dashed #666666;
            font-size: 9.5px;
            color: #333333;
        }
    </style>
</head>
<body>
@php
    $emp = $empresa ?? \App\Models\Empresa::getDatos();
    $nombreEmpresa = $emp?->EmpresaNombreComercial ?: ($emp?->EmpresaRazonSocial ?: 'COMERCIAL VALENCIA');
    $rucEmpresa = $emp?->EmpresaRuc ?: '10181935451';
    $dirEmpresa = $emp?->EmpresaDireccion ?: 'Av. Principal America 178';

    $prov = $orden->proveedor;
    $provNombre = $prov?->ProveedorRazonSocial ?: $nombreEmpresa;
    $provRuc = $prov?->ProveedorRuc ?: $rucEmpresa;
    $provDir = $prov?->ProveedorDireccion ?: $dirEmpresa;

    $fechaDoc = $orden->Orden_CompraFecha ? $orden->Orden_CompraFecha->format('Y-m-d') : now()->format('Y-m-d');
    $usuario = $orden->Orden_CompraUsuarioCreacion ?: 'ADMIN';
    $fechaImpresion = now()->translatedFormat('d M Y, h:i:s a');

    $subtotal = (float) ($orden->Orden_CompraSubtotal ?? 0);
    $igv = (float) ($orden->Orden_CompraIgv ?? 0);
    $total = (float) ($orden->Orden_CompraTotal ?? 0);
    if ($total > 0 && $subtotal == 0) {
        $subtotal = round($total / 1.18, 2);
        $igv = round($total - $subtotal, 2);
    }
@endphp

    <!-- 1. CÓDIGO DE BARRAS Y METADATOS SUPERIORES -->
    <table class="top-meta-table">
        <tr>
            <td style="width: 50%;" class="barcode-box">
                <!-- Representación limpia de barras vectoriales -->
                <svg height="26" width="160" style="display:block;">
                    <rect x="0" y="0" width="3" height="26" fill="black" />
                    <rect x="5" y="0" width="1" height="26" fill="black" />
                    <rect x="8" y="0" width="4" height="26" fill="black" />
                    <rect x="14" y="0" width="2" height="26" fill="black" />
                    <rect x="18" y="0" width="1" height="26" fill="black" />
                    <rect x="22" y="0" width="3" height="26" fill="black" />
                    <rect x="27" y="0" width="2" height="26" fill="black" />
                    <rect x="31" y="0" width="1" height="26" fill="black" />
                    <rect x="35" y="0" width="4" height="26" fill="black" />
                    <rect x="42" y="0" width="2" height="26" fill="black" />
                    <rect x="46" y="0" width="3" height="26" fill="black" />
                    <rect x="51" y="0" width="1" height="26" fill="black" />
                    <rect x="55" y="0" width="2" height="26" fill="black" />
                    <rect x="60" y="0" width="4" height="26" fill="black" />
                    <rect x="66" y="0" width="1" height="26" fill="black" />
                    <rect x="70" y="0" width="3" height="26" fill="black" />
                    <rect x="75" y="0" width="2" height="26" fill="black" />
                    <rect x="80" y="0" width="1" height="26" fill="black" />
                    <rect x="84" y="0" width="3" height="26" fill="black" />
                    <rect x="89" y="0" width="2" height="26" fill="black" />
                    <rect x="94" y="0" width="4" height="26" fill="black" />
                    <rect x="100" y="0" width="1" height="26" fill="black" />
                    <rect x="104" y="0" width="2" height="26" fill="black" />
                    <rect x="108" y="0" width="4" height="26" fill="black" />
                    <rect x="114" y="0" width="1" height="26" fill="black" />
                    <rect x="118" y="0" width="3" height="26" fill="black" />
                </svg>
                <div class="barcode-text">*{{ $orden->Orden_CompraId }}*</div>
            </td>
            <td style="width: 50%;" class="meta-text-box">
                <div>Página: 1 de 1</div>
                <div>Impreso: {{ $fechaImpresion }}</div>
                <div>Usuario: {{ $usuario }}</div>
            </td>
        </tr>
    </table>

    <!-- 2. TÍTULO CENTRAL -->
    <div class="title-section">
        <h1 class="title-text">ORDEN DE COMPRA</h1>
        <div class="subtitle-text">FECHA: {{ $fechaDoc }} &nbsp;&nbsp;&nbsp;&nbsp; Nº: {{ $orden->Orden_CompraId }}</div>
    </div>

    <!-- 3. DATOS DE LA ENTIDAD / PROVEEDOR -->
    <table class="entity-table">
        <tr>
            <td style="width: 70%;">
                <span class="lbl-bold">Señores:</span> {{ $provNombre }}
            </td>
            <td style="width: 30%; text-align: right;">
                <span class="lbl-bold">Fecha:</span> {{ $fechaDoc }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="lbl-bold">R.U.C.:</span> {{ $provRuc }}
            </td>
            <td style="text-align: right;">
                <span class="lbl-bold">{{ $usuario }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="lbl-bold">Dirección:</span> {{ $provDir }}
            </td>
        </tr>
    </table>

    <!-- 4. TABLA EXACTA DEL FORMATO FÍSICO OFICIAL -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 15%;">CODIGO</th>
                <th style="width: 9%; text-align: right;">CANTID</th>
                <th style="width: 12%; text-align: left;">MEDIDA</th>
                <th style="width: 36%;">DESCRIPCION DEL PRODUCTO</th>
                <th style="width: 6%; text-align: center;">SUC</th>
                <th style="width: 11%; text-align: right;">PRECIO</th>
                <th style="width: 11%; text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
        @forelse($orden->detalles as $index => $det)
            @php
                $esUltimo = $loop->last;
                $cant = (float) $det->Detalle_Orden_CompraCantidad;
                $precio = (float) $det->Detalle_Orden_CompraPrecioUnitario;
                $tot = (float) ($det->Detalle_Orden_CompraSubtotal ?? ($cant * $precio));
                $abrev = $det->unidadMedida?->unidades_medidaAbreviatura ?? 'UND';
                $prod = $det->producto;
                $nombreProd = strtoupper($prod?->ProductoNombre ?? 'PRODUCTO');
                if (!empty($prod?->ProductoMarca)) {
                    $nombreProd .= ' - ' . strtoupper($prod->ProductoMarca);
                }
            @endphp
            <tr class="{{ $esUltimo ? 'border-bottom-final' : '' }}">
                <td class="text-left">{{ $det->Detalle_ProductoId }}</td>
                <td class="text-right">{{ number_format($cant, 2) }}</td>
                <td class="text-left">{{ strtoupper($abrev) }}</td>
                <td class="text-left">{{ $nombreProd }}</td>
                <td class="text-center">AC</td>
                <td class="text-right">{{ number_format($precio, 2) }}</td>
                <td class="text-right">{{ number_format($tot, 2) }}</td>
            </tr>
        @empty
            <tr class="border-bottom-final">
                <td colspan="7" class="text-center" style="padding: 12px 0;">--- Sin ítems registrados en esta orden ---</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <!-- 5. TOTALES EXACTOS ESCALONADOS A LA DERECHA -->
    <table class="totals-table">
        <tr>
            <td class="totals-label">TOTAL:</td>
            <td class="totals-val">{{ number_format($subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="totals-label">TOTAL IGV:</td>
            <td class="totals-val">{{ number_format($igv, 2) }}</td>
        </tr>
        <tr>
            <td class="totals-label">TOTAL GENERAL:</td>
            <td class="totals-val">{{ number_format($total, 2) }}</td>
        </tr>
    </table>

    @if(!empty($orden->Orden_CompraObservacion))
    <div class="obs-box">
        <strong>Observaciones / Instrucciones:</strong> {{ $orden->Orden_CompraObservacion }}
    </div>
    @endif
</body>
</html>
