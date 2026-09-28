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
        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_unidades_medidaId')) {
                $table->string('Detalle_Pedido_Productos_unidades_medidaId', 20)->nullable()->default('UND-00001')->after('Detalle_Pedido_Productos_ProductoId');
            }
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_factor_conversion')) {
                $table->decimal('Detalle_Pedido_Productos_factor_conversion', 10, 2)->default(1.00)->after('Detalle_Pedido_Productos_unidades_medidaId');
            }
            if (!Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_base')) {
                $table->decimal('Detalle_Pedido_Productos_cantidad_base', 10, 2)->default(1.00)->after('Detalle_Pedido_Productos_cantidad');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Detalle_Pedido_Productos', function (Blueprint $table) {
            if (Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_cantidad_base')) {
                $table->dropColumn('Detalle_Pedido_Productos_cantidad_base');
            }
            if (Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_factor_conversion')) {
                $table->dropColumn('Detalle_Pedido_Productos_factor_conversion');
            }
            if (Schema::hasColumn('Detalle_Pedido_Productos', 'Detalle_Pedido_Productos_unidades_medidaId')) {
                $table->dropColumn('Detalle_Pedido_Productos_unidades_medidaId');
            }
        });
    }
};
