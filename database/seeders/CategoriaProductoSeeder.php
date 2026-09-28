<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaProductoSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Categoria_Producto`.
     * Total records: 8
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Categoria_Producto')->delete();

        $rows = [
            [
                'Categoria_ProductoId' => 'CAT-00001',
                'Categoria_ProductoDescripcion_categoria' => 'Lácteos y Derivados',
                'Categoria_ProductoEstado' => 'A',
                'Categoria_ProductoEliminado' => 'N',
                'Categoria_ProductoUsuarioCreacion' => 'SYSTEM',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-08-24 10:34:15.977',
                'Categoria_ProductoUsuarioModificacion' => null,
                'Categoria_ProductoHostModificacion' => null,
                'Categoria_ProductoFechaModificacion' => null,
                'Categoria_ProductoUsuarioEliminacion' => null,
                'Categoria_ProductoHostEliminacion' => null,
                'Categoria_ProductoFechaEliminacion' => null,
            ],
            [
                'Categoria_ProductoId' => 'CAT-00002',
                'Categoria_ProductoDescripcion_categoria' => 'Abarrotes y Granos',
                'Categoria_ProductoEstado' => 'A',
                'Categoria_ProductoEliminado' => 'N',
                'Categoria_ProductoUsuarioCreacion' => 'SYSTEM',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-08-24 10:34:15.977',
                'Categoria_ProductoUsuarioModificacion' => null,
                'Categoria_ProductoHostModificacion' => null,
                'Categoria_ProductoFechaModificacion' => null,
                'Categoria_ProductoUsuarioEliminacion' => null,
                'Categoria_ProductoHostEliminacion' => null,
                'Categoria_ProductoFechaEliminacion' => null,
            ],
            [
                'Categoria_ProductoId' => 'CAT-00003',
                'Categoria_ProductoDescripcion_categoria' => 'Bebidas y Refrescos',
                'Categoria_ProductoEstado' => 'A',
                'Categoria_ProductoEliminado' => 'N',
                'Categoria_ProductoUsuarioCreacion' => 'SYSTEM',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-08-24 10:34:15.977',
                'Categoria_ProductoUsuarioModificacion' => 'ADMIN',
                'Categoria_ProductoHostModificacion' => '127.0.0.1',
                'Categoria_ProductoFechaModificacion' => '2026-09-02 09:29:44.410',
                'Categoria_ProductoUsuarioEliminacion' => null,
                'Categoria_ProductoHostEliminacion' => null,
                'Categoria_ProductoFechaEliminacion' => null,
            ],
            [
                'Categoria_ProductoId' => 'CAT-00004',
                'Categoria_ProductoDescripcion_categoria' => 'Útiles y Papelería',
                'Categoria_ProductoEstado' => 'I',
                'Categoria_ProductoEliminado' => 'S',
                'Categoria_ProductoUsuarioCreacion' => 'SYSTEM',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-08-24 10:34:15.977',
                'Categoria_ProductoUsuarioModificacion' => null,
                'Categoria_ProductoHostModificacion' => null,
                'Categoria_ProductoFechaModificacion' => null,
                'Categoria_ProductoUsuarioEliminacion' => 'ADMIN',
                'Categoria_ProductoHostEliminacion' => '127.0.0.1',
                'Categoria_ProductoFechaEliminacion' => '2026-09-02 09:22:06.373',
            ],
            [
                'Categoria_ProductoId' => 'CAT-00005',
                'Categoria_ProductoDescripcion_categoria' => 'Limpieza y Desinfección',
                'Categoria_ProductoEstado' => 'A',
                'Categoria_ProductoEliminado' => 'N',
                'Categoria_ProductoUsuarioCreacion' => 'SYSTEM',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-08-24 10:34:15.977',
                'Categoria_ProductoUsuarioModificacion' => null,
                'Categoria_ProductoHostModificacion' => null,
                'Categoria_ProductoFechaModificacion' => null,
                'Categoria_ProductoUsuarioEliminacion' => null,
                'Categoria_ProductoHostEliminacion' => null,
                'Categoria_ProductoFechaEliminacion' => null,
            ],
            [
                'Categoria_ProductoId' => 'CAT-00006',
                'Categoria_ProductoDescripcion_categoria' => 'Galletas',
                'Categoria_ProductoEstado' => 'A',
                'Categoria_ProductoEliminado' => 'N',
                'Categoria_ProductoUsuarioCreacion' => 'ADMIN',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-09-07 23:07:51.910',
                'Categoria_ProductoUsuarioModificacion' => null,
                'Categoria_ProductoHostModificacion' => null,
                'Categoria_ProductoFechaModificacion' => null,
                'Categoria_ProductoUsuarioEliminacion' => null,
                'Categoria_ProductoHostEliminacion' => null,
                'Categoria_ProductoFechaEliminacion' => null,
            ],
            [
                'Categoria_ProductoId' => 'CAT-00007',
                'Categoria_ProductoDescripcion_categoria' => 'Cereales',
                'Categoria_ProductoEstado' => 'A',
                'Categoria_ProductoEliminado' => 'N',
                'Categoria_ProductoUsuarioCreacion' => 'ADMIN',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-09-07 23:07:55.837',
                'Categoria_ProductoUsuarioModificacion' => 'ADMIN',
                'Categoria_ProductoHostModificacion' => '127.0.0.1',
                'Categoria_ProductoFechaModificacion' => '2026-09-07 23:08:04.427',
                'Categoria_ProductoUsuarioEliminacion' => null,
                'Categoria_ProductoHostEliminacion' => null,
                'Categoria_ProductoFechaEliminacion' => null,
            ],
            [
                'Categoria_ProductoId' => 'CAT-00008',
                'Categoria_ProductoDescripcion_categoria' => 'Aceites y Grasas',
                'Categoria_ProductoEstado' => 'A',
                'Categoria_ProductoEliminado' => 'N',
                'Categoria_ProductoUsuarioCreacion' => 'ADMIN',
                'Categoria_ProductoHostCreacion' => '127.0.0.1',
                'Categoria_ProductoFechaCreacion' => '2026-09-23 22:14:54.493',
                'Categoria_ProductoUsuarioModificacion' => null,
                'Categoria_ProductoHostModificacion' => null,
                'Categoria_ProductoFechaModificacion' => null,
                'Categoria_ProductoUsuarioEliminacion' => null,
                'Categoria_ProductoHostEliminacion' => null,
                'Categoria_ProductoFechaEliminacion' => null,
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Categoria_Producto')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all"');
    }
}
