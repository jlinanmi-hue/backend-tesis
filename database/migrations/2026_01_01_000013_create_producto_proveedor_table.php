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
        Schema::create('Producto_Proveedor', function (Blueprint $table) {
            $table->string('Producto_Proveedor_ProveedorId', 20);
            $table->string('Producto_Proveedor_ProductoId', 20);
            $table->char('Producto_ProveedorEliminado', 1)->default('N');
            $table->string('Producto_ProveedorUsuarioCreacion', 100);
            $table->string('Producto_ProveedorHostCreacion', 100);
            $table->dateTime('Producto_ProveedorFechaCreacion');
            $table->string('Producto_ProveedorUsuarioModificacion', 100)->nullable();
            $table->string('Producto_ProveedorHostModificacion', 100)->nullable();
            $table->dateTime('Producto_ProveedorFechaModificacion')->nullable();
            $table->string('Producto_ProveedorUsuarioEliminacion', 100)->nullable();
            $table->string('Producto_ProveedorHostEliminacion', 100)->nullable();
            $table->dateTime('Producto_ProveedorFechaEliminacion')->nullable();

            $table->primary(['Producto_Proveedor_ProveedorId', 'Producto_Proveedor_ProductoId'], 'Producto_ProveedorPK');

            $table->foreign('Producto_Proveedor_ProveedorId', 'Producto_Proveedor_ProveedorFK')
                ->references('ProveedorId')
                ->on('Proveedor')
                ->noActionOnDelete()
                ->noActionOnUpdate();

            $table->foreign('Producto_Proveedor_ProductoId', 'Producto_Proveedor_ProductoFK')
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
        Schema::dropIfExists('Producto_Proveedor');
    }
};
