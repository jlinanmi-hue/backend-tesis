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
        Schema::table('Cliente', function (Blueprint $table) {
            $table->integer('ClienteComprasCount')->default(0)->index('idx_cliente_compras_count');
            $table->decimal('ClienteTotalMonto', 12, 2)->default(0.00);
            $table->dateTime('ClienteUltimaCompra')->nullable()->index('idx_cliente_ultima_compra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Cliente', function (Blueprint $table) {
            $table->dropIndex('idx_cliente_compras_count');
            $table->dropIndex('idx_cliente_ultima_compra');
            $table->dropColumn(['ClienteComprasCount', 'ClienteTotalMonto', 'ClienteUltimaCompra']);
        });
    }
};
