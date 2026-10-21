<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Actualiza las órdenes de compra cuyos ítems fueron 100% rechazados ('R')
     * y no recibieron mercadería (total recibido = 0), cambiando su estado
     * de 'CERRADA_CON_FALTANTE' a 'RECHAZADA' con Orden_CompraCerradaConFaltante = 'N'.
     */
    public function up(): void
    {
        // Actualizar órdenes de compra que tienen al menos un ítem rechazado y 0 recibido en todos sus ítems
        $ordenesRechazadas = DB::table('Orden_Compra as oc')
            ->where('oc.Orden_CompraEliminado', 'N')
            ->whereIn('oc.Orden_CompraEstado', ['CERRADA_CON_FALTANTE', 'RECEPCION_PARCIAL'])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('Detalle_Orden_Compra as doc')
                    ->whereColumn('doc.Detalle_Orden_Compra_Orden_CompraId', 'oc.Orden_CompraId')
                    ->where('doc.Detalle_Orden_CompraEliminado', 'N')
                    ->where('doc.Detalle_Orden_CompraEntregado', 'R');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('Detalle_Orden_Compra as doc')
                    ->whereColumn('doc.Detalle_Orden_Compra_Orden_CompraId', 'oc.Orden_CompraId')
                    ->where('doc.Detalle_Orden_CompraEliminado', 'N')
                    ->where('doc.Detalle_Orden_CompraCantidadRecibida', '>', 0);
            })
            ->pluck('oc.Orden_CompraId');

        if ($ordenesRechazadas->isNotEmpty()) {
            DB::table('Orden_Compra')
                ->whereIn('Orden_CompraId', $ordenesRechazadas)
                ->update([
                    'Orden_CompraEstado' => 'RECHAZADA',
                    'Orden_CompraCerradaConFaltante' => 'N',
                    'Orden_CompraFechaModificacion' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('Orden_Compra')
            ->where('Orden_CompraEstado', 'RECHAZADA')
            ->update([
                'Orden_CompraEstado' => 'CERRADA_CON_FALTANTE',
                'Orden_CompraCerradaConFaltante' => 'S',
                'Orden_CompraFechaModificacion' => now(),
            ]);
    }
};
