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
        Schema::create('Usuario', function (Blueprint $table) {
            $table->string('UsuarioId', 20)->primary();
            $table->string('UsuarioUserName', 150);
            $table->string('UsuarioPassword', 255);
            $table->string('Usuario_Rol_UserId', 20);
            $table->string('Usuario_EmpleadoId', 20);
            $table->char('UsuarioEliminado', 1)->default('N');
            $table->string('UsuarioUsuarioCreacion', 100);
            $table->string('UsuarioHostCreacion', 100);
            $table->dateTime('UsuarioFechaCreacion');
            $table->string('UsuarioUsuarioModificacion', 100)->nullable();
            $table->string('UsuarioHostModificacion', 100)->nullable();
            $table->dateTime('UsuarioFechaModificacion')->nullable();
            $table->string('UsuarioUsuarioEliminacion', 100)->nullable();
            $table->string('UsuarioHostEliminacion', 100)->nullable();
            $table->dateTime('UsuarioFechaEliminacion')->nullable();

            $table->foreign('Usuario_Rol_UserId', 'Usuario_Rol_UserFK')
                ->references('Rol_UserId')
                ->on('Rol_User')
                ->noActionOnDelete()
                ->noActionOnUpdate();

            $table->foreign('Usuario_EmpleadoId', 'Usuario_EmpleadoFK')
                ->references('EmpleadoId')
                ->on('Empleado')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Usuario');
    }
};
