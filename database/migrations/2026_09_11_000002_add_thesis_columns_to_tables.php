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
        Schema::table('Pedido', function (Blueprint $table) {
            if (!Schema::hasColumn('Pedido', 'PedidoMotivoAnulacion')) {
                $table->string('PedidoMotivoAnulacion', 200)->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTieneError')) {
                $table->char('PedidoTieneError', 1)->default('N');
            }
            if (!Schema::hasColumn('Pedido', 'PedidoTipoError')) {
                $table->string('PedidoTipoError', 100)->nullable();
            }
            if (!Schema::hasColumn('Pedido', 'PedidoOrigenIA')) {
                $table->char('PedidoOrigenIA', 1)->default('N');
            }
        });

        Schema::table('Producto', function (Blueprint $table) {
            if (!Schema::hasColumn('Producto', 'ProductoZona')) {
                $table->string('ProductoZona', 50)->nullable();
            }
            if (!Schema::hasColumn('Producto', 'ProductoUbicacion')) {
                $table->string('ProductoUbicacion', 50)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Pedido', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('Pedido', 'PedidoMotivoAnulacion')) $cols[] = 'PedidoMotivoAnulacion';
            if (Schema::hasColumn('Pedido', 'PedidoTieneError')) $cols[] = 'PedidoTieneError';
            if (Schema::hasColumn('Pedido', 'PedidoTipoError')) $cols[] = 'PedidoTipoError';
            if (Schema::hasColumn('Pedido', 'PedidoOrigenIA')) $cols[] = 'PedidoOrigenIA';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('Producto', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('Producto', 'ProductoZona')) $cols[] = 'ProductoZona';
            if (Schema::hasColumn('Producto', 'ProductoUbicacion')) $cols[] = 'ProductoUbicacion';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
