<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../app');
$iterator = new RecursiveIteratorIterator($dir);

$issues = [];
$patterns = [
    'Canal_Pedido' => 'Casing mismatch Canal_Pedido (table is Canal_pedido)',
    'Categoria_producto' => 'Casing mismatch Categoria_producto (table is Categoria_Producto)',
    'Detalle_Producto_Medida' => 'Casing mismatch Detalle_Producto_Medida',
    'Movimiento_Producto' => 'Casing mismatch Movimiento_Producto',
    'ISNULL' => 'T-SQL ISNULL (use COALESCE)',
    'GETDATE' => 'T-SQL GETDATE (use NOW())',
    'DATEDIFF' => 'T-SQL DATEDIFF',
    'DATEPART' => 'T-SQL DATEPART',
    'LEN(' => 'T-SQL LEN',
    'whereRaw' => 'Raw WHERE clause - check quoting',
    'selectRaw' => 'Raw SELECT clause - check quoting',
    'orderByRaw' => 'Raw ORDER BY clause - check quoting',
    'DB::raw' => 'Raw DB expression - check quoting',
];

$rawLines = [];

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }

    $lines = file($file->getPathname());
    foreach ($lines as $lineNum => $line) {
        foreach ($patterns as $pattern => $desc) {
            if (stripos($line, $pattern) !== false) {
                $rawLines[] = [
                    'file' => str_replace('\\', '/', $file->getPathname()),
                    'line' => $lineNum + 1,
                    'pattern' => $pattern,
                    'text' => trim($line)
                ];
            }
        }
    }
}

file_put_contents(__DIR__ . '/../raw_audit_results.json', json_encode($rawLines, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Found " . count($rawLines) . " matching lines. Saved to raw_audit_results.json\n";
