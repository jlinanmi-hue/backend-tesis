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
        // 1. Eliminar la tabla Detalle_Pedido_Productos si existe (está vacía con 0 registros)
        Schema::dropIfExists('Detalle_Pedido_Productos');

        // 2. Recrear Detalle_Pedido_Productos con Clave Primaria Autonumérica adecuada
        Schema::create('Detalle_Pedido_Productos', function (Blueprint $table) {
            $table->increments('Detalle_Pedido_ProductosId');
            $table->string('Detalle_Pedido_Productos_PedidoId', 20);
            $table->string('Detalle_Pedido_Productos_ProductoId', 20);
            $table->decimal('Detalle_Pedido_Productos_cantidad', 10, 2)->default(1.00);
            $table->decimal('Detalle_Pedido_Productos_precio_unitario_venta', 10, 2)->default(0.00);
            $table->decimal('Detalle_Pedido_Productos_subtotal', 10, 2)->default(0.00);

            // Campos de auditoría estándar
            $table->char('Detalle_Pedido_ProductosEliminado', 1)->default('N');
            $table->string('Detalle_Pedido_ProductosUsuarioCreacion', 100);
            $table->string('Detalle_Pedido_ProductosHostCreacion', 100);
            $table->dateTime('Detalle_Pedido_ProductosFechaCreacion')->useCurrent();
            $table->string('Detalle_Pedido_ProductosUsuarioModificacion', 100)->nullable();
            $table->string('Detalle_Pedido_ProductosHostModificacion', 100)->nullable();
            $table->dateTime('Detalle_Pedido_ProductosFechaModificacion')->nullable();
            $table->string('Detalle_Pedido_ProductosUsuarioEliminacion', 100)->nullable();
            $table->string('Detalle_Pedido_ProductosHostEliminacion', 100)->nullable();
            $table->dateTime('Detalle_Pedido_ProductosFechaEliminacion')->nullable();

            // Relación con Pedido
            $table->foreign('Detalle_Pedido_Productos_PedidoId', 'FK_Detalle_Pedido_Pedido')
                ->references('PedidoId')
                ->on('Pedido')
                ->onDelete('cascade')
                ->noActionOnUpdate();

            // Relación con Producto
            $table->foreign('Detalle_Pedido_Productos_ProductoId', 'FK_Detalle_Pedido_Producto')
                ->references('ProductoId')
                ->on('Producto')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });

        // 3. Crear índice para agilizar consultas y validaciones de stock en Producto
        try {
            DB::statement("IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Producto_StockActual') CREATE INDEX IX_Producto_StockActual ON Producto (ProductoStockActual);");
        } catch (\Exception $e) {
            // Continuar si ya existiera el índice
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Detalle_Pedido_Productos');

        try {
            DB::statement("IF EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Producto_StockActual') DROP INDEX IX_Producto_StockActual ON Producto;");
        } catch (\Exception $e) {
        }
    }
};
