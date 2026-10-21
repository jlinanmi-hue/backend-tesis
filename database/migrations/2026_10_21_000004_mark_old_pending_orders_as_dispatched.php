<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Marca como completados/despachados los pedidos antiguos que quedaron en estado 'P' (Pendiente)
     * de meses anteriores, liberando la cola operativa del Dashboard.
     */
    public function up(): void
    {
        $updated = DB::table('Pedido')
            ->where('PedidoEstado_pedido', 'P')
            ->where('PedidoId', '!=', 'PED-20261031')
            ->update([
                'PedidoEstado_pedido'      => 'C',
                'PedidoEstadoDespacho'     => 'ENTREGADO_COMPLETO',
                'PedidoFechaDespacho'      => DB::raw('COALESCE("PedidoFecha_pedido", NOW())'),
                'PedidoUsuarioDespacho'    => 'OPERARIO_CENTRAL',
                'PedidoFechaModificacion'  => now(),
                'PedidoUsuarioModificacion'=> 'SISTEMA_DESPACHO',
            ]);

        Log::info("Migración: {$updated} pedidos antiguos en estado 'P' marcados como despachados/completados.");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir para no reabrir órdenes históricas cerradas
    }
};
