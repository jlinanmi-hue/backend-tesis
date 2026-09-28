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
        Schema::create('Movimiento_producto', function (Blueprint $table) {
            $table->string('Movimiento_productoId', 20)->primary();
            $table->string('Movimiento_productoCantidadPresentacion', 45);
            $table->string('Movimiento_productoDocumentoOperacionId', 45);
            $table->char('Movimiento_productoTipoMovimiento', 1);
            $table->decimal('Movimiento_productoCostoPrecioUnitario', 10, 2);
            $table->string('Movimiento_productoCantidadEntrada', 45);
            $table->string('Movimiento_productoCantidadSalida', 45);
            $table->string('Movimiento_productoCantidadSaldo', 45);
            $table->dateTime('Movimiento_productoFecha_Movimiento');
            $table->string('Movimiento_producto_Detalle_Producto_medida_ProductoId', 20);
            $table->string('Movimiento_producto_Detalle_Producto_medida_unidades_medidaId', 20);
            $table->char('Movimiento_productoEliminado', 1)->default('N');
            $table->string('Movimiento_productoUsuarioCreacion', 100);
            $table->string('Movimiento_productoHostCreacion', 100);
            $table->dateTime('Movimiento_productoFechaCreacion');
            $table->string('Movimiento_productoUsuarioModificacion', 100)->nullable();
            $table->string('Movimiento_productoHostModificacion', 100)->nullable();
            $table->dateTime('Movimiento_productoFechaModificacion')->nullable();
            $table->string('Movimiento_productoUsuarioEliminacion', 100)->nullable();
            $table->string('Movimiento_productoHostEliminacion', 100)->nullable();
            $table->dateTime('Movimiento_productoFechaEliminacion')->nullable();

            $table->foreign(
                ['Movimiento_producto_Detalle_Producto_medida_ProductoId', 'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId'],
                'Movimiento_producto_Detalle_Producto_medidaFK'
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
        Schema::dropIfExists('Movimiento_producto');
    }
};
