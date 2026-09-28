<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoProveedorSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Producto_Proveedor`.
     * Total records: 2
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Producto_Proveedor')->delete();

        $rows = [
            [
                'Producto_Proveedor_ProveedorId' => 'PRV-00001',
                'Producto_Proveedor_ProductoId' => 'PROD-00001',
                'Producto_ProveedorEliminado' => 'N',
                'Producto_ProveedorUsuarioCreacion' => 'ADMIN',
                'Producto_ProveedorHostCreacion' => '127.0.0.1',
                'Producto_ProveedorFechaCreacion' => '2026-09-07 21:03:03.140',
                'Producto_ProveedorUsuarioModificacion' => null,
                'Producto_ProveedorHostModificacion' => null,
                'Producto_ProveedorFechaModificacion' => null,
                'Producto_ProveedorUsuarioEliminacion' => null,
                'Producto_ProveedorHostEliminacion' => null,
                'Producto_ProveedorFechaEliminacion' => null,
            ],
            [
                'Producto_Proveedor_ProveedorId' => 'PRV-00005',
                'Producto_Proveedor_ProductoId' => 'PROD-00002',
                'Producto_ProveedorEliminado' => 'N',
                'Producto_ProveedorUsuarioCreacion' => 'ADMIN',
                'Producto_ProveedorHostCreacion' => '127.0.0.1',
                'Producto_ProveedorFechaCreacion' => '2026-09-09 02:57:38.377',
                'Producto_ProveedorUsuarioModificacion' => null,
                'Producto_ProveedorHostModificacion' => null,
                'Producto_ProveedorFechaModificacion' => null,
                'Producto_ProveedorUsuarioEliminacion' => null,
                'Producto_ProveedorHostEliminacion' => null,
                'Producto_ProveedorFechaEliminacion' => null,
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Producto_Proveedor')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all"');
    }
}
