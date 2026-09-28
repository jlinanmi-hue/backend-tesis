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
        Schema::create('Consulta_Ruc', function (Blueprint $table) {
            $table->id('ConsultaRucId');
            $table->string('ConsultaRucNumero', 11)->unique()->index();
            $table->string('ConsultaRucRazonSocial', 255);
            $table->string('ConsultaRucEstado', 50)->default('ACTIVO');
            $table->string('ConsultaRucCondicion', 50)->default('HABIDO');
            $table->string('ConsultaRucDireccion', 500)->default('DIRECCION NO REGISTRADA');
            $table->string('ConsultaRucDepartamento', 100)->nullable();
            $table->string('ConsultaRucProvincia', 100)->nullable();
            $table->string('ConsultaRucDistrito', 100)->nullable();
            $table->string('ConsultaRucUbigeo', 20)->nullable();
            $table->string('ConsultaRucTipoContribuyente', 150)->nullable();
            $table->string('ConsultaRucFechaInscripcion', 50)->nullable();
            $table->string('ConsultaRucActividadEconomica', 255)->nullable();
            $table->string('ConsultaRucTelefono', 50)->nullable();
            $table->longText('ConsultaRucRawJson')->nullable();
            $table->integer('ConsultaRucVecesConsultado')->default(1);
            $table->dateTime('ConsultaRucUltimaConsulta');

            // Campos de Auditoría
            $table->string('ConsultaRucUsuarioCreacion', 100)->nullable();
            $table->string('ConsultaRucHostCreacion', 100)->nullable();
            $table->dateTime('ConsultaRucFechaCreacion');
            $table->string('ConsultaRucUsuarioModificacion', 100)->nullable();
            $table->string('ConsultaRucHostModificacion', 100)->nullable();
            $table->dateTime('ConsultaRucFechaModificacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Consulta_Ruc');
    }
};
