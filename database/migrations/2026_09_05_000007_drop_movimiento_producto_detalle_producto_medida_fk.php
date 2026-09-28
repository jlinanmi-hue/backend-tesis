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
        Schema::table('Movimiento_producto', function (Blueprint $table) {
            // Eliminar la clave foránea que relaciona Movimiento_producto con Detalle_Producto_medida
            $table->dropForeign('Movimiento_producto_Detalle_Producto_medidaFK');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Movimiento_producto', function (Blueprint $table) {
            $table->foreign(
                ['Movimiento_producto_Detalle_Producto_medida_ProductoId', 'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId'],
                'Movimiento_producto_Detalle_Producto_medidaFK'
            )->references(
                ['Detalle_Producto_medida_ProductoId', 'Detalle_Producto_medida_unidades_medidaId']
            )->on('Detalle_Producto_medida')
            ->noActionOnDelete()
            ->noActionOnUpdate();
        });
    }
};
