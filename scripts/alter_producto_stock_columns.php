<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stmts = [
    'ALTER TABLE "Producto" ALTER COLUMN "ProductoStockActual" TYPE numeric(18,2) USING NULLIF("ProductoStockActual", \'\')::numeric(18,2)',
    'ALTER TABLE "Producto" ALTER COLUMN "ProductoStockMinimo" TYPE numeric(18,2) USING NULLIF("ProductoStockMinimo", \'\')::numeric(18,2)',
    'ALTER TABLE "Producto" ALTER COLUMN "ProductoStockMaximo" TYPE numeric(18,2) USING NULLIF("ProductoStockMaximo", \'\')::numeric(18,2)',
];

foreach ($stmts as $s) {
    DB::statement($s);
    echo "Executed: $s\n";
}

echo "✅ Producto stock columns altered to numeric(18,2) successfully!\n";
