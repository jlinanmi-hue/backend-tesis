<?php

$content = file_get_contents(__DIR__ . '/../app/Services/DashboardService.php');
$lines = explode("\n", $content);

$unquotedIdentifiers = [];

// Column names in Pedido, Producto, etc. that have mixed case
$knownColumns = [
    'PedidoFechaCreacion',
    'PedidoFecha_pedido',
    'PedidoFechaFinDespacho',
    'PedidoFechaInicioDespacho',
    'PedidoEstado_pedido',
    'PedidoEstadoDespacho',
    'PedidoEliminado',
    'PedidoTotal',
    'PedidoTotalDespachado',
    'PedidoTieneFaltante',
    'ProductoStockActual',
    'ProductoStockMinimo',
    'ProductoStockVirtual',
    'ProductoPrecioUnitario',
    'ProductoEliminado',
    'Movimiento_productoFecha_Movimiento',
    'Movimiento_productoCantidadEntrada',
    'Movimiento_productoCantidadSalida',
    'Movimiento_productoTipoMovimiento',
    'Detalle_Pedido_Productos_cantidad',
    'Detalle_Pedido_Productos_subtotal',
    'Detalle_Pedido_ProductosCantidadFaltanteRotura',
];

foreach ($lines as $idx => $line) {
    if (preg_match('/(DB::raw|selectRaw|whereRaw|orderByRaw|groupByRaw|havingRaw)\s*\((.+)\)/i', $line, $m)) {
        $expr = $m[2];
        foreach ($knownColumns as $col) {
            // Check if $col appears without quotes in the raw expression
            if (preg_match('/(?<!["\w])' . preg_quote($col, '/') . '(?!["\w])/', $expr)) {
                $unquotedIdentifiers[] = [
                    'line' => $idx + 1,
                    'col' => $col,
                    'text' => trim($line)
                ];
            }
        }
    }
}

echo "Found " . count($unquotedIdentifiers) . " unquoted column references in raw SQL in DashboardService.php:\n";
foreach ($unquotedIdentifiers as $u) {
    echo "Line {$u['line']} [{$u['col']}]: {$u['text']}\n";
}
