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
        Schema::create('Pedido', function (Blueprint $table) {
            $table->string('PedidoId', 20)->primary();
            $table->dateTime('PedidoFecha_pedido');
            $table->decimal('PedidoTotal', 10, 2);
            $table->decimal('PedidoIgv', 10, 2)->default(0.00);
            $table->string('PedidoUsuarioRegistro', 45);
            $table->char('PedidoEstado_pedido', 1)->default('P');
            $table->string('PedidoAcuerdo_Comercial', 45);
            $table->string('Pedido_ClienteId', 20);
            $table->string('Pedido_canal_pedidoId', 20);
            $table->char('PedidoEliminado', 1)->default('N');
            $table->string('PedidoUsuarioCreacion', 100);
            $table->string('PedidoHostCreacion', 100);
            $table->dateTime('PedidoFechaCreacion');
            $table->string('PedidoUsuarioModificacion', 100)->nullable();
            $table->string('PedidoHostModificacion', 100)->nullable();
            $table->dateTime('PedidoFechaModificacion')->nullable();
            $table->string('PedidoUsuarioEliminacion', 100)->nullable();
            $table->string('PedidoHostEliminacion', 100)->nullable();
            $table->dateTime('PedidoFechaEliminacion')->nullable();

            $table->foreign('Pedido_ClienteId', 'Pedido_ClienteFK')
                ->references('ClienteId')
                ->on('Cliente')
                ->noActionOnDelete()
                ->noActionOnUpdate();

            $table->foreign('Pedido_canal_pedidoId', 'Pedido_canal_pedidoFK')
                ->references('Canal_pedidoId')
                ->on('Canal_pedido')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Pedido');
    }
};
