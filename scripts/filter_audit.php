<?php

$results = json_decode(file_get_contents(__DIR__ . '/../raw_audit_results.json'), true);
$filtered = [];
foreach ($results as $r) {
    $p = $r['pattern'];
    if (in_array($p, ['Canal_Pedido', 'Categoria_producto', 'Detalle_Producto_Medida', 'Movimiento_Producto', 'ISNULL', 'GETDATE', 'DATEDIFF', 'DATEPART', 'LEN('])) {
        // filter out comments
        if (str_contains($r['text'], '//') && strpos($r['text'], '//') < stripos($r['text'], $p)) {
            continue;
        }
        $filtered[] = $r;
    }
}

echo "Found " . count($filtered) . " high-priority issues:\n";
foreach ($filtered as $f) {
    echo "{$f['pattern']} in {$f['file']}:{$f['line']}\n  --> {$f['text']}\n";
}
