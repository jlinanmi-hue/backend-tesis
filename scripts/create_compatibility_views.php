<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$views = [
    'Canal_Pedido' => 'Canal_pedido',
    'Unidades_Medida' => 'unidades_medida',
    'Categoria_producto' => 'Categoria_Producto',
    'Detalle_Producto_Medida' => 'Detalle_Producto_medida',
    'Movimiento_Producto' => 'Movimiento_producto',
];

foreach ($views as $alias => $target) {
    try {
        DB::statement("CREATE OR REPLACE VIEW \"{$alias}\" AS SELECT * FROM \"{$target}\"");
        echo "✅ Created view \"{$alias}\" -> \"{$target}\"\n";
    } catch (\Throwable $e) {
        echo "❌ Failed to create view \"{$alias}\": " . $e->getMessage() . "\n";
    }
}
