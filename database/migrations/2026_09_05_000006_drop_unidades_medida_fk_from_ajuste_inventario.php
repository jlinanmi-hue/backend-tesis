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
        Schema::table('Ajuste_inventario', function (Blueprint $table) {
            // Eliminar relación de unidades_medida con Ajuste_inventario
            $table->dropForeign('Ajuste_inventario_unidades_medidaFK');
            $table->dropColumn('Ajuste_inventario_unidades_medidaId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Ajuste_inventario', function (Blueprint $table) {
            $table->string('Ajuste_inventario_unidades_medidaId', 20)->nullable()->after('Ajuste_inventario_ProductoId');
            $table->foreign('Ajuste_inventario_unidades_medidaId', 'Ajuste_inventario_unidades_medidaFK')
                ->references('unidades_medidaId')
                ->on('unidades_medida')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }
};
