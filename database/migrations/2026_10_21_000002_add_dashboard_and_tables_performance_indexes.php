<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $indexes = [
            // Índices compuestos para Pedido (aceleran cálculo de PODE, PEOR, PRS, TBPP y filtros por fecha/estado)
            'CREATE INDEX IF NOT EXISTS idx_pedido_elim_fecha_estado ON "Pedido" ("PedidoEliminado", "PedidoFechaCreacion" DESC, "PedidoEstado_pedido");',
            'CREATE INDEX IF NOT EXISTS idx_pedido_elim_canal_fecha ON "Pedido" ("PedidoEliminado", "Pedido_canal_pedidoId", "PedidoFechaCreacion" DESC);',
            'CREATE INDEX IF NOT EXISTS idx_pedido_elim_despacho ON "Pedido" ("PedidoEliminado", "PedidoEstadoDespacho", "PedidoFechaCreacion" DESC);',
            'CREATE INDEX IF NOT EXISTS idx_pedido_elim_error ON "Pedido" ("PedidoEliminado", "PedidoTieneError", "PedidoFechaCreacion" DESC);',
            'CREATE INDEX IF NOT EXISTS idx_pedido_elim_faltante ON "Pedido" ("PedidoEliminado", "PedidoTieneFaltante", "PedidoFechaCreacion" DESC);',
            'CREATE INDEX IF NOT EXISTS idx_pedido_origen_ia ON "Pedido" ("PedidoEliminado", "PedidoOrigenIA", "PedidoFechaCreacion" DESC);',
            'CREATE INDEX IF NOT EXISTS idx_pedido_cliente_fecha ON "Pedido" ("Pedido_ClienteId", "PedidoFechaCreacion" DESC);',

            // Índice para Detalle_rotura_stock (acelera indicador PRS y fichas diarias)
            'CREATE INDEX IF NOT EXISTS idx_drs_pedido_id ON "detalle_rotura_stock" ("pedido_id");',

            // Índices para logs de IA (acelera calendario de dificultades y PEOR)
            'CREATE INDEX IF NOT EXISTS idx_ai_tool_logs_status_fecha ON "ai_tool_logs" ("status", "created_at");',
            'CREATE INDEX IF NOT EXISTS idx_ai_feedback_rating_fecha ON "ai_generation_feedback" ("rating", "created_at");',

            // Índices para Producto (acelera alertas de stock mínimo y filtros de categoría)
            'CREATE INDEX IF NOT EXISTS idx_producto_elim_stock ON "Producto" ("ProductoEliminado", "ProductoStockActual", "ProductoStockMinimo");',
            'CREATE INDEX IF NOT EXISTS idx_producto_elim_cat ON "Producto" ("ProductoEliminado", "Producto_Categoria_ProductoId");',
        ];

        foreach ($indexes as $sql) {
            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                // Silencioso para máxima compatibilidad si ya existiera
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'idx_pedido_elim_fecha_estado',
            'idx_pedido_elim_canal_fecha',
            'idx_pedido_elim_despacho',
            'idx_pedido_elim_error',
            'idx_pedido_elim_faltante',
            'idx_pedido_origen_ia',
            'idx_pedido_cliente_fecha',
            'idx_drs_pedido_id',
            'idx_ai_tool_logs_status_fecha',
            'idx_ai_feedback_rating_fecha',
            'idx_producto_elim_stock',
            'idx_producto_elim_cat',
        ];

        foreach ($indexes as $name) {
            try {
                DB::statement("DROP INDEX IF EXISTS \"{$name}\";");
            } catch (\Throwable $e) {
            }
        }
    }
};
