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
        Schema::create('Detalle_Producto_medida', function (Blueprint $table) {
            $table->string('Detalle_Producto_medida_ProductoId', 20);
            $table->string('Detalle_Producto_medida_unidades_medidaId', 20);
            $table->integer('Detalle_Producto_medida_factor_conversion');
            $table->decimal('Detalle_Producto_medida_precio_venta', 10, 2);
            $table->decimal('Detalle_Producto_medida_precio_compra', 10, 2);
            $table->char('Detalle_Producto_medidaEliminado', 1)->default('N');
            $table->string('Detalle_Producto_medidaUsuarioCreacion', 100);
            $table->string('Detalle_Producto_medidaHostCreacion', 100);
            $table->dateTime('Detalle_Producto_medidaFechaCreacion');
            $table->string('Detalle_Producto_medidaUsuarioModificacion', 100)->nullable();
            $table->string('Detalle_Producto_medidaHostModificacion', 100)->nullable();
            $table->dateTime('Detalle_Producto_medidaFechaModificacion')->nullable();
            $table->string('Detalle_Producto_medidaUsuarioEliminacion', 100)->nullable();
            $table->string('Detalle_Producto_medidaHostEliminacion', 100)->nullable();
            $table->dateTime('Detalle_Producto_medidaFechaEliminacion')->nullable();

            $table->primary(['Detalle_Producto_medida_ProductoId', 'Detalle_Producto_medida_unidades_medidaId'], 'Detalle_Producto_medidaPK');

            $table->foreign('Detalle_Producto_medida_ProductoId', 'Detalle_Producto_medida_ProductoFK')
                ->references('ProductoId')
                ->on('Producto')
                ->noActionOnDelete()
                ->noActionOnUpdate();

            $table->foreign('Detalle_Producto_medida_unidades_medidaId', 'Detalle_Producto_medida_unidades_medidaFK')
                ->references('unidades_medidaId')
                ->on('unidades_medida')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Detalle_Producto_medida');
    }
};
