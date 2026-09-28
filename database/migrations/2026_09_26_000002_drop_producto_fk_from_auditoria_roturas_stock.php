<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Eliminar la Foreign Key entre Producto y auditoria_roturas_stock si existe
        $fks = DB::select("SELECT name FROM sys.foreign_keys WHERE name = 'auditoria_roturas_stock_producto_id_foreign'");
        if (!empty($fks)) {
            DB::statement("ALTER TABLE auditoria_roturas_stock DROP CONSTRAINT auditoria_roturas_stock_producto_id_foreign");
        }

        // 2. Eliminar la columna producto_id de auditoria_roturas_stock si existe
        if (Schema::hasColumn('auditoria_roturas_stock', 'producto_id')) {
            Schema::table('auditoria_roturas_stock', function (Blueprint $table) {
                $table->dropColumn('producto_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('auditoria_roturas_stock') && !Schema::hasColumn('auditoria_roturas_stock', 'producto_id')) {
            Schema::table('auditoria_roturas_stock', function (Blueprint $table) {
                $table->string('producto_id', 20)->nullable()->after('pedido_id');
                $table->foreign('producto_id')
                    ->references('ProductoId')
                    ->on('Producto')
                    ->noActionOnDelete();
            });
        }
    }
};
