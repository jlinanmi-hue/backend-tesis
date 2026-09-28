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
        Schema::create('Detalle_Orden_Compra', function (Blueprint $table) {
            $table->increments('Detalle_Orden_CompraId');
            $table->string('Detalle_Orden_Compra_Orden_CompraId', 20);
            $table->string('Detalle_ProductoId', 20);
            $table->string('Detalle_UnidadMedidaId', 20);
            $table->decimal('Detalle_Orden_CompraCantidad', 10, 2);
            $table->decimal('Detalle_Orden_CompraPrecioUnitario', 10, 2);
            $table->decimal('Detalle_Orden_CompraSubtotal', 10, 2);

            // Auditoría Estándar
            $table->char('Detalle_Orden_CompraEliminado', 1)->default('N');
            $table->string('Detalle_Orden_CompraUsuarioCreacion', 100);
            $table->string('Detalle_Orden_CompraHostCreacion', 100);
            $table->dateTime('Detalle_Orden_CompraFechaCreacion')->useCurrent();
            $table->string('Detalle_Orden_CompraUsuarioModificacion', 100)->nullable();
            $table->string('Detalle_Orden_CompraHostModificacion', 100)->nullable();
            $table->dateTime('Detalle_Orden_CompraFechaModificacion')->nullable();
            $table->string('Detalle_Orden_CompraUsuarioEliminacion', 100)->nullable();
            $table->string('Detalle_Orden_CompraHostEliminacion', 100)->nullable();
            $table->dateTime('Detalle_Orden_CompraFechaEliminacion')->nullable();

            // Relación con Orden_Compra
            $table->foreign('Detalle_Orden_Compra_Orden_CompraId', 'FK_Detalle_Orden_Compra_Orden')
                ->references('Orden_CompraId')
                ->on('Orden_Compra')
                ->onDelete('cascade')
                ->noActionOnUpdate();

            // Relación con Producto
            $table->foreign('Detalle_ProductoId', 'FK_Detalle_Orden_Compra_Producto')
                ->references('ProductoId')
                ->on('Producto')
                ->noActionOnDelete()
                ->noActionOnUpdate();

            // Relación con Unidades de Medida
            $table->foreign('Detalle_UnidadMedidaId', 'FK_Detalle_Orden_Compra_UnidadMedida')
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
        Schema::dropIfExists('Detalle_Orden_Compra');
    }
};
