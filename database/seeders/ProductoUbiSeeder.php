<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoUbiSeeder extends Seeder
{
    /**
     * Auto-generated from database table `producto_ubi`.
     * Total records: 3
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('producto_ubi')->delete();

        $rows = [
            [
                'producto_ubi_id' => 'UBI-00001',
                'producto_ubi_descripcion' => 'Almacén Central - piso1',
                'producto_ubi_estado' => 'A',
                'producto_ubi_observacion' => 'Zona de productos secos y abarrotes pesados',
                'producto_ubi_Eliminado' => 'N',
                'producto_ubi_UsuarioCreacion' => 'SYSTEM',
                'producto_ubi_HostCreacion' => '127.0.0.1',
                'producto_ubi_FechaCreacion' => '2026-09-05 06:51:48.713',
                'producto_ubi_UsuarioModificacion' => 'ADMIN',
                'producto_ubi_HostModificacion' => '127.0.0.1',
                'producto_ubi_FechaModificacion' => '2026-09-05 20:42:27.587',
                'producto_ubi_UsuarioEliminacion' => null,
                'producto_ubi_HostEliminacion' => null,
                'producto_ubi_FechaEliminacion' => null,
            ],
            [
                'producto_ubi_id' => 'UBI-00002',
                'producto_ubi_descripcion' => 'Almacen 01',
                'producto_ubi_estado' => 'A',
                'producto_ubi_observacion' => 'Ubicacion para pruebas automatizadas',
                'producto_ubi_Eliminado' => 'N',
                'producto_ubi_UsuarioCreacion' => 'ADMIN',
                'producto_ubi_HostCreacion' => '127.0.0.1',
                'producto_ubi_FechaCreacion' => '2026-09-05 19:19:07.140',
                'producto_ubi_UsuarioModificacion' => 'ADMIN',
                'producto_ubi_HostModificacion' => '127.0.0.1',
                'producto_ubi_FechaModificacion' => '2026-09-05 20:42:06.570',
                'producto_ubi_UsuarioEliminacion' => null,
                'producto_ubi_HostEliminacion' => null,
                'producto_ubi_FechaEliminacion' => null,
            ],
            [
                'producto_ubi_id' => 'UBI-00003',
                'producto_ubi_descripcion' => 'Almacén Central - piso2',
                'producto_ubi_estado' => 'A',
                'producto_ubi_observacion' => 'Almacén Central - piso2',
                'producto_ubi_Eliminado' => 'N',
                'producto_ubi_UsuarioCreacion' => 'ADMIN',
                'producto_ubi_HostCreacion' => '127.0.0.1',
                'producto_ubi_FechaCreacion' => '2026-09-05 20:42:42.070',
                'producto_ubi_UsuarioModificacion' => null,
                'producto_ubi_HostModificacion' => null,
                'producto_ubi_FechaModificacion' => null,
                'producto_ubi_UsuarioEliminacion' => null,
                'producto_ubi_HostEliminacion' => null,
                'producto_ubi_FechaEliminacion' => null,
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('producto_ubi')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
