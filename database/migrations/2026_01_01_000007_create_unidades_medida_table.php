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
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->string('unidades_medidaId', 20)->primary();
            $table->string('unidades_medidaDescripcionUnidades', 45);
            $table->char('unidades_medidaEstadoUnidades', 1)->default('A');
            $table->char('unidades_medidaEliminado', 1)->default('N');
            $table->string('unidades_medidaUsuarioCreacion', 100);
            $table->string('unidades_medidaHostCreacion', 100);
            $table->dateTime('unidades_medidaFechaCreacion');
            $table->string('unidades_medidaUsuarioModificacion', 100)->nullable();
            $table->string('unidades_medidaHostModificacion', 100)->nullable();
            $table->dateTime('unidades_medidaFechaModificacion')->nullable();
            $table->string('unidades_medidaUsuarioEliminacion', 100)->nullable();
            $table->string('unidades_medidaHostEliminacion', 100)->nullable();
            $table->dateTime('unidades_medidaFechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};
