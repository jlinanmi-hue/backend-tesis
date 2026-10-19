<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../app');
$iterator = new RecursiveIteratorIterator($dir);

$knownColumns = [
    'PedidoId',
    'PedidoFechaCreacion',
    'PedidoFecha_pedido',
    'PedidoFechaFinDespacho',
    'PedidoFechaInicioDespacho',
    'PedidoFechaDespacho',
    'PedidoEstado_pedido',
    'PedidoEstadoDespacho',
    'PedidoEliminado',
    'PedidoTotal',
    'PedidoTotalDespachado',
    'PedidoTieneFaltante',
    'PedidoAcuerdo_Comercial',
    'Pedido_ClienteId',
    'Pedido_canal_pedidoId',
    'ProductoId',
    'ProductoNombre',
    'ProductoStockActual',
    'ProductoStockMinimo',
    'ProductoStockVirtual',
    'ProductoPrecioUnitario',
    'ProductoEliminado',
    'Movimiento_productoId',
    'Movimiento_productoFecha_Movimiento',
    'Movimiento_productoCantidadEntrada',
    'Movimiento_productoCantidadSalida',
    'Movimiento_productoTipoMovimiento',
    'Movimiento_productoEliminado',
    'Detalle_Pedido_ProductosId',
    'Detalle_Pedido_Productos_PedidoId',
    'Detalle_Pedido_Productos_ProductoId',
    'Detalle_Pedido_Productos_cantidad',
    'Detalle_Pedido_Productos_subtotal',
    'Detalle_Pedido_ProductosCantidadFaltanteRotura',
    'Detalle_Pedido_ProductosTuvoRotura',
    'ClienteId',
    'ClienteNombre',
    'ClienteRuc',
    'ClienteDni',
    'ProveedorId',
    'ProveedorRazonSocial',
    'ProveedorRuc',
    'Orden_compraId',
    'Orden_compraTotal',
    'Orden_compraEstado',
    'Orden_compraEliminado',
    'Canal_pedidoId',
    'Canal_pedidoDescripcion',
];

$allUnquoted = [];

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }

    if (str_contains($file->getFilename(), 'DashboardService')) {
        continue;
    }

    $lines = file($file->getPathname());
    foreach ($lines as $idx => $line) {
        if (preg_match('/(DB::raw|selectRaw|whereRaw|orderByRaw|groupByRaw|havingRaw)\s*\((.+)\)/i', $line, $m)) {
            $expr = $m[2];
            foreach ($knownColumns as $col) {
                if (preg_match('/(?<!["\'`\w])' . preg_quote($col, '/') . '(?!["\'`\w])/', $expr)) {
                    $allUnquoted[] = [
                        'file' => str_replace('\\', '/', $file->getPathname()),
                        'line' => $idx + 1,
                        'col' => $col,
                        'text' => trim($line)
                    ];
                }
            }
        }
    }
}

echo "Found " . count($allUnquoted) . " unquoted in other files:\n";
foreach ($allUnquoted as $u) {
    echo "{$u['file']}:{$u['line']} [{$u['col']}] -> {$u['text']}\n";
}
