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
        Schema::create('Orden_Compra', function (Blueprint $table) {
            $table->string('Orden_CompraId', 20)->primary();
            $table->dateTime('Orden_CompraFecha')->useCurrent();
            $table->decimal('Orden_CompraSubtotal', 10, 2)->default(0.00);
            $table->decimal('Orden_CompraIgv', 10, 2)->default(0.00);
            $table->decimal('Orden_CompraTotal', 10, 2)->default(0.00);
            $table->char('Orden_CompraEstado', 1)->default('P'); // 'P' = Pendiente, 'C' = Completada/Recibida, 'A' = Anulada
            $table->string('Orden_CompraObservacion', 250)->nullable();
            
            // Proveedor asignado (puede ser null al crear y asignarse después)
            $table->string('Orden_Compra_ProveedorId', 20)->nullable();

            // Auditoría Estándar
            $table->char('Orden_CompraEliminado', 1)->default('N');
            $table->string('Orden_CompraUsuarioCreacion', 100);
            $table->string('Orden_CompraHostCreacion', 100);
            $table->dateTime('Orden_CompraFechaCreacion')->useCurrent();
            $table->string('Orden_CompraUsuarioModificacion', 100)->nullable();
            $table->string('Orden_CompraHostModificacion', 100)->nullable();
            $table->dateTime('Orden_CompraFechaModificacion')->nullable();
            $table->string('Orden_CompraUsuarioEliminacion', 100)->nullable();
            $table->string('Orden_CompraHostEliminacion', 100)->nullable();
            $table->dateTime('Orden_CompraFechaEliminacion')->nullable();

            // Relación con Proveedor
            $table->foreign('Orden_Compra_ProveedorId', 'FK_Orden_Compra_Proveedor')
                ->references('ProveedorId')
                ->on('Proveedor')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Orden_Compra');
    }
};
