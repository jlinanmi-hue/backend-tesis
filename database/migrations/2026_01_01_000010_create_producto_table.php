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
        Schema::create('Producto', function (Blueprint $table) {
            $table->string('ProductoId', 20)->primary();
            $table->string('ProductoNombre', 45);
            $table->string('Producto_Categoria_ProductoId', 20);
            $table->string('ProductoMarca', 45);
            $table->string('ProductoStockActual', 45);
            $table->string('ProductoStockMinimo', 45);
            $table->string('ProductoStockMaximo', 45);
            $table->char('ProductoEstado', 1)->default('A');
            $table->char('ProductoEliminado', 1)->default('N');
            $table->string('ProductoUsuarioCreacion', 100);
            $table->string('ProductoHostCreacion', 100);
            $table->dateTime('ProductoFechaCreacion');
            $table->string('ProductoUsuarioModificacion', 100)->nullable();
            $table->string('ProductoHostModificacion', 100)->nullable();
            $table->dateTime('ProductoFechaModificacion')->nullable();
            $table->string('ProductoUsuarioEliminacion', 100)->nullable();
            $table->string('ProductoHostEliminacion', 100)->nullable();
            $table->dateTime('ProductoFechaEliminacion')->nullable();

            $table->foreign('Producto_Categoria_ProductoId', 'Producto_Categoria_ProductoFK')
                ->references('Categoria_ProductoId')
                ->on('Categoria_Producto')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Producto');
    }
};
