<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(App\Http\Controllers\DashboardController::class);

echo "--- Testing indicators() ---\n";
try {
    $res = $controller->indicators(request());
    $data = $res->getData();
    if ($res->getStatusCode() !== 200 || !($data->success ?? false)) {
        echo "❌ Status " . $res->getStatusCode() . ": " . ($data->message ?? 'fail') . "\n";
    } else {
        echo "✅ indicators() returned 200\n";
    }
} catch (\Throwable $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

for ($i = 1; $i <= 4; $i++) {
    echo "--- Testing detail({$i}) ---\n";
    try {
        $res = $controller->detail(request(), $i);
        $data = $res->getData();
        if ($res->getStatusCode() !== 200 || !($data->success ?? false)) {
            echo "❌ Detail {$i} Status " . $res->getStatusCode() . ": " . ($data->message ?? 'fail') . "\n";
        } else {
            echo "✅ Detail {$i} returned 200\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }
}
