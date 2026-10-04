<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpresaSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Empresa`.
     * Total records: 1
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Empresa')->delete();

        $rows = [
            [
                'Id_Empresa' => '1',
                'EmpresaRuc' => '10181935451',
                'EmpresaRazonSocial' => 'VALENCIA VILLANUEVA BERNABE',
                'EmpresaNombreComercial' => 'COMERCIAL VALENCIA',
                'EmpresaDireccion' => 'Av. Principal America 178',
                'EmpresaDepartamento' => 'La Libertad',
                'EmpresaProvincia' => 'Trujillo',
                'EmpresaDistrito' => 'Trujillo',
                'EmpresaUbigeo' => '13001',
                'EmpresaTelefono' => '900292514',
                'EmpresaWhatsapp' => '51900292514',
                'EmpresaEmail' => 'ventas@comercialvalencia.pe',
                'EmpresaLogo' => null,
                'EmpresaMensajeTicket' => '¡Gracias por su preferencia en Comercial Valencia! Conserve su comprobante.',
                'EmpresaEstado' => 'A',
                'EmpresaUsuarioCreacion' => 'SISTEMA',
                'EmpresaHostCreacion' => '127.0.0.1',
                'EmpresaFechaCreacion' => '2026-09-09 16:15:20.663',
                'EmpresaUsuarioModificacion' => 'ADMIN',
                'EmpresaHostModificacion' => '127.0.0.1',
                'EmpresaFechaModificacion' => '2026-09-16 17:20:48.177',
            ],
        ];

        // Inserción en bloques con IDENTITY_INSERT habilitado
        foreach (array_chunk($rows, 25) as $chunk) {
            if (empty($chunk)) continue;
            $columns = array_keys($chunk[0]);
            $colList = '[' . implode('], [', $columns) . ']';

            $valuesSql = [];
            foreach ($chunk as $r) {
                $valParts = [];
                foreach ($r as $val) {
                    if ($val === null) {
                        $valParts[] = 'NULL';
                    } elseif (is_numeric($val) && !is_string($val)) {
                        $valParts[] = $val;
                    } elseif (is_bool($val)) {
                        $valParts[] = $val ? '1' : '0';
                    } else {
                        $escaped = str_replace("'", "''", (string)$val);
                        $valParts[] = "N'" . $escaped . "'";
                    }
                }
                $valuesSql[] = '(' . implode(', ', $valParts) . ')';
            }

            $sql = "SET IDENTITY_INSERT [Empresa] ON;\n"
                 . "INSERT INTO [Empresa] ($colList) VALUES\n"
                 . implode(",\n", $valuesSql) . ";\n"
                 . "SET IDENTITY_INSERT [Empresa] OFF;";

            DB::unprepared($sql);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
