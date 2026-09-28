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
        Schema::table('Producto', function (Blueprint $table) {
            $table->string('producto_descripcion', 50)->nullable()->after('ProductoMarca');
            $table->string('producto_imagen', 255)->nullable()->after('producto_descripcion');
            $table->string('Producto_producto_ubi_id', 20)->nullable()->after('producto_imagen');

            $table->foreign('Producto_producto_ubi_id', 'Producto_producto_ubiFK')
                ->references('producto_ubi_id')
                ->on('producto_ubi')
                ->noActionOnDelete()
                ->noActionOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Producto', function (Blueprint $table) {
            $table->dropForeign('Producto_producto_ubiFK');
            $table->dropColumn(['producto_descripcion', 'producto_imagen', 'Producto_producto_ubi_id']);
        });
    }
};
