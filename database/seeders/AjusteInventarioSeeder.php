<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AjusteInventarioSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Ajuste_inventario`.
     * Total records: 5
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Ajuste_inventario')->delete();

        $rows = [
            [
                'Ajuste_inventario_id' => 'AJU-00001',
                'Ajuste_inventario_ProductoId' => 'PROD-00001',
                'Ajuste_inventario_tipo' => 'Merma',
                'Ajuste_inventario_cantidad' => '1.00',
                'Ajuste_inventario_motivo' => 'Saco de 50 kg se derramó en piso húmedo, pérdida de 1 kg no apto para venta.',
                'Ajuste_inventario_fecha' => '2026-09-05 06:51:48.757',
                'Ajuste_inventario_Eliminado' => 'S',
                'Ajuste_inventario_UsuarioCreacion' => 'SYSTEM',
                'Ajuste_inventario_HostCreacion' => '127.0.0.1',
                'Ajuste_inventario_FechaCreacion' => '2026-09-05 06:51:48.757',
                'Ajuste_inventario_UsuarioModificacion' => null,
                'Ajuste_inventario_HostModificacion' => null,
                'Ajuste_inventario_FechaModificacion' => null,
                'Ajuste_inventario_UsuarioEliminacion' => 'ADMIN',
                'Ajuste_inventario_HostEliminacion' => '127.0.0.1',
                'Ajuste_inventario_FechaEliminacion' => '2026-09-08 15:36:21.580',
            ],
            [
                'Ajuste_inventario_id' => 'AJU-00002',
                'Ajuste_inventario_ProductoId' => 'PROD-00005',
                'Ajuste_inventario_tipo' => 'Dañado',
                'Ajuste_inventario_cantidad' => '2.00',
                'Ajuste_inventario_motivo' => 'botellas picadas',
                'Ajuste_inventario_fecha' => '2026-09-08 15:27:16.000',
                'Ajuste_inventario_Eliminado' => 'N',
                'Ajuste_inventario_UsuarioCreacion' => 'ADMIN',
                'Ajuste_inventario_HostCreacion' => '127.0.0.1',
                'Ajuste_inventario_FechaCreacion' => '2026-09-08 15:27:16.523',
                'Ajuste_inventario_UsuarioModificacion' => null,
                'Ajuste_inventario_HostModificacion' => null,
                'Ajuste_inventario_FechaModificacion' => null,
                'Ajuste_inventario_UsuarioEliminacion' => null,
                'Ajuste_inventario_HostEliminacion' => null,
                'Ajuste_inventario_FechaEliminacion' => null,
            ],
            [
                'Ajuste_inventario_id' => 'AJU-00003',
                'Ajuste_inventario_ProductoId' => 'PROD-00002',
                'Ajuste_inventario_tipo' => 'Vencido',
                'Ajuste_inventario_cantidad' => '15.00',
                'Ajuste_inventario_motivo' => 'producto vencido',
                'Ajuste_inventario_fecha' => '2026-09-08 15:35:24.000',
                'Ajuste_inventario_Eliminado' => 'N',
                'Ajuste_inventario_UsuarioCreacion' => 'ADMIN',
                'Ajuste_inventario_HostCreacion' => '127.0.0.1',
                'Ajuste_inventario_FechaCreacion' => '2026-09-08 15:35:24.253',
                'Ajuste_inventario_UsuarioModificacion' => null,
                'Ajuste_inventario_HostModificacion' => null,
                'Ajuste_inventario_FechaModificacion' => null,
                'Ajuste_inventario_UsuarioEliminacion' => null,
                'Ajuste_inventario_HostEliminacion' => null,
                'Ajuste_inventario_FechaEliminacion' => null,
            ],
            [
                'Ajuste_inventario_id' => 'AJU-00004',
                'Ajuste_inventario_ProductoId' => 'PROD-00001',
                'Ajuste_inventario_tipo' => 'Merma',
                'Ajuste_inventario_cantidad' => '5.00',
                'Ajuste_inventario_motivo' => 'arroz desperdicio',
                'Ajuste_inventario_fecha' => '2026-09-08 15:37:09.000',
                'Ajuste_inventario_Eliminado' => 'N',
                'Ajuste_inventario_UsuarioCreacion' => 'ADMIN',
                'Ajuste_inventario_HostCreacion' => '127.0.0.1',
                'Ajuste_inventario_FechaCreacion' => '2026-09-08 15:37:09.737',
                'Ajuste_inventario_UsuarioModificacion' => null,
                'Ajuste_inventario_HostModificacion' => null,
                'Ajuste_inventario_FechaModificacion' => null,
                'Ajuste_inventario_UsuarioEliminacion' => null,
                'Ajuste_inventario_HostEliminacion' => null,
                'Ajuste_inventario_FechaEliminacion' => null,
            ],
            [
                'Ajuste_inventario_id' => 'AJU-00005',
                'Ajuste_inventario_ProductoId' => 'PROD-00005',
                'Ajuste_inventario_tipo' => 'Merma',
                'Ajuste_inventario_cantidad' => '1.00',
                'Ajuste_inventario_motivo' => 'Prueba de registro de hora exacta',
                'Ajuste_inventario_fecha' => '2026-09-08 20:26:00.000',
                'Ajuste_inventario_Eliminado' => 'S',
                'Ajuste_inventario_UsuarioCreacion' => 'ADMIN',
                'Ajuste_inventario_HostCreacion' => '127.0.0.1',
                'Ajuste_inventario_FechaCreacion' => '2026-09-08 20:26:19.657',
                'Ajuste_inventario_UsuarioModificacion' => null,
                'Ajuste_inventario_HostModificacion' => null,
                'Ajuste_inventario_FechaModificacion' => null,
                'Ajuste_inventario_UsuarioEliminacion' => 'ADMIN',
                'Ajuste_inventario_HostEliminacion' => '127.0.0.1',
                'Ajuste_inventario_FechaEliminacion' => '2026-09-08 20:26:19.683',
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Ajuste_inventario')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all"');
    }
}
