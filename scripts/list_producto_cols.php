<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'Producto' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo $c->column_name . "\n";
}
