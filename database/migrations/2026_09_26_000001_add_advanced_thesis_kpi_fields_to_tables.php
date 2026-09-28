<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Extender la tabla Pedido con nuevos campos para medición de PODE, PEOR y TBPP
        Schema::table('Pedido', function (Blueprint $table) {
            // INDICADOR 1: PODE (% Órdenes Despachadas Exitosamente)
            if (!Schema::hasColumn('Pedido', 'PedidoEstadoDespacho')) {
                $table->string('PedidoEstadoDespacho', 30)->default('ENTREGADO_COMPLETO')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoCausaFalloDespacho')) {
                $table->string('PedidoCausaFalloDespacho', 50)->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoFechaInicioPreparacion')) {
                $table->dateTime('PedidoFechaInicioPreparacion')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoFechaDespacho')) {
                $table->dateTime('PedidoFechaDespacho')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoUsuarioDespacho')) {
                $table->string('PedidoUsuarioDespacho', 50)->nullable();
            }

            // INDICADOR 2: PEOR (% Error en Órdenes Registradas)
            if (!Schema::hasColumn('Pedido', 'PedidoOrigenError')) {
                $table->string('PedidoOrigenError', 30)->nullable(); // ERROR_HUMANO, ERROR_IA, ERROR_DATOS_MAESTROS
            }
            if (!Schema::hasColumn('Pedido', 'PedidoGravedadError')) {
                $table->string('PedidoGravedadError', 20)->nullable(); // LEVE, MODERADO, CRITICO
            }
            if (!Schema::hasColumn('Pedido', 'PedidoOrigenDeteccionError')) {
                $table->string('PedidoOrigenDeteccionError', 30)->nullable(); // DETECTADO_HUMANO, DETECTADO_IA
            }
            if (!Schema::hasColumn('Pedido', 'PedidoMomentoDeteccionError')) {
                $table->string('PedidoMomentoDeteccionError', 30)->nullable(); // PRE_DESPACHO, POST_DESPACHO
            }
            if (!Schema::hasColumn('Pedido', 'PedidoCostoError')) {
                $table->decimal('PedidoCostoError', 10, 2)->default(0.00);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoScoreConfianzaIA')) {
                $table->decimal('PedidoScoreConfianzaIA', 5, 2)->nullable(); // 0.00 - 100.00
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTiempoCorreccionMin')) {
                $table->integer('PedidoTiempoCorreccionMin')->default(0);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoIdOriginal')) {
                $table->string('PedidoIdOriginal', 20)->nullable();
            }

            // INDICADOR 4: TBPP (Tiempo Promedio de Registro / Telemetría)
            if (!Schema::hasColumn('Pedido', 'PedidoTiempoRegistroSeg')) {
                $table->integer('PedidoTiempoRegistroSeg')->default(0);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTiempoEfectivoSeg')) {
                $table->integer('PedidoTiempoEfectivoSeg')->default(0);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTiempoSeleccionClienteMs')) {
                $table->integer('PedidoTiempoSeleccionClienteMs')->default(0);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTiempoCargaProductosMs')) {
                $table->integer('PedidoTiempoCargaProductosMs')->default(0);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTiempoValidacionStockMs')) {
                $table->integer('PedidoTiempoValidacionStockMs')->default(0);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTiempoConfirmacionPagoMs')) {
                $table->integer('PedidoTiempoConfirmacionPagoMs')->default(0);
            }
            if (!Schema::hasColumn('Pedido', 'PedidoDispositivo')) {
                $table->string('PedidoDispositivo', 20)->default('DESKTOP')->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTipoCliente')) {
                $table->string('PedidoTipoCliente', 20)->default('RECURRENTE')->nullable(); // NUEVO, RECURRENTE
            }
            if (!Schema::hasColumn('Pedido', 'PedidoIntentosCorreccion')) {
                $table->integer('PedidoIntentosCorreccion')->default(0);
            }
        });

        // 2. Tabla de Auditoría de Roturas de Stock y Backorders (INDICADOR 3: PRS)
        if (!Schema::hasTable('auditoria_roturas_stock')) {
            Schema::create('auditoria_roturas_stock', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('pedido_id', 20)->nullable();
                $table->decimal('cantidad_solicitada', 12, 2)->default(0.00);
                $table->decimal('cantidad_disponible_fisica', 12, 2)->default(0.00);
                $table->decimal('cantidad_virtual_usada', 12, 2)->default(0.00);
                $table->decimal('deficit_unidades', 12, 2)->default(0.00);
                $table->string('tipo_rotura', 30)->default('AMORTIGUADO'); // VENTA_PERDIDA, BACKORDER, AMORTIGUADO
                $table->boolean('rompe_stock_seguridad')->default(false);
                $table->string('categoria_id', 20)->nullable();
                $table->string('usuario', 50)->nullable();
                $table->dateTime('created_at')->nullable();

                $table->foreign('pedido_id')
                    ->references('PedidoId')
                    ->on('Pedido')
                    ->noActionOnDelete();
            });
        }

        // 3. Tabla de Auditoría e Historial de Correcciones (INDICADOR 2: PEOR)
        if (!Schema::hasTable('pedido_historial_correccion')) {
            Schema::create('pedido_historial_correccion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('pedido_id', 20);
                $table->string('campo_modificado', 100);
                $table->text('valor_anterior')->nullable();
                $table->text('valor_nuevo')->nullable();
                $table->string('motivo', 200)->nullable();
                $table->string('usuario', 50)->nullable();
                $table->dateTime('created_at')->nullable();

                $table->foreign('pedido_id')
                    ->references('PedidoId')
                    ->on('Pedido')
                    ->noActionOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido_historial_correccion');
        Schema::dropIfExists('auditoria_roturas_stock');

        Schema::table('Pedido', function (Blueprint $table) {
            $cols = [
                'PedidoEstadoDespacho',
                'PedidoCausaFalloDespacho',
                'PedidoFechaInicioPreparacion',
                'PedidoFechaDespacho',
                'PedidoUsuarioDespacho',
                'PedidoOrigenError',
                'PedidoGravedadError',
                'PedidoOrigenDeteccionError',
                'PedidoMomentoDeteccionError',
                'PedidoCostoError',
                'PedidoScoreConfianzaIA',
                'PedidoTiempoCorreccionMin',
                'PedidoIdOriginal',
                'PedidoTiempoRegistroSeg',
                'PedidoTiempoEfectivoSeg',
                'PedidoTiempoSeleccionClienteMs',
                'PedidoTiempoCargaProductosMs',
                'PedidoTiempoValidacionStockMs',
                'PedidoTiempoConfirmacionPagoMs',
                'PedidoDispositivo',
                'PedidoTipoCliente',
                'PedidoIntentosCorreccion',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('Pedido', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
