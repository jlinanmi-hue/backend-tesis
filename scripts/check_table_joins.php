<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pgTables = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"))
    ->pluck('table_name')
    ->toArray();

echo "PostgreSQL actual tables in public schema:\n";
print_r($pgTables);

// Scan app for DB::table, join, leftJoin, rightJoin
$dir = new RecursiveDirectoryIterator(__DIR__ . '/../app');
$iterator = new RecursiveIteratorIterator($dir);

$mismatches = [];

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    
    // Match DB::table('TableName') or ->join('TableName') or ->leftJoin('TableName')
    if (preg_match_all("/(?:DB::table|->join|->leftJoin|->rightJoin)\s*\(\s*['\"]([^'\"]+)['\"]/i", $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $idx => $match) {
            $rawTableRef = trim($match[0]);
            // Strip alias: e.g. "Canal_Pedido as cp" -> "Canal_Pedido"
            $tableName = preg_split('/\s+(?:as\s+)?/i', $rawTableRef)[0];
            
            // Check if $tableName is in $pgTables (exact case match)
            if (!in_array($tableName, $pgTables)) {
                // Find line number
                $offset = $match[1];
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                $mismatches[] = [
                    'file' => str_replace('\\', '/', $file->getPathname()),
                    'line' => $line,
                    'referenced' => $tableName,
                    'raw' => $rawTableRef,
                ];
            }
        }
    }
}

echo "\nTABLE CASING MISMATCHES IN CODE:\n";
foreach ($mismatches as $m) {
    echo "{$m['file']}:{$m['line']} -> '{$m['raw']}' (table '{$m['referenced']}' NOT found in PostgreSQL!)\n";
}
