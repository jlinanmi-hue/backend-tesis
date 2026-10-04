<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CargoSeeder extends Seeder
{
    /**
     * Auto-generated from database table `cargo`.
     * Total records: 5
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('cargo')->delete();

        $rows = [
            [
                'cargoId' => 'CAR-00001',
                'cargoDescripcion' => 'Administrador',
                'cargoEstado' => 'A',
                'cargoEliminado' => 'N',
                'cargoUsuarioCreacion' => 'SYSTEM',
                'cargoHostCreacion' => '127.0.0.1',
                'cargoFechaCreacion' => '2026-08-24 10:34:14.797',
                'cargoUsuarioModificacion' => null,
                'cargoHostModificacion' => null,
                'cargoFechaModificacion' => null,
                'cargoUsuarioEliminacion' => null,
                'cargoHostEliminacion' => null,
                'cargoFechaEliminacion' => null,
            ],
            [
                'cargoId' => 'CAR-00002',
                'cargoDescripcion' => 'Jefe de Tienda',
                'cargoEstado' => 'A',
                'cargoEliminado' => 'N',
                'cargoUsuarioCreacion' => 'SYSTEM',
                'cargoHostCreacion' => '127.0.0.1',
                'cargoFechaCreacion' => '2026-08-24 10:34:14.797',
                'cargoUsuarioModificacion' => null,
                'cargoHostModificacion' => null,
                'cargoFechaModificacion' => null,
                'cargoUsuarioEliminacion' => null,
                'cargoHostEliminacion' => null,
                'cargoFechaEliminacion' => null,
            ],
            [
                'cargoId' => 'CAR-00003',
                'cargoDescripcion' => 'Vendedor',
                'cargoEstado' => 'A',
                'cargoEliminado' => 'N',
                'cargoUsuarioCreacion' => 'SYSTEM',
                'cargoHostCreacion' => '127.0.0.1',
                'cargoFechaCreacion' => '2026-08-24 10:34:14.797',
                'cargoUsuarioModificacion' => null,
                'cargoHostModificacion' => null,
                'cargoFechaModificacion' => null,
                'cargoUsuarioEliminacion' => null,
                'cargoHostEliminacion' => null,
                'cargoFechaEliminacion' => null,
            ],
            [
                'cargoId' => 'CAR-00004',
                'cargoDescripcion' => 'Logístico',
                'cargoEstado' => 'A',
                'cargoEliminado' => 'N',
                'cargoUsuarioCreacion' => 'SYSTEM',
                'cargoHostCreacion' => '127.0.0.1',
                'cargoFechaCreacion' => '2026-08-24 10:34:14.797',
                'cargoUsuarioModificacion' => null,
                'cargoHostModificacion' => null,
                'cargoFechaModificacion' => null,
                'cargoUsuarioEliminacion' => null,
                'cargoHostEliminacion' => null,
                'cargoFechaEliminacion' => null,
            ],
            [
                'cargoId' => 'CAR-00005',
                'cargoDescripcion' => 'Recursos Humanos',
                'cargoEstado' => 'I',
                'cargoEliminado' => 'S',
                'cargoUsuarioCreacion' => 'SYSTEM',
                'cargoHostCreacion' => '127.0.0.1',
                'cargoFechaCreacion' => '2026-08-24 10:34:14.797',
                'cargoUsuarioModificacion' => null,
                'cargoHostModificacion' => null,
                'cargoFechaModificacion' => null,
                'cargoUsuarioEliminacion' => 'ADMIN',
                'cargoHostEliminacion' => '127.0.0.1',
                'cargoFechaEliminacion' => '2026-09-16 18:52:41.807',
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('cargo')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
