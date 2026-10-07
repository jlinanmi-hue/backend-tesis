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
        if (Schema::hasTable('Pedido')) {
            Schema::table('Pedido', function (Blueprint $table) {
                if (!Schema::hasColumn('Pedido', 'PedidoFechaEntrega')) {
                    $table->dateTime('PedidoFechaEntrega')->nullable();
                }
                if (!Schema::hasColumn('Pedido', 'PedidoEsDelivery')) {
                    $table->char('PedidoEsDelivery', 1)->default('N');
                }
                if (!Schema::hasColumn('Pedido', 'PedidoDireccionEntrega')) {
                    $table->string('PedidoDireccionEntrega', 300)->nullable();
                }
                if (!Schema::hasColumn('Pedido', 'PedidoReferenciaEntrega')) {
                    $table->string('PedidoReferenciaEntrega', 200)->nullable();
                }
                if (!Schema::hasColumn('Pedido', 'PedidoZonaDeliveryId')) {
                    $table->string('PedidoZonaDeliveryId', 20)->nullable();
                }
                if (!Schema::hasColumn('Pedido', 'PedidoCostoDelivery')) {
                    $table->decimal('PedidoCostoDelivery', 10, 2)->default(0.00);
                }
            });

            // Guard defensivo: Única relación foránea permitida con Zona_Delivery (exclusivamente con Pedido)
            if (Schema::hasTable('Zona_Delivery')) {
                Schema::table('Pedido', function (Blueprint $table) {
                    $table->foreign('PedidoZonaDeliveryId', 'FK_Pedido_ZonaDelivery')
                        ->references('Zona_DeliveryId')
                        ->on('Zona_Delivery')
                        ->noActionOnDelete()
                        ->noActionOnUpdate();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('Pedido')) {
            Schema::table('Pedido', function (Blueprint $table) {
                try {
                    $table->dropForeign('FK_Pedido_ZonaDelivery');
                } catch (\Throwable $e) {}

                $cols = [
                    'PedidoFechaEntrega',
                    'PedidoEsDelivery',
                    'PedidoDireccionEntrega',
                    'PedidoReferenciaEntrega',
                    'PedidoZonaDeliveryId',
                    'PedidoCostoDelivery',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('Pedido', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
