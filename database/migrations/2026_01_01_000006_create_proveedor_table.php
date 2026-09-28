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
        Schema::create('Proveedor', function (Blueprint $table) {
            $table->string('ProveedorId', 20)->primary();
            $table->string('ProveedorRuc', 45);
            $table->string('ProveedorRazonSocial', 45);
            $table->string('ProveedorTipoContribuyente', 45);
            $table->char('ProveedorEstado', 1)->default('A');
            $table->string('ProveedorActividadEconomica', 45);
            $table->string('ProveedorTelefono', 45);
            $table->char('ProveedorEliminado', 1)->default('N');
            $table->string('ProveedorUsuarioCreacion', 100);
            $table->string('ProveedorHostCreacion', 100);
            $table->dateTime('ProveedorFechaCreacion');
            $table->string('ProveedorUsuarioModificacion', 100)->nullable();
            $table->string('ProveedorHostModificacion', 100)->nullable();
            $table->dateTime('ProveedorFechaModificacion')->nullable();
            $table->string('ProveedorUsuarioEliminacion', 100)->nullable();
            $table->string('ProveedorHostEliminacion', 100)->nullable();
            $table->dateTime('ProveedorFechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Proveedor');
    }
};
