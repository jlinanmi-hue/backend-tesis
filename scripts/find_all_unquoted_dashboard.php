<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$allColumns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public'"))
    ->pluck('column_name')
    ->unique()
    ->filter(fn($c) => preg_match('/[A-Z]/', $c)) // only mixed-case or uppercase columns
    ->sortByDesc(fn($c) => strlen($c)) // longest first for replace
    ->values()
    ->toArray();

echo "Total mixed-case columns: " . count($allColumns) . "\n";

// Let's scan DashboardService.php for any unquoted occurrence of these columns in raw SQL
$content = file_get_contents(__DIR__ . '/../app/Services/DashboardService.php');
$lines = explode("\n", $content);

$toFix = [];
foreach ($lines as $lineNum => $line) {
    if (preg_match('/(DB::raw|selectRaw|whereRaw|orderByRaw|groupByRaw|havingRaw)\s*\((.+)\)/i', $line, $m)) {
        $expr = $m[2];
        foreach ($allColumns as $col) {
            // Check if $col appears without quotes
            // Pattern: not preceded by " or word char, not followed by " or word char
            if (preg_match('/(?<!["\w])(' . preg_quote($col, '/') . ')(?!["\w])/', $expr)) {
                $toFix[] = [
                    'line' => $lineNum + 1,
                    'col' => $col,
                    'text' => trim($line)
                ];
            }
        }
    }
}

echo "Found " . count($toFix) . " unquoted column instances in DashboardService.php:\n";
foreach ($toFix as $f) {
    echo "Line {$f['line']} [{$f['col']}]: {$f['text']}\n";
}
