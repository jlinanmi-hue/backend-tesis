<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = DB::select("SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema = 'public' AND (column_name ILIKE '%stock%' OR column_name ILIKE '%precio%') AND data_type LIKE '%char%'");
print_r($cols);
