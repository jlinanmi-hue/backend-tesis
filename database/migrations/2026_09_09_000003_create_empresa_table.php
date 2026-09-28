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
        if (!Schema::hasTable('Empresa')) {
            Schema::create('Empresa', function (Blueprint $table) {
                $table->id('Id_Empresa');
                $table->string('EmpresaRuc', 11)->unique();
                $table->string('EmpresaRazonSocial', 255);
                $table->string('EmpresaNombreComercial', 255)->nullable();
                $table->string('EmpresaDireccion', 500)->nullable();
                $table->string('EmpresaDepartamento', 100)->nullable();
                $table->string('EmpresaProvincia', 100)->nullable();
                $table->string('EmpresaDistrito', 100)->nullable();
                $table->string('EmpresaUbigeo', 20)->nullable();
                $table->string('EmpresaTelefono', 50)->nullable();
                $table->string('EmpresaWhatsapp', 50)->nullable();
                $table->string('EmpresaEmail', 150)->nullable();
                $table->longText('EmpresaLogo')->nullable();
                $table->text('EmpresaMensajeTicket')->nullable();
                $table->char('EmpresaEstado', 1)->default('A');

                // Auditoría
                $table->string('EmpresaUsuarioCreacion', 100)->nullable();
                $table->string('EmpresaHostCreacion', 100)->nullable();
                $table->dateTime('EmpresaFechaCreacion')->useCurrent();
                $table->string('EmpresaUsuarioModificacion', 100)->nullable();
                $table->string('EmpresaHostModificacion', 100)->nullable();
                $table->dateTime('EmpresaFechaModificacion')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Empresa');
    }
};
