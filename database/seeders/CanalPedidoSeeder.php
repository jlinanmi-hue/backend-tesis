<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CanalPedidoSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Canal_pedido`.
     * Total records: 3
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Canal_pedido')->delete();

        $rows = [
            [
                'Canal_pedidoId' => 'CNL-00001',
                'Canal_pedidoDescripcion' => 'Tienda Presencial',
                'Canal_pedidoEstado' => 'A',
                'Canal_pedidoEliminado' => 'N',
                'Canal_pedidoUsuarioCreacion' => 'ADMIN',
                'Canal_pedidoHostCreacion' => '127.0.0.1',
                'Canal_pedidoFechaCreacion' => '2026-09-02 09:24:24.787',
                'Canal_pedidoUsuarioModificacion' => null,
                'Canal_pedidoHostModificacion' => null,
                'Canal_pedidoFechaModificacion' => null,
                'Canal_pedidoUsuarioEliminacion' => null,
                'Canal_pedidoHostEliminacion' => null,
                'Canal_pedidoFechaEliminacion' => null,
            ],
            [
                'Canal_pedidoId' => 'CNL-00002',
                'Canal_pedidoDescripcion' => 'App Movil',
                'Canal_pedidoEstado' => 'I',
                'Canal_pedidoEliminado' => 'S',
                'Canal_pedidoUsuarioCreacion' => 'ADMIN',
                'Canal_pedidoHostCreacion' => '127.0.0.1',
                'Canal_pedidoFechaCreacion' => '2026-09-02 09:24:38.390',
                'Canal_pedidoUsuarioModificacion' => null,
                'Canal_pedidoHostModificacion' => null,
                'Canal_pedidoFechaModificacion' => null,
                'Canal_pedidoUsuarioEliminacion' => 'ADMIN',
                'Canal_pedidoHostEliminacion' => '127.0.0.1',
                'Canal_pedidoFechaEliminacion' => '2026-09-02 09:25:00.697',
            ],
            [
                'Canal_pedidoId' => 'CNL-00003',
                'Canal_pedidoDescripcion' => 'WhatsApp',
                'Canal_pedidoEstado' => 'A',
                'Canal_pedidoEliminado' => 'N',
                'Canal_pedidoUsuarioCreacion' => 'ADMIN',
                'Canal_pedidoHostCreacion' => '127.0.0.1',
                'Canal_pedidoFechaCreacion' => '2026-09-02 09:24:56.340',
                'Canal_pedidoUsuarioModificacion' => null,
                'Canal_pedidoHostModificacion' => null,
                'Canal_pedidoFechaModificacion' => null,
                'Canal_pedidoUsuarioEliminacion' => null,
                'Canal_pedidoHostEliminacion' => null,
                'Canal_pedidoFechaEliminacion' => null,
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Canal_pedido')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
