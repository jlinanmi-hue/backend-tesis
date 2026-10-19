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

        Schema::table('Pedido', function (Blueprint $table) {
            // Estado de despacho — puede ya existir con el mismo nombre
            if (!Schema::hasColumn('Pedido', 'PedidoEstadoDespacho')) {
                $table->string('PedidoEstadoDespacho', 30)->default('PENDIENTE_DESPACHO')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTieneFaltante')) {
                $table->string('PedidoTieneFaltante', 1)->default('N')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTotalDespachado')) {
                $table->decimal('PedidoTotalDespachado', 18, 2)->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoFechaInicioDespacho')) {
                $table->dateTime('PedidoFechaInicioDespacho')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoFechaFinDespacho')) {
                $table->dateTime('PedidoFechaFinDespacho')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoObservacionDespacho')) {
                $table->string('PedidoObservacionDespacho', 500)->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoDespachadoPor')) {
                $table->string('PedidoDespachadoPor', 100)->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoCerradoPor')) {
                $table->string('PedidoCerradoPor', 100)->nullable();
            }
        });

        // =====================================================================
        // TABLA: Detalle_Pedido_Productos — añadir campos de verificación por ítem
        // =====================================================================

        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_despachada')) {
                $table->decimal('Detalle_Pedido_Productos_cantidad_despachada', 18, 2)->nullable();
            }
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_estado_item_despacho')) {
                $table->string('Detalle_Pedido_Productos_estado_item_despacho', 30)->default('PENDIENTE')->nullable();
            }
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_observacion_item')) {
                $table->string('Detalle_Pedido_Productos_observacion_item', 200)->nullable();
            }
        });

        // =====================================================================
        // ÍNDICE de rendimiento en Pedido (no es tabla)
        // Acelera la carga de la bandeja de despacho
        // =====================================================================
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('
                    CREATE INDEX IF NOT EXISTS "ix_pedido_estadodespacho"
                    ON "Pedido"("PedidoEstadoDespacho", "PedidoFechaCreacion")
                    INCLUDE ("Pedido_ClienteId", "PedidoTotal", "PedidoTotalDespachado", "PedidoTieneFaltante")
                ');
            } else {
                DB::statement("
                    CREATE INDEX IX_Pedido_EstadoDespacho
                    ON Pedido(PedidoEstadoDespacho, PedidoFechaCreacion)
                    INCLUDE (Pedido_ClienteId, PedidoTotal, PedidoTotalDespachado, PedidoTieneFaltante)
                ");
            }
        } catch (\Throwable $e) {
            // El índice puede ya existir si se re-ejecuta la migración
        }
    }

    public function down(): void
    {
        // Eliminar índice primero
        try {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS "ix_pedido_estadodespacho"');
            } else {
                DB::statement('DROP INDEX IF EXISTS IX_Pedido_EstadoDespacho ON Pedido');
            }
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
