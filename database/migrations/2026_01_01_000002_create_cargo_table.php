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
        Schema::create('cargo', function (Blueprint $table) {
            $table->string('cargoId', 20)->primary();
            $table->string('cargoDescripcion', 45);
            $table->char('cargoEstado', 1)->default('A');
            $table->char('cargoEliminado', 1)->default('N');
            $table->string('cargoUsuarioCreacion', 100);
            $table->string('cargoHostCreacion', 100);
            $table->dateTime('cargoFechaCreacion');
            $table->string('cargoUsuarioModificacion', 100)->nullable();
            $table->string('cargoHostModificacion', 100)->nullable();
            $table->dateTime('cargoFechaModificacion')->nullable();
            $table->string('cargoUsuarioEliminacion', 100)->nullable();
            $table->string('cargoHostEliminacion', 100)->nullable();
            $table->dateTime('cargoFechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargo');
    }
};
