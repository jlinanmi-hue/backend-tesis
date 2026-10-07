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
        if (Schema::hasTable('Pedido')) {
            Schema::table('Pedido', function (Blueprint $table) {
                if (!Schema::hasColumn('Pedido', 'PedidoLatitudEntrega')) {
                    $table->decimal('PedidoLatitudEntrega', 10, 7)->nullable()->after('PedidoDireccionEntrega');
                }
                if (!Schema::hasColumn('Pedido', 'PedidoLongitudEntrega')) {
                    $table->decimal('PedidoLongitudEntrega', 10, 7)->nullable()->after('PedidoLatitudEntrega');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('Pedido')) {
            Schema::table('Pedido', function (Blueprint $table) {
                if (Schema::hasColumn('Pedido', 'PedidoLongitudEntrega')) {
                    $table->dropColumn('PedidoLongitudEntrega');
                }
                if (Schema::hasColumn('Pedido', 'PedidoLatitudEntrega')) {
                    $table->dropColumn('PedidoLatitudEntrega');
                }
            });
        }
    }
};
