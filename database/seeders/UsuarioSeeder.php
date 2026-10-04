<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsuarioSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Usuario`.
     * Total records: 7
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Usuario')->delete();

        $rows = [
            [
                'UsuarioId' => 'USR-00001',
                'UsuarioUserName' => 'jlinanmi@ucvvirtual.edu.pe',
                'UsuarioPassword' => '$2y$12$HYN0E8ORBvm2.xRVEN2mQuyBJlU6brG2bHTj7Ff3JxWh5.yCeG4oy',
                'Usuario_Rol_UserId' => 'ROL-00001',
                'Usuario_EmpleadoId' => 'EMP-00001',
                'UsuarioEliminado' => 'N',
                'UsuarioUsuarioCreacion' => 'SYSTEM',
                'UsuarioHostCreacion' => '127.0.0.1',
                'UsuarioFechaCreacion' => '2026-08-24 10:34:15.067',
                'UsuarioUsuarioModificacion' => 'ADMIN',
                'UsuarioHostModificacion' => '127.0.0.1',
                'UsuarioFechaModificacion' => '2026-09-16 17:47:04.313',
                'UsuarioUsuarioEliminacion' => null,
                'UsuarioHostEliminacion' => null,
                'UsuarioFechaEliminacion' => null,
            ],
            [
                'UsuarioId' => 'USR-00002',
                'UsuarioUserName' => 'roberto.morales',
                'UsuarioPassword' => '$2y$12$CtXc5sjvpBQcdcqgS4sDbe.yDxoj94j3Rd0.SLBNFP4QESJcoOwSO',
                'Usuario_Rol_UserId' => 'ROL-00003',
                'Usuario_EmpleadoId' => 'EMP-00002',
                'UsuarioEliminado' => 'N',
                'UsuarioUsuarioCreacion' => 'SYSTEM',
                'UsuarioHostCreacion' => '127.0.0.1',
                'UsuarioFechaCreacion' => '2026-08-24 10:34:15.250',
                'UsuarioUsuarioModificacion' => null,
                'UsuarioHostModificacion' => null,
                'UsuarioFechaModificacion' => null,
                'UsuarioUsuarioEliminacion' => null,
                'UsuarioHostEliminacion' => null,
                'UsuarioFechaEliminacion' => null,
            ],
            [
                'UsuarioId' => 'USR-00003',
                'UsuarioUserName' => 'ana.torres',
                'UsuarioPassword' => '$2y$12$nIl/mfnPugE1mVL0HuKKsuEPmyP0P0ONfZUGclqeWTr2Pu.HKPgnK',
                'Usuario_Rol_UserId' => 'ROL-00003',
                'Usuario_EmpleadoId' => 'EMP-00003',
                'UsuarioEliminado' => 'N',
                'UsuarioUsuarioCreacion' => 'SYSTEM',
                'UsuarioHostCreacion' => '127.0.0.1',
                'UsuarioFechaCreacion' => '2026-08-24 10:34:15.427',
                'UsuarioUsuarioModificacion' => null,
                'UsuarioHostModificacion' => null,
                'UsuarioFechaModificacion' => null,
                'UsuarioUsuarioEliminacion' => null,
                'UsuarioHostEliminacion' => null,
                'UsuarioFechaEliminacion' => null,
            ],
            [
                'UsuarioId' => 'USR-00004',
                'UsuarioUserName' => 'miguel.castro',
                'UsuarioPassword' => '$2y$12$T7G8rSdc8T0huzjOtRF2gOno/8oSKrleoBtjekBqTj/DTydEnil7W',
                'Usuario_Rol_UserId' => 'ROL-00004',
                'Usuario_EmpleadoId' => 'EMP-00004',
                'UsuarioEliminado' => 'N',
                'UsuarioUsuarioCreacion' => 'SYSTEM',
                'UsuarioHostCreacion' => '127.0.0.1',
                'UsuarioFechaCreacion' => '2026-08-24 10:34:15.603',
                'UsuarioUsuarioModificacion' => 'ADMIN',
                'UsuarioHostModificacion' => '127.0.0.1',
                'UsuarioFechaModificacion' => '2026-09-02 04:49:22.983',
                'UsuarioUsuarioEliminacion' => null,
                'UsuarioHostEliminacion' => null,
                'UsuarioFechaEliminacion' => null,
            ],
            [
                'UsuarioId' => 'USR-00005',
                'UsuarioUserName' => 'sofia.mendoza',
                'UsuarioPassword' => '$2y$12$15Aap3QN08Kf6qM4ulS2R.4pPnpZBmgfpNLPE4ff5t//Gg8pOosvO',
                'Usuario_Rol_UserId' => 'ROL-00004',
                'Usuario_EmpleadoId' => 'EMP-00005',
                'UsuarioEliminado' => 'N',
                'UsuarioUsuarioCreacion' => 'SYSTEM',
                'UsuarioHostCreacion' => '127.0.0.1',
                'UsuarioFechaCreacion' => '2026-08-24 10:34:15.780',
                'UsuarioUsuarioModificacion' => 'ADMIN',
                'UsuarioHostModificacion' => '127.0.0.1',
                'UsuarioFechaModificacion' => '2026-09-02 04:49:22.413',
                'UsuarioUsuarioEliminacion' => null,
                'UsuarioHostEliminacion' => null,
                'UsuarioFechaEliminacion' => null,
            ],
            [
                'UsuarioId' => 'USR-00006',
                'UsuarioUserName' => 'carmen.flores@colegiobuonarroti.edu.pe',
                'UsuarioPassword' => '$2y$12$E8tT3PSaZfIq95oJ0biuSuDXO5J0eRmdw.UxDg8P25rn2Q4zHbUjy',
                'Usuario_Rol_UserId' => 'ROL-00005',
                'Usuario_EmpleadoId' => 'EMP-00006',
                'UsuarioEliminado' => 'S',
                'UsuarioUsuarioCreacion' => 'SYSTEM',
                'UsuarioHostCreacion' => '127.0.0.1',
                'UsuarioFechaCreacion' => '2026-08-24 10:34:15.957',
                'UsuarioUsuarioModificacion' => 'ADMIN',
                'UsuarioHostModificacion' => '127.0.0.1',
                'UsuarioFechaModificacion' => '2026-09-02 04:49:21.773',
                'UsuarioUsuarioEliminacion' => 'ADMIN',
                'UsuarioHostEliminacion' => '127.0.0.1',
                'UsuarioFechaEliminacion' => '2026-09-02 05:09:20.190',
            ],
            [
                'UsuarioId' => 'USR-00007',
                'UsuarioUserName' => 'Carlos@hotmail.com',
                'UsuarioPassword' => '$2y$12$7kefz5HzwQ4lw221RK2Swu3SyvyvNVM3YTd.wc3t5Z4KsbX5wws1G',
                'Usuario_Rol_UserId' => 'ROL-00001',
                'Usuario_EmpleadoId' => 'EMP-00007',
                'UsuarioEliminado' => 'N',
                'UsuarioUsuarioCreacion' => 'ADMIN',
                'UsuarioHostCreacion' => '127.0.0.1',
                'UsuarioFechaCreacion' => '2026-09-02 04:40:17.140',
                'UsuarioUsuarioModificacion' => 'ADMIN',
                'UsuarioHostModificacion' => '127.0.0.1',
                'UsuarioFechaModificacion' => '2026-09-02 04:55:00.397',
                'UsuarioUsuarioEliminacion' => null,
                'UsuarioHostEliminacion' => null,
                'UsuarioFechaEliminacion' => null,
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Usuario')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
