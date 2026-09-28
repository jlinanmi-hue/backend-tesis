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
        Schema::create('Ajuste_inventario', function (Blueprint $table) {
            $table->string('Ajuste_inventario_id', 20)->primary();
            $table->string('Ajuste_inventario_ProductoId', 20);
            $table->string('Ajuste_inventario_unidades_medidaId', 20)->nullable();
            $table->string('Ajuste_inventario_tipo', 30); // Merma, Dañado, Vencido, Rotura, Pérdida, Ajuste
            $table->decimal('Ajuste_inventario_cantidad', 10, 2);
            $table->string('Ajuste_inventario_motivo', 255);
            $table->dateTime('Ajuste_inventario_fecha');

            // Campos de Auditoría
            $table->char('Ajuste_inventario_Eliminado', 1)->default('N');
            $table->string('Ajuste_inventario_UsuarioCreacion', 100);
            $table->string('Ajuste_inventario_HostCreacion', 100);
            $table->dateTime('Ajuste_inventario_FechaCreacion');
            $table->string('Ajuste_inventario_UsuarioModificacion', 100)->nullable();
            $table->string('Ajuste_inventario_HostModificacion', 100)->nullable();
            $table->dateTime('Ajuste_inventario_FechaModificacion')->nullable();
            $table->string('Ajuste_inventario_UsuarioEliminacion', 100)->nullable();
            $table->string('Ajuste_inventario_HostEliminacion', 100)->nullable();
            $table->dateTime('Ajuste_inventario_FechaEliminacion')->nullable();

            // Relación con Producto
            $table->foreign('Ajuste_inventario_ProductoId', 'Ajuste_inventario_ProductoFK')
                ->references('ProductoId')
                ->on('Producto')
                ->noActionOnDelete()
                ->noActionOnUpdate();

            // Relación con Unidades de Medida
            $table->foreign('Ajuste_inventario_unidades_medidaId', 'Ajuste_inventario_unidades_medidaFK')
                ->references('unidades_medidaId')
                ->on('unidades_medida')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Ajuste_inventario');
    }
};
