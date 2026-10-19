<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Get all table names and column names from information_schema
$dbTables = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"))
    ->pluck('table_name')
    ->filter(fn($t) => preg_match('/[A-Z]/', $t))
    ->values()
    ->toArray();

$dbColumns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public'"))
    ->pluck('column_name')
    ->unique()
    ->filter(fn($c) => preg_match('/[A-Z]/', $c))
    ->sortByDesc(fn($c) => strlen($c))
    ->values()
    ->toArray();

echo "Auditing against " . count($dbTables) . " mixed-case tables and " . count($dbColumns) . " mixed-case columns...\n";

$dir = new RecursiveDirectoryIterator(__DIR__ . '/../app');
$iterator = new RecursiveIteratorIterator($dir);

$issues = [];

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }

    $filepath = $file->getPathname();
    $content = file_get_contents($filepath);
    $tokens = token_get_all($content);

    $inRawCall = false;
    $parenDepth = 0;
    $currentFunc = '';
    $rawCalls = ['raw', 'whereraw', 'selectraw', 'orderbyraw', 'groupbyraw', 'havingraw'];

    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];

        if (is_array($token)) {
            $tokenName = token_name($token[0]);
            $tokenText = $token[1];
            $line = $token[2];

            if ($token[0] === T_STRING && in_array(strtolower($tokenText), $rawCalls)) {
                // Check if next non-whitespace is '('
                $j = $i + 1;
                while ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if ($j < count($tokens) && $tokens[$j] === '(') {
                    $inRawCall = true;
                    $parenDepth = 0;
                    $currentFunc = $tokenText;
                }
            } elseif ($inRawCall && ($token[0] === T_CONSTANT_ENCAPSED_STRING || $token[0] === T_ENCAPSED_AND_WHITESPACE)) {
                // Check string literal for unquoted column / table names
                $sqlSnippet = $tokenText;

                foreach ($dbColumns as $col) {
                    // Look for $col preceded not by " or ' or letter/digit/_, and followed not by " or ' or letter/digit/_
                    // Also check if it's not prefixed with alias or dot like a."Col" vs a.Col
                    if (preg_match('/(?<!["\'`\w])' . preg_quote($col, '/') . '(?!["\'`\w])/', $sqlSnippet)) {
                        $issues[] = [
                            'file' => str_replace('\\', '/', $filepath),
                            'line' => $line,
                            'func' => $currentFunc,
                            'target' => "column: $col",
                            'snippet' => trim($sqlSnippet),
                        ];
                    }
                }
            }
        } else {
            if ($inRawCall) {
                if ($token === '(') {
                    $parenDepth++;
                } elseif ($token === ')') {
                    $parenDepth--;
                    if ($parenDepth <= 0) {
                        $inRawCall = false;
                    }
                }
            }
        }
    }
}

echo "Total raw issues found: " . count($issues) . "\n";
foreach ($issues as $issue) {
    echo "{$issue['file']}:{$issue['line']} [{$issue['func']}] -> {$issue['target']} in {$issue['snippet']}\n";
}
