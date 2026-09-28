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
        Schema::create('Rol_User', function (Blueprint $table) {
            $table->string('Rol_UserId', 20)->primary();
            $table->string('Rol_UserEmpleadocol', 45);
            $table->string('Rol_UserRol_Usercol', 45);
            $table->char('Rol_UserEliminado', 1)->default('N');
            $table->string('Rol_UserUsuarioCreacion', 100);
            $table->string('Rol_UserHostCreacion', 100);
            $table->dateTime('Rol_UserFechaCreacion');
            $table->string('Rol_UserUsuarioModificacion', 100)->nullable();
            $table->string('Rol_UserHostModificacion', 100)->nullable();
            $table->dateTime('Rol_UserFechaModificacion')->nullable();
            $table->string('Rol_UserUsuarioEliminacion', 100)->nullable();
            $table->string('Rol_UserHostEliminacion', 100)->nullable();
            $table->dateTime('Rol_UserFechaEliminacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Rol_User');
    }
};
