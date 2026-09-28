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
        Schema::create('Cliente', function (Blueprint $table) {
            $table->string('ClienteId', 20)->primary();
            $table->string('ClienteNombre', 100);
            $table->string('ClienteDireccion', 200);
            $table->string('ClienteNumero', 45);
            $table->char('ClienteEstado', 1)->default('A');
            $table->string('ClienteRuc', 45);
            $table->char('ClienteEliminado', 1)->default('N');
            $table->string('ClienteUsuarioCreacion', 100);
            $table->string('ClienteHostCreacion', 100);
            $table->dateTime('ClienteFechaCreacion');
            $table->string('ClienteUsuarioModificacion', 100)->nullable();
            $table->string('ClienteHostModificacion', 100)->nullable();
            $table->dateTime('ClienteFechaModificacion')->nullable();
            $table->string('ClienteUsuarioEliminacion', 100)->nullable();
            $table->string('ClienteHostEliminacion', 100)->nullable();
            $table->dateTime('ClienteFechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Cliente');
    }
};
