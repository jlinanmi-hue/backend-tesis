<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProveedorSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Proveedor`.
     * Total records: 7
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Proveedor')->delete();

        $rows = [
            [
                'ProveedorId' => 'PRV-00001',
                'ProveedorRuc' => '20100017491',
                'ProveedorRazonSocial' => 'Gloria S.A.',
                'ProveedorTipoContribuyente' => 'Sociedad Anónima',
                'ProveedorEstado' => 'A',
                'ProveedorActividadEconomica' => 'Fabricación de productos lácteos',
                'ProveedorTelefono' => '014115000',
                'ProveedorEliminado' => 'N',
                'ProveedorUsuarioCreacion' => 'SYSTEM',
                'ProveedorHostCreacion' => '127.0.0.1',
                'ProveedorFechaCreacion' => '2026-08-24 10:34:15.997',
                'ProveedorUsuarioModificacion' => null,
                'ProveedorHostModificacion' => null,
                'ProveedorFechaModificacion' => null,
                'ProveedorUsuarioEliminacion' => null,
                'ProveedorHostEliminacion' => null,
                'ProveedorFechaEliminacion' => null,
            ],
            [
                'ProveedorId' => 'PRV-00002',
                'ProveedorRuc' => '20100055237',
                'ProveedorRazonSocial' => 'Alicorp S.A.A.',
                'ProveedorTipoContribuyente' => 'Sociedad Anónima Abierta',
                'ProveedorEstado' => 'A',
                'ProveedorActividadEconomica' => 'Elaboración de alimentos y abarrotes',
                'ProveedorTelefono' => '013150800',
                'ProveedorEliminado' => 'N',
                'ProveedorUsuarioCreacion' => 'SYSTEM',
                'ProveedorHostCreacion' => '127.0.0.1',
                'ProveedorFechaCreacion' => '2026-08-24 10:34:15.997',
                'ProveedorUsuarioModificacion' => null,
                'ProveedorHostModificacion' => null,
                'ProveedorFechaModificacion' => null,
                'ProveedorUsuarioEliminacion' => null,
                'ProveedorHostEliminacion' => null,
                'ProveedorFechaEliminacion' => null,
            ],
            [
                'ProveedorId' => 'PRV-00003',
                'ProveedorRuc' => '20100100127',
                'ProveedorRazonSocial' => 'Arca Continental Lindley S.A.',
                'ProveedorTipoContribuyente' => 'Sociedad Anónima',
                'ProveedorEstado' => 'A',
                'ProveedorActividadEconomica' => 'Distribución de bebidas y refrescos',
                'ProveedorTelefono' => '013116000',
                'ProveedorEliminado' => 'N',
                'ProveedorUsuarioCreacion' => 'SYSTEM',
                'ProveedorHostCreacion' => '127.0.0.1',
                'ProveedorFechaCreacion' => '2026-08-24 10:34:15.997',
                'ProveedorUsuarioModificacion' => null,
                'ProveedorHostModificacion' => null,
                'ProveedorFechaModificacion' => null,
                'ProveedorUsuarioEliminacion' => null,
                'ProveedorHostEliminacion' => null,
                'ProveedorFechaEliminacion' => null,
            ],
            [
                'ProveedorId' => 'PRV-00004',
                'ProveedorRuc' => '20501234567',
                'ProveedorRazonSocial' => 'Distribuidora San Jorge S.A.C.',
                'ProveedorTipoContribuyente' => 'Sociedad Anónima Cerrada',
                'ProveedorEstado' => 'A',
                'ProveedorActividadEconomica' => 'Distribuidora mayorista de útiles',
                'ProveedorTelefono' => '014223344',
                'ProveedorEliminado' => 'N',
                'ProveedorUsuarioCreacion' => 'SYSTEM',
                'ProveedorHostCreacion' => '127.0.0.1',
                'ProveedorFechaCreacion' => '2026-08-24 10:34:15.997',
                'ProveedorUsuarioModificacion' => null,
                'ProveedorHostModificacion' => null,
                'ProveedorFechaModificacion' => null,
                'ProveedorUsuarioEliminacion' => null,
                'ProveedorHostEliminacion' => null,
                'ProveedorFechaEliminacion' => null,
            ],
            [
                'ProveedorId' => 'PRV-00005',
                'ProveedorRuc' => '10181935451',
                'ProveedorRazonSocial' => 'VALENCIA VILLANUEVA BERNABE',
                'ProveedorTipoContribuyente' => 'GENERAL',
                'ProveedorEstado' => 'A',
                'ProveedorActividadEconomica' => 'VENTA AL POR MAYOR NO ESPECIALIZADA',
                'ProveedorTelefono' => '900292514',
                'ProveedorEliminado' => 'N',
                'ProveedorUsuarioCreacion' => 'ADMIN',
                'ProveedorHostCreacion' => '127.0.0.1',
                'ProveedorFechaCreacion' => '2026-09-07 22:25:50.443',
                'ProveedorUsuarioModificacion' => 'ADMIN',
                'ProveedorHostModificacion' => '127.0.0.1',
                'ProveedorFechaModificacion' => '2026-09-09 00:33:34.463',
                'ProveedorUsuarioEliminacion' => null,
                'ProveedorHostEliminacion' => null,
                'ProveedorFechaEliminacion' => null,
            ],
            [
                'ProveedorId' => 'PRV-00006',
                'ProveedorRuc' => '20615580610',
                'ProveedorRazonSocial' => 'VELION TECHNOLOGY E.I.R.L.',
                'ProveedorTipoContribuyente' => 'GENERAL',
                'ProveedorEstado' => 'A',
                'ProveedorActividadEconomica' => 'ACTIVIDAD COMERCIAL GENERAL',
                'ProveedorTelefono' => '987462136',
                'ProveedorEliminado' => 'N',
                'ProveedorUsuarioCreacion' => 'ADMIN',
                'ProveedorHostCreacion' => '127.0.0.1',
                'ProveedorFechaCreacion' => '2026-09-16 18:01:32.067',
                'ProveedorUsuarioModificacion' => null,
                'ProveedorHostModificacion' => null,
                'ProveedorFechaModificacion' => null,
                'ProveedorUsuarioEliminacion' => null,
                'ProveedorHostEliminacion' => null,
                'ProveedorFechaEliminacion' => null,
            ],
            [
                'ProveedorId' => 'PRV-00007',
                'ProveedorRuc' => '20230131253',
                'ProveedorRazonSocial' => 'PROVEEDOR TEST AUDIT S.A.C. MODIFICADO',
                'ProveedorTipoContribuyente' => 'GENERAL',
                'ProveedorEstado' => 'I',
                'ProveedorActividadEconomica' => '-',
                'ProveedorTelefono' => '999111222',
                'ProveedorEliminado' => 'S',
                'ProveedorUsuarioCreacion' => 'ADMIN',
                'ProveedorHostCreacion' => '127.0.0.1',
                'ProveedorFechaCreacion' => '2026-09-26 19:17:46.843',
                'ProveedorUsuarioModificacion' => 'ADMIN',
                'ProveedorHostModificacion' => '127.0.0.1',
                'ProveedorFechaModificacion' => '2026-09-26 19:17:46.997',
                'ProveedorUsuarioEliminacion' => 'ADMIN',
                'ProveedorHostEliminacion' => '127.0.0.1',
                'ProveedorFechaEliminacion' => '2026-09-26 19:17:47.020',
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Proveedor')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
