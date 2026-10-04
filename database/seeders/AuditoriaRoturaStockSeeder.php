<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditoriaRoturaStockSeeder extends Seeder
{
    /**
     * Auto-generated from database table `auditoria_roturas_stock`.
     * Total records: 0
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('auditoria_roturas_stock')->delete();

        // La tabla no contiene registros actualmente.
        // DB::statement(CHECK CONSTRAINT all);
    }
}
