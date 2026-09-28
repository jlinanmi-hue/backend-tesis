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
        Schema::create('Empleado', function (Blueprint $table) {
            $table->string('EmpleadoId', 20)->primary();
            $table->string('EmpleadoNombres', 100);
            $table->string('EmpleadoApellidos', 100);
            $table->string('EmpleadoDni', 45);
            $table->string('EmpleadoTelefono', 45);
            $table->string('EmpleadoCorreo', 150);
            $table->char('EmpleadoSexo', 1);
            $table->char('EmpleadoEstado', 1)->default('A');
            $table->string('Empleado_cargoId', 20);
            $table->char('EmpleadoEliminado', 1)->default('N');
            $table->string('EmpleadoUsuarioCreacion', 100);
            $table->string('EmpleadoHostCreacion', 100);
            $table->dateTime('EmpleadoFechaCreacion');
            $table->string('EmpleadoUsuarioModificacion', 100)->nullable();
            $table->string('EmpleadoHostModificacion', 100)->nullable();
            $table->dateTime('EmpleadoFechaModificacion')->nullable();
            $table->string('EmpleadoUsuarioEliminacion', 100)->nullable();
            $table->string('EmpleadoHostEliminacion', 100)->nullable();
            $table->dateTime('EmpleadoFechaEliminacion')->nullable();

            $table->foreign('Empleado_cargoId', 'Empleado_cargoFK')
                ->references('cargoId')
                ->on('cargo')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Empleado');
    }
};
