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
        // 1. Renombrar la columna a Movimiento_producto_ProductoId usando sp_rename de SQL Server para conservar los datos
        DB::statement("EXEC sp_rename 'Movimiento_producto.Movimiento_producto_Detalle_Producto_medida_ProductoId', 'Movimiento_producto_ProductoId', 'COLUMN'");

        // 2. Agregar la clave foránea hacia la tabla Producto
        Schema::table('Movimiento_producto', function (Blueprint $table) {
            $table->foreign('Movimiento_producto_ProductoId', 'Movimiento_producto_ProductoFK')
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
        Schema::table('Movimiento_producto', function (Blueprint $table) {
            $table->dropForeign('Movimiento_producto_ProductoFK');
        });

        DB::statement("EXEC sp_rename 'Movimiento_producto.Movimiento_producto_ProductoId', 'Movimiento_producto_Detalle_Producto_medida_ProductoId', 'COLUMN'");
    }
};
