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
        Schema::create('Canal_pedido', function (Blueprint $table) {
            $table->string('Canal_pedidoId', 20)->primary();
            $table->string('Canal_pedidoDescripcion', 100);
            $table->char('Canal_pedidoEstado', 1)->default('A');
            $table->char('Canal_pedidoEliminado', 1)->default('N');
            $table->string('Canal_pedidoUsuarioCreacion', 100);
            $table->string('Canal_pedidoHostCreacion', 100);
            $table->dateTime('Canal_pedidoFechaCreacion');
            $table->string('Canal_pedidoUsuarioModificacion', 100)->nullable();
            $table->string('Canal_pedidoHostModificacion', 100)->nullable();
            $table->dateTime('Canal_pedidoFechaModificacion')->nullable();
            $table->string('Canal_pedidoUsuarioEliminacion', 100)->nullable();
            $table->string('Canal_pedidoHostEliminacion', 100)->nullable();
            $table->dateTime('Canal_pedidoFechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Canal_pedido');
    }
};
