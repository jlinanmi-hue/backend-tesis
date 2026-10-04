<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpleadoSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Empleado`.
     * Total records: 7
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Empleado')->delete();

        $rows = [
            [
                'EmpleadoId' => 'EMP-00001',
                'EmpleadoNombres' => 'Jhonatan',
                'EmpleadoApellidos' => 'Liñan Miguel',
                'EmpleadoDni' => '71236394',
                'EmpleadoTelefono' => '903339531',
                'EmpleadoCorreo' => 'jlinanmi@ucvvirtual.edu.pe',
                'EmpleadoSexo' => 'M',
                'EmpleadoEstado' => 'A',
                'Empleado_cargoId' => 'CAR-00002',
                'EmpleadoEliminado' => 'N',
                'EmpleadoUsuarioCreacion' => 'SYSTEM',
                'EmpleadoHostCreacion' => '127.0.0.1',
                'EmpleadoFechaCreacion' => '2026-08-24 10:34:14.827',
                'EmpleadoUsuarioModificacion' => 'ADMIN',
                'EmpleadoHostModificacion' => '127.0.0.1',
                'EmpleadoFechaModificacion' => '2026-09-16 17:47:04.307',
                'EmpleadoUsuarioEliminacion' => null,
                'EmpleadoHostEliminacion' => null,
                'EmpleadoFechaEliminacion' => null,
                'EmpleadoFechaIngreso' => '2026-01-01',
            ],
            [
                'EmpleadoId' => 'EMP-00002',
                'EmpleadoNombres' => 'Roberto Carlos',
                'EmpleadoApellidos' => 'Morales Diaz',
                'EmpleadoDni' => '71234561',
                'EmpleadoTelefono' => '987654321',
                'EmpleadoCorreo' => 'roberto.morales@colegiobuonarroti.edu.pe',
                'EmpleadoSexo' => 'M',
                'EmpleadoEstado' => 'A',
                'Empleado_cargoId' => 'CAR-00003',
                'EmpleadoEliminado' => 'N',
                'EmpleadoUsuarioCreacion' => 'SYSTEM',
                'EmpleadoHostCreacion' => '127.0.0.1',
                'EmpleadoFechaCreacion' => '2026-08-24 10:34:15.067',
                'EmpleadoUsuarioModificacion' => null,
                'EmpleadoHostModificacion' => null,
                'EmpleadoFechaModificacion' => null,
                'EmpleadoUsuarioEliminacion' => null,
                'EmpleadoHostEliminacion' => null,
                'EmpleadoFechaEliminacion' => null,
                'EmpleadoFechaIngreso' => '2026-01-15',
            ],
            [
                'EmpleadoId' => 'EMP-00003',
                'EmpleadoNombres' => 'Ana Lucia',
                'EmpleadoApellidos' => 'Torres Vega',
                'EmpleadoDni' => '71234562',
                'EmpleadoTelefono' => '987654322',
                'EmpleadoCorreo' => 'ana.torres@colegiobuonarroti.edu.pe',
                'EmpleadoSexo' => 'F',
                'EmpleadoEstado' => 'A',
                'Empleado_cargoId' => 'CAR-00003',
                'EmpleadoEliminado' => 'N',
                'EmpleadoUsuarioCreacion' => 'SYSTEM',
                'EmpleadoHostCreacion' => '127.0.0.1',
                'EmpleadoFechaCreacion' => '2026-08-24 10:34:15.250',
                'EmpleadoUsuarioModificacion' => null,
                'EmpleadoHostModificacion' => null,
                'EmpleadoFechaModificacion' => null,
                'EmpleadoUsuarioEliminacion' => null,
                'EmpleadoHostEliminacion' => null,
                'EmpleadoFechaEliminacion' => null,
                'EmpleadoFechaIngreso' => '2026-02-01',
            ],
            [
                'EmpleadoId' => 'EMP-00004',
                'EmpleadoNombres' => 'Miguel Angel',
                'EmpleadoApellidos' => 'Castro Ruiz',
                'EmpleadoDni' => '71234563',
                'EmpleadoTelefono' => '987654323',
                'EmpleadoCorreo' => 'miguel.castro@colegiobuonarroti.edu.pe',
                'EmpleadoSexo' => 'M',
                'EmpleadoEstado' => 'A',
                'Empleado_cargoId' => 'CAR-00004',
                'EmpleadoEliminado' => 'N',
                'EmpleadoUsuarioCreacion' => 'SYSTEM',
                'EmpleadoHostCreacion' => '127.0.0.1',
                'EmpleadoFechaCreacion' => '2026-08-24 10:34:15.427',
                'EmpleadoUsuarioModificacion' => 'ADMIN',
                'EmpleadoHostModificacion' => '127.0.0.1',
                'EmpleadoFechaModificacion' => '2026-09-02 04:49:22.980',
                'EmpleadoUsuarioEliminacion' => null,
                'EmpleadoHostEliminacion' => null,
                'EmpleadoFechaEliminacion' => null,
                'EmpleadoFechaIngreso' => '2026-02-15',
            ],
            [
                'EmpleadoId' => 'EMP-00005',
                'EmpleadoNombres' => 'Sofia Elena',
                'EmpleadoApellidos' => 'Mendoza Paz',
                'EmpleadoDni' => '71234564',
                'EmpleadoTelefono' => '987654324',
                'EmpleadoCorreo' => 'sofia.mendoza@colegiobuonarroti.edu.pe',
                'EmpleadoSexo' => 'F',
                'EmpleadoEstado' => 'A',
                'Empleado_cargoId' => 'CAR-00004',
                'EmpleadoEliminado' => 'N',
                'EmpleadoUsuarioCreacion' => 'SYSTEM',
                'EmpleadoHostCreacion' => '127.0.0.1',
                'EmpleadoFechaCreacion' => '2026-08-24 10:34:15.603',
                'EmpleadoUsuarioModificacion' => 'ADMIN',
                'EmpleadoHostModificacion' => '127.0.0.1',
                'EmpleadoFechaModificacion' => '2026-09-02 04:49:22.407',
                'EmpleadoUsuarioEliminacion' => null,
                'EmpleadoHostEliminacion' => null,
                'EmpleadoFechaEliminacion' => null,
                'EmpleadoFechaIngreso' => '2026-03-01',
            ],
            [
                'EmpleadoId' => 'EMP-00006',
                'EmpleadoNombres' => 'Carmen Rosa',
                'EmpleadoApellidos' => 'Flores Benites',
                'EmpleadoDni' => '71234565',
                'EmpleadoTelefono' => '987654325',
                'EmpleadoCorreo' => 'carmen.flores@colegiobuonarroti.edu.pe',
                'EmpleadoSexo' => 'F',
                'EmpleadoEstado' => 'I',
                'Empleado_cargoId' => 'CAR-00005',
                'EmpleadoEliminado' => 'S',
                'EmpleadoUsuarioCreacion' => 'SYSTEM',
                'EmpleadoHostCreacion' => '127.0.0.1',
                'EmpleadoFechaCreacion' => '2026-08-24 10:34:15.780',
                'EmpleadoUsuarioModificacion' => 'ADMIN',
                'EmpleadoHostModificacion' => '127.0.0.1',
                'EmpleadoFechaModificacion' => '2026-09-02 04:49:21.770',
                'EmpleadoUsuarioEliminacion' => 'ADMIN',
                'EmpleadoHostEliminacion' => '127.0.0.1',
                'EmpleadoFechaEliminacion' => '2026-09-02 05:09:20.183',
                'EmpleadoFechaIngreso' => '2026-03-15',
            ],
            [
                'EmpleadoId' => 'EMP-00007',
                'EmpleadoNombres' => 'Carlos',
                'EmpleadoApellidos' => 'Liñan',
                'EmpleadoDni' => '41707293',
                'EmpleadoTelefono' => '992136527',
                'EmpleadoCorreo' => 'Carlos@hotmail.com',
                'EmpleadoSexo' => 'M',
                'EmpleadoEstado' => 'V',
                'Empleado_cargoId' => 'CAR-00003',
                'EmpleadoEliminado' => 'N',
                'EmpleadoUsuarioCreacion' => 'ADMIN',
                'EmpleadoHostCreacion' => '127.0.0.1',
                'EmpleadoFechaCreacion' => '2026-09-02 04:40:17.133',
                'EmpleadoUsuarioModificacion' => 'ADMIN',
                'EmpleadoHostModificacion' => '127.0.0.1',
                'EmpleadoFechaModificacion' => '2026-09-04 22:08:58.047',
                'EmpleadoUsuarioEliminacion' => null,
                'EmpleadoHostEliminacion' => null,
                'EmpleadoFechaEliminacion' => null,
                'EmpleadoFechaIngreso' => '2026-09-07',
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Empleado')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
