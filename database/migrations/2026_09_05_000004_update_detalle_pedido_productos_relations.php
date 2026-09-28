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
        // 1. Eliminar la Foreign Key previa que enlazaba con Detalle_Producto_medida
        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            $table->dropForeign('Detalle_Pedido_Productos_Detalle_Producto_medidaFK');
        });

        // 2. Renombrar la columna o recrearla hacia Detalle_Pedido_Productos_ProductoId y eliminar id_unidad_detalle
        // Usamos sp_rename para SQL Server para preservar datos si existieran
        DB::statement("EXEC sp_rename 'Detalle_Pedido_Productos.Detalle_Pedido_Productos_id_producto_detalle', 'Detalle_Pedido_Productos_ProductoId', 'COLUMN'");

        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            $table->dropColumn('Detalle_Pedido_Productos_id_unidad_detalle');

            // 3. Crear la nueva Foreign Key hacia Producto(ProductoId)
            $table->foreign('Detalle_Pedido_Productos_ProductoId', 'Detalle_Pedido_Productos_ProductoFK')
                ->references('ProductoId')
                ->on('Producto')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            $table->dropForeign('Detalle_Pedido_Productos_ProductoFK');
            $table->string('Detalle_Pedido_Productos_id_unidad_detalle', 20)->nullable();
        });

        DB::statement("EXEC sp_rename 'Detalle_Pedido_Productos.Detalle_Pedido_Productos_ProductoId', 'Detalle_Pedido_Productos_id_producto_detalle', 'COLUMN'");

        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
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
};
