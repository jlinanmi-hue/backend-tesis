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
        Schema::create('Categoria_Producto', function (Blueprint $table) {
            $table->string('Categoria_ProductoId', 20)->primary();
            $table->string('Categoria_ProductoDescripcion_categoria', 45);
            $table->char('Categoria_ProductoEstado', 1)->default('A');
            $table->char('Categoria_ProductoEliminado', 1)->default('N');
            $table->string('Categoria_ProductoUsuarioCreacion', 100);
            $table->string('Categoria_ProductoHostCreacion', 100);
            $table->dateTime('Categoria_ProductoFechaCreacion');
            $table->string('Categoria_ProductoUsuarioModificacion', 100)->nullable();
            $table->string('Categoria_ProductoHostModificacion', 100)->nullable();
            $table->dateTime('Categoria_ProductoFechaModificacion')->nullable();
            $table->string('Categoria_ProductoUsuarioEliminacion', 100)->nullable();
            $table->string('Categoria_ProductoHostEliminacion', 100)->nullable();
            $table->dateTime('Categoria_ProductoFechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Categoria_Producto');
    }
};
