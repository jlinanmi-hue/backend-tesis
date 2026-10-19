<?php

set_time_limit(0);
ini_set('memory_limit', '1024M');

$startTotal = microtime(true);

echo "=================================================================\n";
echo "    MIGRACIÓN TOTAL DE DATOS: SQL SERVER -> POSTGRESQL\n";
echo "=================================================================\n\n";

// 1. Conexiones
$pdoSql = new PDO("sqlsrv:Server=127.0.0.1,1433;Database=bdTesis;TrustServerCertificate=true", "laravel_user", "123", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdoPg = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=tesis_db", "usuario_app", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "✅ Conexiones a SQL Server y PostgreSQL activas.\n\n";

// 2. Orden topológico de tablas (respetando Foreign Keys)
$tables = [
    'Empresa',
    'cargo',
    'Rol_User',
    'Categoria_Producto',
    'unidades_medida',
    'producto_ubi',
    'Canal_pedido',
    'Zona_Delivery',
    'Proveedor',
    'Cliente',
    'Consulta_Ruc',
    'Empleado',
    'Usuario',
    'Producto',
    'Orden_Compra',
    'Producto_Proveedor',
    'Detalle_Producto_medida',
    'Detalle_Orden_Compra',
    'Pedido',
    'Ajuste_inventario',
    'Detalle_Pedido_Productos',
    'Movimiento_producto',
    'Notificacion',
    'auditoria_roturas_stock',
    'detalle_rotura_stock',
    'pedido_historial_correccion',
    'ai_generation_feedback',
    'ai_intent_logs',
    'ai_tool_logs'
];

$booleanColumns = [
    'unidades_medida.unidades_medidaEsBase',
    'ai_intent_logs.cache_hit',
    'ai_intent_logs.success',
    'ai_generation_feedback.es_correcto',
    'auditoria_roturas_stock.rompe_stock_seguridad'
];

$numericColumns = [
    'Movimiento_producto.Movimiento_productoCantidadEntrada',
    'Movimiento_producto.Movimiento_productoCantidadSalida',
    'Movimiento_producto.Movimiento_productoCantidadSaldo'
];

// 3. Limpiar datos demo en PostgreSQL
echo "Paso 1: Vaciando tablas demo en PostgreSQL (TRUNCATE CASCADE)...\n";
$reverseTables = array_reverse($tables);
foreach ($reverseTables as $t) {
    try {
        $pdoPg->exec("TRUNCATE TABLE \"$t\" CASCADE;");
    } catch (Exception $e) {
        echo "Aviso truncate en $t: " . $e->getMessage() . "\n";
    }
}
echo "✅ Tablas en PostgreSQL vaciadas exitosamente.\n\n";

// 4. Migración masiva tabla por tabla
echo "Paso 2: Transfiriendo todos los registros de SQL Server a PostgreSQL...\n";
echo sprintf("%-32s | %-12s | %-12s | %s\n", "TABLA", "ORIGEN (SQL)", "DESTINO (PG)", "TIEMPO");
echo str_repeat("-", 72) . "\n";

foreach ($tables as $t) {
    $t0 = microtime(true);
    
    // Contar total en SQL Server
    $countSql = (int) $pdoSql->query("SELECT COUNT(*) FROM [$t]")->fetchColumn();
    if ($countSql === 0) {
        $elapsed = round(microtime(true) - $t0, 2);
        echo sprintf("%-32s | %-12s | %-12s | %ss\n", $t, 0, 0, $elapsed);
        continue;
    }

    // Obtener nombres de columnas
    $stmtCols = $pdoSql->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$t'");
    $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    $numCols = count($cols);
    $quotedCols = array_map(fn($c) => "\"$c\"", $cols);
    $colList = implode(', ', $quotedCols);

    // Batch size dinámico que nunca exceda el límite de parámetros de PostgreSQL (65k)
    $batchSize = (int) min(500, floor(50000 / $numCols));
    if ($batchSize < 10) $batchSize = 10;

    $stmtSelect = $pdoSql->query("SELECT * FROM [$t]");
    
    $batch = [];
    $insertedCount = 0;

    $pdoPg->beginTransaction();

    while ($row = $stmtSelect->fetch(PDO::FETCH_ASSOC)) {
        $sanitizedRow = [];
        foreach ($row as $colName => $val) {
            $colKey = "$t.$colName";
            
            // Boolean mapping
            if (in_array($colKey, $booleanColumns)) {
                if ($val === null) {
                    $sanitizedRow[] = null;
                } else {
                    $sanitizedRow[] = ($val === 1 || $val === '1' || $val === true) ? 'true' : 'false';
                }
                continue;
            }

            // Numeric mapping (Kardex que era varchar en SQL Server)
            if (in_array($colKey, $numericColumns)) {
                if ($val === null || trim((string)$val) === '') {
                    $sanitizedRow[] = 0.00;
                } else {
                    $sanitizedRow[] = (float) trim((string)$val);
                }
                continue;
            }

            // Normalizar string vacío en campos de fecha o FK nullable
            if ($val === '') {
                $sanitizedRow[] = null;
                continue;
            }

            $sanitizedRow[] = $val;
        }

        $batch[] = $sanitizedRow;

        if (count($batch) >= $batchSize) {
            insertBatchHelper($pdoPg, $t, $colList, $numCols, $batch);
            $insertedCount += count($batch);
            $batch = [];
        }
    }

    if (!empty($batch)) {
        insertBatchHelper($pdoPg, $t, $colList, $numCols, $batch);
        $insertedCount += count($batch);
        $batch = [];
    }

    $pdoPg->commit();

    $elapsed = round(microtime(true) - $t0, 2);
    echo sprintf("%-32s | %-12d | %-12d | %ss\n", $t, $countSql, $insertedCount, $elapsed);
}

// 5. Sincronizar secuencias
echo "\nPaso 3: Sincronizando secuencias de PostgreSQL con los IDs migrados...\n";
$sequences = [
    'Consulta_Ruc' => ['ConsultaRucId', 'Consulta_Ruc_ConsultaRucId_seq'],
    'Detalle_Orden_Compra' => ['Detalle_Orden_CompraId', 'Detalle_Orden_Compra_Detalle_Orden_CompraId_seq'],
    'Detalle_Pedido_Productos' => ['Detalle_Pedido_ProductosId', 'Detalle_Pedido_Productos_Detalle_Pedido_ProductosId_seq'],
    'Empresa' => ['Id_Empresa', 'Empresa_Id_Empresa_seq'],
    'ai_tool_logs' => ['id', 'ai_tool_logs_id_seq'],
    'ai_intent_logs' => ['id', 'ai_intent_logs_id_seq'],
    'ai_generation_feedback' => ['id', 'ai_generation_feedback_id_seq'],
    'auditoria_roturas_stock' => ['id', 'auditoria_roturas_stock_id_seq'],
    'pedido_historial_correccion' => ['id', 'pedido_historial_correccion_id_seq'],
    'detalle_rotura_stock' => ['id', 'detalle_rotura_stock_id_seq']
];

foreach ($sequences as $tbl => [$col, $seq]) {
    try {
        $pdoPg->exec("SELECT setval('\"$seq\"', COALESCE((SELECT MAX(\"$col\") FROM \"$tbl\"), 1));");
        echo " - Secuencia $seq: OK\n";
    } catch (Exception $e) {
        echo " - Secuencia $seq (error): " . $e->getMessage() . "\n";
    }
}

// 6. Auditoría final de paridad
echo "\nPaso 4: Auditoría de paridad exacta...\n";
$allMatch = true;
$totalSql = 0;
$totalPg = 0;

foreach ($tables as $t) {
    $cSql = (int) $pdoSql->query("SELECT COUNT(*) FROM [$t]")->fetchColumn();
    $cPg  = (int) $pdoPg->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
    $totalSql += $cSql;
    $totalPg += $cPg;

    if ($cSql !== $cPg) {
        echo "❌ DISCREPANCIA EN $t: SQL=$cSql vs PG=$cPg\n";
        $allMatch = false;
    }
}

echo "Total registros SQL Server: $totalSql\n";
echo "Total registros PostgreSQL: $totalPg\n";

if ($allMatch && $totalSql === $totalPg) {
    echo "\n🎉 ¡ÉXITO TOTAL! Los $totalPg registros fueron transferidos con 100% de paridad exacta.\n";
} else {
    echo "\n⚠️ Hay discrepancias en algunas tablas. Revisar registros.\n";
}

$totalTime = round(microtime(true) - $startTotal, 2);
echo "Tiempo total transcurrido: {$totalTime}s\n";

function insertBatchHelper(PDO $pdoPg, string $table, string $colList, int $numCols, array $batch): void
{
    $placeholders = [];
    $values = [];

    $rowPlaceholder = '(' . implode(', ', array_fill(0, $numCols, '?')) . ')';
    foreach ($batch as $row) {
        $placeholders[] = $rowPlaceholder;
        foreach ($row as $val) {
            $values[] = $val;
        }
    }

    $sql = "INSERT INTO \"$table\" ($colList) VALUES " . implode(', ', $placeholders);
    $stmt = $pdoPg->prepare($sql);
    $stmt->execute($values);
}
