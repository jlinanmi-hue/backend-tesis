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
        Schema::create('producto_ubi', function (Blueprint $table) {
            $table->string('producto_ubi_id', 20)->primary();
            $table->string('producto_ubi_descripcion', 100);
            $table->char('producto_ubi_estado', 1)->default('A');
            $table->string('producto_ubi_observacion', 255)->nullable();

            // Campos de Auditoría
            $table->char('producto_ubi_Eliminado', 1)->default('N');
            $table->string('producto_ubi_UsuarioCreacion', 100);
            $table->string('producto_ubi_HostCreacion', 100);
            $table->dateTime('producto_ubi_FechaCreacion');
            $table->string('producto_ubi_UsuarioModificacion', 100)->nullable();
            $table->string('producto_ubi_HostModificacion', 100)->nullable();
            $table->dateTime('producto_ubi_FechaModificacion')->nullable();
            $table->string('producto_ubi_UsuarioEliminacion', 100)->nullable();
            $table->string('producto_ubi_HostEliminacion', 100)->nullable();
            $table->dateTime('producto_ubi_FechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_ubi');
    }
};
