<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columnas = [
        ['Producto', 'ProductoStockVirtual'],
        ['Producto', 'ProductoStockVirtualConsumido'],
        ['Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_virtual'],
        ['auditoria_roturas_stock', 'cantidad_virtual_usada'],
    ];

    public function up(): void
    {
        foreach ($this->columnas as [$tabla, $columna]) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, $columna)) {
                continue;
            }

            // 1. Eliminar DEFAULT constraints dinámicamente
            $constraints = DB::select("
                SELECT dc.name AS constraint_name
                FROM sys.default_constraints dc
                INNER JOIN sys.columns c
                    ON c.default_object_id = dc.object_id
                INNER JOIN sys.tables t
                    ON t.object_id = c.object_id
                WHERE t.name = ? AND c.name = ?
            ", [$tabla, $columna]);

            foreach ($constraints as $c) {
                DB::statement("ALTER TABLE [{$tabla}] DROP CONSTRAINT [{$c->constraint_name}]");
            }

            // 2. Eliminar índices asociados (si existen)
            $indices = DB::select("
                SELECT i.name AS index_name
                FROM sys.indexes i
                INNER JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
                INNER JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                INNER JOIN sys.tables t ON t.object_id = i.object_id
                WHERE t.name = ? AND c.name = ? AND i.is_primary_key = 0 AND i.is_unique_constraint = 0
            ", [$tabla, $columna]);

            foreach ($indices as $idx) {
                DB::statement("DROP INDEX [{$idx->index_name}] ON [{$tabla}]");
            }

            // 3. Drop column
            DB::statement("ALTER TABLE [{$tabla}] DROP COLUMN [{$columna}]");
        }
    }

    public function down(): void
    {
        // Re-crear columnas dormidas por si se requiere rollback
        if (Schema::hasTable('Producto')) {
            Schema::table('Producto', function ($t) {
                if (!Schema::hasColumn('Producto', 'ProductoStockVirtual')) {
                    $t->decimal('ProductoStockVirtual', 12, 2)->default(0.00);
                }
                if (!Schema::hasColumn('Producto', 'ProductoStockVirtualConsumido')) {
                    $t->decimal('ProductoStockVirtualConsumido', 12, 2)->default(0.00);
                }
            });
        }
        if (Schema::hasTable('Detalle_Pedido_Productos')) {
            Schema::table('Detalle_Pedido_Productos', function ($t) {
                if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_virtual')) {
                    $t->decimal('Detalle_Pedido_Productos_cantidad_virtual', 12, 2)->default(0.00);
                }
            });
        }
        if (Schema::hasTable('auditoria_roturas_stock')) {
            Schema::table('auditoria_roturas_stock', function ($t) {
                if (!Schema::hasColumn('auditoria_roturas_stock', 'cantidad_virtual_usada')) {
                    $t->decimal('cantidad_virtual_usada', 12, 2)->default(0.00);
                }
            });
        }
    }
};
