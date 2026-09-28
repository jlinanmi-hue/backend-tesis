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
        Schema::create('Detalle_Pedido_Productos', function (Blueprint $table) {
            $table->string('Detalle_Pedido_Productos_PedidoId', 20)->primary();
            $table->string('Detalle_Pedido_Productos_cantidad', 45);
            $table->string('Detalle_Pedido_Productos_precio_unitario_venta', 45);
            $table->decimal('Detalle_Pedido_Productos_subtotal', 10, 2);
            $table->string('Detalle_Pedido_Productos_id_producto_detalle', 20);
            $table->string('Detalle_Pedido_Productos_id_unidad_detalle', 20);
            $table->char('Detalle_Pedido_ProductosEliminado', 1)->default('N');
            $table->string('Detalle_Pedido_ProductosUsuarioCreacion', 100);
            $table->string('Detalle_Pedido_ProductosHostCreacion', 100);
            $table->dateTime('Detalle_Pedido_ProductosFechaCreacion');
            $table->string('Detalle_Pedido_ProductosUsuarioModificacion', 100)->nullable();
            $table->string('Detalle_Pedido_ProductosHostModificacion', 100)->nullable();
            $table->dateTime('Detalle_Pedido_ProductosFechaModificacion')->nullable();
            $table->string('Detalle_Pedido_ProductosUsuarioEliminacion', 100)->nullable();
            $table->string('Detalle_Pedido_ProductosHostEliminacion', 100)->nullable();
            $table->dateTime('Detalle_Pedido_ProductosFechaEliminacion')->nullable();

            $table->foreign('Detalle_Pedido_Productos_PedidoId', 'Detalle_Pedido_Productos_PedidoFK')
                ->references('PedidoId')
                ->on('Pedido')
                ->noActionOnDelete()
                ->noActionOnUpdate();

            $table->foreign(
                ['Detalle_Pedido_Productos_id_producto_detalle', 'Detalle_Pedido_Productos_id_unidad_detalle'],
                'Detalle_Pedido_Productos_Detalle_Producto_medidaFK'
            )->references(
                ['Detalle_Producto_medida_ProductoId', 'Detalle_Producto_medida_unidades_medidaId']
            )->on('Detalle_Producto_medida')
            ->noActionOnDelete()
            ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Detalle_Pedido_Productos');
    }
};
