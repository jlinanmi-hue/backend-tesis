<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'Producto' AND column_name LIKE '%Stock%'");
print_r($cols);
