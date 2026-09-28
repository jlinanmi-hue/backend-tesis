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
        // 1. Agregar campos de Stock Virtual a la tabla Producto
        Schema::table('Producto', function (Blueprint $table) {
            if (!Schema::hasColumn('Producto', 'ProductoStockVirtual')) {
                $table->decimal('ProductoStockVirtual', 10, 2)->default(0.00)->after('ProductoStockMaximo');
            }
            if (!Schema::hasColumn('Producto', 'ProductoStockVirtualConsumido')) {
                $table->decimal('ProductoStockVirtualConsumido', 10, 2)->default(0.00)->after('ProductoStockVirtual');
            }
        });

        // 2. Agregar campos de desglose físico y virtual en Detalle_Pedido_Productos
        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_fisica')) {
                $table->decimal('Detalle_Pedido_Productos_cantidad_fisica', 10, 2)->default(0.00)->after('Detalle_Pedido_Productos_cantidad');
            }
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_virtual')) {
                $table->decimal('Detalle_Pedido_Productos_cantidad_virtual', 10, 2)->default(0.00)->after('Detalle_Pedido_Productos_cantidad_fisica');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Producto', function (Blueprint $table) {
            if (Schema::hasColumn('Producto', 'ProductoStockVirtualConsumido')) {
                $table->dropColumn('ProductoStockVirtualConsumido');
            }
            if (Schema::hasColumn('Producto', 'ProductoStockVirtual')) {
                $table->dropColumn('ProductoStockVirtual');
            }
        });

        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            if (Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_virtual')) {
                $table->dropColumn('Detalle_Pedido_Productos_cantidad_virtual');
            }
            if (Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_fisica')) {
                $table->dropColumn('Detalle_Pedido_Productos_cantidad_fisica');
            }
        });
    }
};
