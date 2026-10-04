<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolUserSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Rol_User`.
     * Total records: 5
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Rol_User')->delete();

        $rows = [
            [
                'Rol_UserId' => 'ROL-00001',
                'Rol_UserEmpleadocol' => 'ADMINISTRADOR',
                'Rol_UserRol_Usercol' => 'Administrador',
                'Rol_UserEliminado' => 'N',
                'Rol_UserUsuarioCreacion' => 'SYSTEM',
                'Rol_UserHostCreacion' => '127.0.0.1',
                'Rol_UserFechaCreacion' => '2026-08-24 10:34:14.817',
                'Rol_UserUsuarioModificacion' => null,
                'Rol_UserHostModificacion' => null,
                'Rol_UserFechaModificacion' => null,
                'Rol_UserUsuarioEliminacion' => null,
                'Rol_UserHostEliminacion' => null,
                'Rol_UserFechaEliminacion' => null,
            ],
            [
                'Rol_UserId' => 'ROL-00002',
                'Rol_UserEmpleadocol' => 'JEFE_TIENDA',
                'Rol_UserRol_Usercol' => 'Jefe de Tienda',
                'Rol_UserEliminado' => 'N',
                'Rol_UserUsuarioCreacion' => 'SYSTEM',
                'Rol_UserHostCreacion' => '127.0.0.1',
                'Rol_UserFechaCreacion' => '2026-08-24 10:34:14.817',
                'Rol_UserUsuarioModificacion' => null,
                'Rol_UserHostModificacion' => null,
                'Rol_UserFechaModificacion' => null,
                'Rol_UserUsuarioEliminacion' => null,
                'Rol_UserHostEliminacion' => null,
                'Rol_UserFechaEliminacion' => null,
            ],
            [
                'Rol_UserId' => 'ROL-00003',
                'Rol_UserEmpleadocol' => 'VENDEDOR',
                'Rol_UserRol_Usercol' => 'Vendedor',
                'Rol_UserEliminado' => 'N',
                'Rol_UserUsuarioCreacion' => 'SYSTEM',
                'Rol_UserHostCreacion' => '127.0.0.1',
                'Rol_UserFechaCreacion' => '2026-08-24 10:34:14.817',
                'Rol_UserUsuarioModificacion' => null,
                'Rol_UserHostModificacion' => null,
                'Rol_UserFechaModificacion' => null,
                'Rol_UserUsuarioEliminacion' => null,
                'Rol_UserHostEliminacion' => null,
                'Rol_UserFechaEliminacion' => null,
            ],
            [
                'Rol_UserId' => 'ROL-00004',
                'Rol_UserEmpleadocol' => 'LOGISTICO',
                'Rol_UserRol_Usercol' => 'Logístico',
                'Rol_UserEliminado' => 'N',
                'Rol_UserUsuarioCreacion' => 'SYSTEM',
                'Rol_UserHostCreacion' => '127.0.0.1',
                'Rol_UserFechaCreacion' => '2026-08-24 10:34:14.817',
                'Rol_UserUsuarioModificacion' => null,
                'Rol_UserHostModificacion' => null,
                'Rol_UserFechaModificacion' => null,
                'Rol_UserUsuarioEliminacion' => null,
                'Rol_UserHostEliminacion' => null,
                'Rol_UserFechaEliminacion' => null,
            ],
            [
                'Rol_UserId' => 'ROL-00005',
                'Rol_UserEmpleadocol' => 'RECURSOS_HUMANOS',
                'Rol_UserRol_Usercol' => 'Recursos Humanos',
                'Rol_UserEliminado' => 'S',
                'Rol_UserUsuarioCreacion' => 'SYSTEM',
                'Rol_UserHostCreacion' => '127.0.0.1',
                'Rol_UserFechaCreacion' => '2026-08-24 10:34:14.817',
                'Rol_UserUsuarioModificacion' => null,
                'Rol_UserHostModificacion' => null,
                'Rol_UserFechaModificacion' => null,
                'Rol_UserUsuarioEliminacion' => 'ADMIN',
                'Rol_UserHostEliminacion' => '127.0.0.1',
                'Rol_UserFechaEliminacion' => '2026-09-02 10:13:11.927',
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Rol_User')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
