<?php

/**
 * ⚠️ REGLA DEL PROYECTO:
 * Esta migración NO crea tablas nuevas ni foreign keys.
 * Solo agrega columnas a tablas YA EXISTENTES (Pedido, Detalle_Pedido_Productos)
 * y crea un índice de rendimiento (no es tabla).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // TABLA: Pedido — añadir campos de despacho
        // Solo columnas que NO existen ya. Se verifica antes de cada alter.
        // YA EXISTEN (no tocar): PedidoEstadoDespacho, PedidoFechaInicioPreparacion,
        //                         PedidoFechaDespacho, PedidoUsuarioDespacho
        // =====================================================================

        $existingPedidoCols = DB::select("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'Pedido'
        ");
        $pedidoCols = array_map(fn($c) => $c->COLUMN_NAME, $existingPedidoCols);

        Schema::table('Pedido', function (Blueprint $table) use ($pedidoCols) {
            // Estado de despacho — puede ya existir con el mismo nombre
            if (!in_array('PedidoEstadoDespacho', $pedidoCols)) {
                $table->string('PedidoEstadoDespacho', 30)->default('PENDIENTE_DESPACHO')->nullable();
            }
            if (!in_array('PedidoTieneFaltante', $pedidoCols)) {
                $table->string('PedidoTieneFaltante', 1)->default('N')->nullable();
            }
            if (!in_array('PedidoTotalDespachado', $pedidoCols)) {
                $table->decimal('PedidoTotalDespachado', 18, 2)->nullable();
            }
            if (!in_array('PedidoFechaInicioDespacho', $pedidoCols)) {
                $table->dateTime('PedidoFechaInicioDespacho')->nullable();
            }
            if (!in_array('PedidoFechaFinDespacho', $pedidoCols)) {
                $table->dateTime('PedidoFechaFinDespacho')->nullable();
            }
            if (!in_array('PedidoObservacionDespacho', $pedidoCols)) {
                $table->string('PedidoObservacionDespacho', 500)->nullable();
            }
            if (!in_array('PedidoDespachadoPor', $pedidoCols)) {
                $table->string('PedidoDespachadoPor', 100)->nullable();
            }
            if (!in_array('PedidoCerradoPor', $pedidoCols)) {
                $table->string('PedidoCerradoPor', 100)->nullable();
            }
        });

        // =====================================================================
        // TABLA: Detalle_Pedido_Productos — añadir campos de verificación por ítem
        // =====================================================================

        $existingDetalleCols = DB::select("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'Detalle_Pedido_Productos'
        ");
        $detalleCols = array_map(fn($c) => $c->COLUMN_NAME, $existingDetalleCols);

        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) use ($detalleCols) {
            if (!in_array('Detalle_Pedido_Productos_cantidad_despachada', $detalleCols)) {
                $table->decimal('Detalle_Pedido_Productos_cantidad_despachada', 18, 2)->nullable();
            }
            if (!in_array('Detalle_Pedido_Productos_estado_item_despacho', $detalleCols)) {
                $table->string('Detalle_Pedido_Productos_estado_item_despacho', 30)->default('PENDIENTE')->nullable();
            }
            if (!in_array('Detalle_Pedido_Productos_observacion_item', $detalleCols)) {
                $table->string('Detalle_Pedido_Productos_observacion_item', 200)->nullable();
            }
        });

        // =====================================================================
        // ÍNDICE de rendimiento en Pedido (no es tabla)
        // Acelera la carga de la bandeja de despacho
        // =====================================================================
        try {
            DB::statement("
                CREATE INDEX IX_Pedido_EstadoDespacho
                ON Pedido(PedidoEstadoDespacho, PedidoFechaCreacion)
                INCLUDE (Pedido_ClienteId, PedidoTotal, PedidoTotalDespachado, PedidoTieneFaltante)
            ");
        } catch (\Throwable $e) {
            // El índice puede ya existir si se re-ejecuta la migración
        }
    }

    public function down(): void
    {
        // Eliminar índice primero
        try {
            DB::statement('DROP INDEX IF EXISTS IX_Pedido_EstadoDespacho ON Pedido');
        } catch (\Throwable $e) {}

        // Revertir columnas de Pedido
        Schema::table('Pedido', function (Blueprint $table) {
            $cols = [
                'PedidoTieneFaltante',
                'PedidoTotalDespachado',
                'PedidoFechaInicioDespacho',
                'PedidoFechaFinDespacho',
                'PedidoObservacionDespacho',
                'PedidoDespachadoPor',
                'PedidoCerradoPor',
            ];
            foreach ($cols as $col) {
                try {
                    $table->dropColumn($col);
                } catch (\Throwable $e) {}
            }
        });

        // Revertir columnas de Detalle_Pedido_Productos
        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            $cols = [
                'Detalle_Pedido_Productos_cantidad_despachada',
                'Detalle_Pedido_Productos_estado_item_despacho',
                'Detalle_Pedido_Productos_observacion_item',
            ];
            foreach ($cols as $col) {
                try {
                    $table->dropColumn($col);
                } catch (\Throwable $e) {}
            }
        });
    }
};
