<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // =========================================================================
        // 1. TABLA: Orden_Compra
        // =========================================================================
        if (Schema::hasTable('Orden_Compra')) {
            // Paso 0: Sanitizar cualquier valor NULL antes de alterar (sigue siendo CHAR(1))
            DB::statement("UPDATE Orden_Compra SET Orden_CompraEstado = 'P' WHERE Orden_CompraEstado IS NULL");

            // Paso 1: Eliminar DEFAULT constraint dinámicamente en SQL Server
            $constraint = DB::selectOne("
                SELECT dc.name AS constraint_name
                FROM sys.default_constraints dc
                INNER JOIN sys.columns c ON c.default_object_id = dc.object_id
                INNER JOIN sys.tables t ON t.object_id = c.object_id
                WHERE t.name = 'Orden_Compra' AND c.name = 'Orden_CompraEstado'
            ");

            if ($constraint) {
                DB::statement("ALTER TABLE Orden_Compra DROP CONSTRAINT [{$constraint->constraint_name}]");
            }

            // Paso 2: Alterar columna de CHAR(1) a VARCHAR(30) NOT NULL para que soporte cadenas largas
            DB::statement("ALTER TABLE Orden_Compra ALTER COLUMN Orden_CompraEstado VARCHAR(30) NOT NULL");

            // Paso 3: Ahora que es VARCHAR(30), migrar valores legacy existentes
            DB::statement("UPDATE Orden_Compra SET Orden_CompraEstado = 'PENDIENTE_RECEPCION' WHERE Orden_CompraEstado = 'P'");
            DB::statement("UPDATE Orden_Compra SET Orden_CompraEstado = 'CERRADA' WHERE Orden_CompraEstado = 'C'");
            DB::statement("UPDATE Orden_Compra SET Orden_CompraEstado = 'ANULADA' WHERE Orden_CompraEstado = 'A'");

            // Paso 4: Reasignar default constraint para nuevos registros
            DB::statement("ALTER TABLE Orden_Compra ADD CONSTRAINT DF_Orden_Compra_Estado DEFAULT 'BORRADOR' FOR Orden_CompraEstado");

            // Paso 5: Ampliación de columnas adicionales en Orden_Compra
            Schema::table('Orden_Compra', function (Blueprint $table) {
                if (!Schema::hasColumn('Orden_Compra', 'Orden_CompraFechaEstimadaLlegada')) {
                    $table->dateTime('Orden_CompraFechaEstimadaLlegada')->nullable();
                }
                if (!Schema::hasColumn('Orden_Compra', 'Orden_CompraFechaRecepcionReal')) {
                    $table->dateTime('Orden_CompraFechaRecepcionReal')->nullable();
                }
                if (!Schema::hasColumn('Orden_Compra', 'Orden_CompraMotivoAnulacion')) {
                    $table->string('Orden_CompraMotivoAnulacion', 500)->nullable();
                }
                if (!Schema::hasColumn('Orden_Compra', 'Orden_CompraUsuarioRecepcionId')) {
                    $table->string('Orden_CompraUsuarioRecepcionId', 100)->nullable();
                }
                if (!Schema::hasColumn('Orden_Compra', 'Orden_CompraCerradaConFaltante')) {
                    $table->char('Orden_CompraCerradaConFaltante', 1)->default('N');
                }
            });
        }

        // =========================================================================
        // 2. TABLA: Detalle_Orden_Compra
        // =========================================================================
        if (Schema::hasTable('Detalle_Orden_Compra')) {
            Schema::table('Detalle_Orden_Compra', function (Blueprint $table) {
                if (!Schema::hasColumn('Detalle_Orden_Compra', 'Detalle_Orden_CompraCantidadRecibida')) {
                    $table->decimal('Detalle_Orden_CompraCantidadRecibida', 10, 2)->default(0.00);
                }
                if (!Schema::hasColumn('Detalle_Orden_Compra', 'Detalle_Orden_CompraEntregado')) {
                    $table->char('Detalle_Orden_CompraEntregado', 1)->default('N');
                }
                if (!Schema::hasColumn('Detalle_Orden_Compra', 'Detalle_Orden_CompraFechaRecepcionItem')) {
                    $table->dateTime('Detalle_Orden_CompraFechaRecepcionItem')->nullable();
                }
                if (!Schema::hasColumn('Detalle_Orden_Compra', 'Detalle_Orden_CompraUsuarioRecepcionItemId')) {
                    $table->string('Detalle_Orden_CompraUsuarioRecepcionItemId', 100)->nullable();
                }
                if (!Schema::hasColumn('Detalle_Orden_Compra', 'Detalle_Orden_CompraFueProductoNuevo')) {
                    $table->char('Detalle_Orden_CompraFueProductoNuevo', 1)->default('N');
                }
            });
        }

        // =========================================================================
        // 3. TABLA: Detalle_Pedido_Productos
        // =========================================================================
        if (Schema::hasTable('Detalle_Pedido_Productos')) {
            Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
                if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_ProductosTuvoRotura')) {
                    $table->char('Detalle_Pedido_ProductosTuvoRotura', 1)->default('N');
                }
                if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_ProductosCantidadFaltanteRotura')) {
                    $table->decimal('Detalle_Pedido_ProductosCantidadFaltanteRotura', 10, 2)->default(0.00);
                }
            });
        }

        // =========================================================================
        // 4. TABLA: Movimiento_producto (Kárdex)
        // =========================================================================
        if (Schema::hasTable('Movimiento_producto')) {
            Schema::table('Movimiento_producto', function (Blueprint $table) {
                if (!Schema::hasColumn('Movimiento_producto', 'Movimiento_productoSubtipo')) {
                    $table->string('Movimiento_productoSubtipo', 30)->nullable();
                }
                if (!Schema::hasColumn('Movimiento_producto', 'Movimiento_productoReferenciaTipo')) {
                    $table->string('Movimiento_productoReferenciaTipo', 30)->nullable();
                }
                if (!Schema::hasColumn('Movimiento_producto', 'Movimiento_productoReferenciaId')) {
                    $table->string('Movimiento_productoReferenciaId', 45)->nullable();
                }
                if (!Schema::hasColumn('Movimiento_producto', 'Movimiento_productoMotivo')) {
                    $table->string('Movimiento_productoMotivo', 500)->nullable();
                }
            });

            // Paso 6: Backfill de subtipos históricos en Movimiento_producto para soporte completo de Kárdex
            DB::statement("UPDATE Movimiento_producto SET Movimiento_productoSubtipo = 'DEVOLUCION_CLIENTE' WHERE Movimiento_productoDocumentoOperacionId LIKE 'DEV-PED-%' AND Movimiento_productoSubtipo IS NULL");
            DB::statement("UPDATE Movimiento_producto SET Movimiento_productoSubtipo = 'AJUSTE_MANUAL' WHERE (Movimiento_productoDocumentoOperacionId LIKE 'AJU-%' OR Movimiento_productoDocumentoOperacionId LIKE 'AJUSTE-%' OR Movimiento_productoDocumentoOperacionId LIKE 'AUDIT-%') AND Movimiento_productoSubtipo IS NULL");
            DB::statement("UPDATE Movimiento_producto SET Movimiento_productoSubtipo = 'ANULACION_RECEPCION' WHERE (Movimiento_productoDocumentoOperacionId LIKE 'ANUL-%' OR Movimiento_productoDocumentoOperacionId LIKE 'REV-%') AND Movimiento_productoSubtipo IS NULL");
            DB::statement("UPDATE Movimiento_producto SET Movimiento_productoSubtipo = 'RECEPCION_OC' WHERE Movimiento_productoDocumentoOperacionId LIKE 'OC-%' AND Movimiento_productoSubtipo IS NULL");
            DB::statement("UPDATE Movimiento_producto SET Movimiento_productoSubtipo = 'VENTA_PEDIDO' WHERE Movimiento_productoDocumentoOperacionId LIKE 'PED-%' AND Movimiento_productoSubtipo IS NULL");
            DB::statement("UPDATE Movimiento_producto SET Movimiento_productoSubtipo = 'INVENTARIO_INICIAL' WHERE Movimiento_productoDocumentoOperacionId LIKE 'INV-INICIAL%' AND Movimiento_productoSubtipo IS NULL");
            DB::statement("UPDATE Movimiento_producto SET Movimiento_productoSubtipo = 'RECEPCION_OC' WHERE Movimiento_productoSubtipo IS NULL AND Movimiento_productoTipoMovimiento = 'E'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Revertir Movimiento_producto
        if (Schema::hasTable('Movimiento_producto')) {
            Schema::table('Movimiento_producto', function (Blueprint $table) {
                $cols = ['Movimiento_productoSubtipo', 'Movimiento_productoReferenciaTipo', 'Movimiento_productoReferenciaId', 'Movimiento_productoMotivo'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('Movimiento_producto', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 2. Revertir Detalle_Pedido_Productos
        if (Schema::hasTable('Detalle_Pedido_Productos')) {
            Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
                $cols = ['Detalle_Pedido_ProductosTuvoRotura', 'Detalle_Pedido_ProductosCantidadFaltanteRotura'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('Detalle_Pedido_Productos', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 3. Revertir Detalle_Orden_Compra
        if (Schema::hasTable('Detalle_Orden_Compra')) {
            Schema::table('Detalle_Orden_Compra', function (Blueprint $table) {
                $cols = [
                    'Detalle_Orden_CompraCantidadRecibida',
                    'Detalle_Orden_CompraEntregado',
                    'Detalle_Orden_CompraFechaRecepcionItem',
                    'Detalle_Orden_CompraUsuarioRecepcionItemId',
                    'Detalle_Orden_CompraFueProductoNuevo'
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('Detalle_Orden_Compra', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 4. Revertir Orden_Compra
        if (Schema::hasTable('Orden_Compra')) {
            Schema::table('Orden_Compra', function (Blueprint $table) {
                $cols = [
                    'Orden_CompraFechaEstimadaLlegada',
                    'Orden_CompraFechaRecepcionReal',
                    'Orden_CompraMotivoAnulacion',
                    'Orden_CompraUsuarioRecepcionId',
                    'Orden_CompraCerradaConFaltante'
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('Orden_Compra', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });

            // Revertir estados a legacy ('P', 'C', 'A')
            DB::statement("UPDATE Orden_Compra SET Orden_CompraEstado = 'P' WHERE Orden_CompraEstado IN ('BORRADOR', 'ENVIADA', 'PENDIENTE_RECEPCION', 'EN_RECEPCION')");
            DB::statement("UPDATE Orden_Compra SET Orden_CompraEstado = 'C' WHERE Orden_CompraEstado IN ('CERRADA', 'CERRADA_CON_FALTANTE')");
            DB::statement("UPDATE Orden_Compra SET Orden_CompraEstado = 'A' WHERE Orden_CompraEstado IN ('ANULADA', 'CANCELADA_PROVEEDOR')");

            // Eliminar default constraint DF_Orden_Compra_Estado si existe
            $constraint = DB::selectOne("
                SELECT dc.name AS constraint_name
                FROM sys.default_constraints dc
                INNER JOIN sys.columns c ON c.default_object_id = dc.object_id
                INNER JOIN sys.tables t ON t.object_id = c.object_id
                WHERE t.name = 'Orden_Compra' AND c.name = 'Orden_CompraEstado'
            ");
            if ($constraint) {
                DB::statement("ALTER TABLE Orden_Compra DROP CONSTRAINT [{$constraint->constraint_name}]");
            }

            // Regresar a CHAR(1)
            DB::statement("ALTER TABLE Orden_Compra ALTER COLUMN Orden_CompraEstado CHAR(1) NOT NULL");
            DB::statement("ALTER TABLE Orden_Compra ADD CONSTRAINT DF_Orden_Compra_Estado_Legacy DEFAULT 'P' FOR Orden_CompraEstado");
        }
    }
};
