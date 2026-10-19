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
            // Orden_Compra
            'CREATE INDEX IF NOT EXISTS idx_orden_compra_fecha ON "Orden_Compra" ("Orden_CompraFecha" DESC)',
            'CREATE INDEX IF NOT EXISTS idx_orden_compra_estado_elim ON "Orden_Compra" ("Orden_CompraEstado", "Orden_CompraEliminado")',
            'CREATE INDEX IF NOT EXISTS idx_orden_compra_prov ON "Orden_Compra" ("Orden_Compra_ProveedorId")',

            // Detalle_Orden_Compra
            'CREATE INDEX IF NOT EXISTS idx_doc_orden_id ON "Detalle_Orden_Compra" ("Detalle_Orden_Compra_Orden_CompraId")',
            'CREATE INDEX IF NOT EXISTS idx_doc_prod_id ON "Detalle_Orden_Compra" ("Detalle_ProductoId")',

            // Detalle_Pedido_Productos
            'CREATE INDEX IF NOT EXISTS idx_dpp_pedido_id ON "Detalle_Pedido_Productos" ("Detalle_Pedido_Productos_PedidoId")',
            'CREATE INDEX IF NOT EXISTS idx_dpp_producto_id ON "Detalle_Pedido_Productos" ("Detalle_Pedido_Productos_ProductoId")',

            // Pedido
            'CREATE INDEX IF NOT EXISTS idx_pedido_fecha ON "Pedido" ("PedidoFecha_pedido" DESC)',
            'CREATE INDEX IF NOT EXISTS idx_pedido_fechacreacion ON "Pedido" ("PedidoFechaCreacion" DESC)',
            'CREATE INDEX IF NOT EXISTS idx_pedido_estado_elim ON "Pedido" ("PedidoEstado_pedido", "PedidoEliminado")',
            'CREATE INDEX IF NOT EXISTS idx_pedido_cliente ON "Pedido" ("Pedido_ClienteId")',

            // Movimiento_producto
            'CREATE INDEX IF NOT EXISTS idx_mov_prod_compuesto ON "Movimiento_producto" ("Movimiento_producto_ProductoId", "Movimiento_productoFecha_Movimiento" DESC)',

            // Notificacion
            'CREATE INDEX IF NOT EXISTS idx_notif_fecha ON "Notificacion" ("NotificacionFecha" DESC)',
            'CREATE INDEX IF NOT EXISTS idx_notif_leida ON "Notificacion" ("NotificacionLeida", "NotificacionEliminado")',
        ];

        foreach ($indexes as $sql) {
            DB::statement($sql);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'idx_orden_compra_fecha',
            'idx_orden_compra_estado_elim',
            'idx_orden_compra_prov',
            'idx_doc_orden_id',
            'idx_doc_prod_id',
            'idx_dpp_pedido_id',
            'idx_dpp_producto_id',
            'idx_pedido_fecha',
            'idx_pedido_fechacreacion',
            'idx_pedido_estado_elim',
            'idx_pedido_cliente',
            'idx_mov_prod_compuesto',
            'idx_notif_fecha',
            'idx_notif_leida',
        ];

        foreach ($indexes as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
