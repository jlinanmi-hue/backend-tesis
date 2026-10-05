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
        if (!Schema::hasTable('detalle_rotura_stock')) {
            Schema::create('detalle_rotura_stock', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('pedido_id', 20)->nullable();
                $table->unsignedInteger('detalle_pedido_id')->nullable();
                $table->string('producto_id', 20);
                $table->string('orden_compra_codigo', 20)->nullable();
                $table->decimal('cantidad_solicitada', 12, 2);
                $table->decimal('cantidad_disponible_momento', 12, 2);
                $table->decimal('cantidad_faltante', 12, 2);
                $table->string('tipo_rotura', 20); // 'INTENTO' | 'CONFIRMADA'
                $table->dateTime('fecha_hora')->useCurrent();
                $table->string('usuario_id', 100);
                $table->string('observaciones', 500)->nullable();
                $table->timestamps();

                // Índices para reportes y cálculo rápido del indicador PRS
                $table->index(['producto_id', 'fecha_hora'], 'idx_rotura_prod_fecha');
                $table->index(['tipo_rotura', 'fecha_hora'], 'idx_rotura_tipo_fecha');
                $table->index('pedido_id', 'idx_rotura_pedido');

                // Relación con Pedido con restricción noActionOnDelete para compatibilidad con SQL Server
                $table->foreign('pedido_id', 'FK_rotura_stock_pedido')
                    ->references('PedidoId')
                    ->on('Pedido')
                    ->noActionOnDelete()
                    ->noActionOnUpdate();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_rotura_stock');
    }
};
