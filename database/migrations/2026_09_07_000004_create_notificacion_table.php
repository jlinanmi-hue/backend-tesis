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
        if (!Schema::hasTable('Notificacion')) {
            Schema::create('Notificacion', function (Blueprint $table) {
                $table->string('NotificacionId', 30)->primary();
                $table->string('NotificacionTitulo', 100);
                $table->string('NotificacionMensaje', 255);
                $table->string('NotificacionTipo', 30);
                $table->string('NotificacionCategoria', 50)->nullable();
                $table->string('NotificacionReferenciaId', 30)->nullable();
                $table->text('NotificacionDatos')->nullable();
                $table->char('NotificacionLeida', 1)->default('N');
                $table->char('NotificacionSilenciosa', 1)->default('N');
                $table->dateTime('NotificacionFecha');
                $table->string('NotificacionUsuarioId', 20)->nullable();
                $table->char('NotificacionEliminado', 1)->default('N');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Notificacion');
    }
};
