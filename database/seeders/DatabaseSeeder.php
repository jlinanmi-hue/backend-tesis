<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Poblador maestro de la base de datos Comercial Valencia.
     * Ejecuta la carga completa en estricto orden topológico de dependencias.
     */
    public function run(): void
    {
        $this->command->info('1/3 - Desactivando restricciones de integridad referencial...');
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        $this->command->info('2/3 - Ejecutando Seeders con los datos de producción/tesis...');
        $this->call([
            EmpresaSeeder::class,
            CargoSeeder::class,
            RolUserSeeder::class,
            CategoriaProductoSeeder::class,
            UnidadesMedidaSeeder::class,
            ProductoUbiSeeder::class,
            CanalPedidoSeeder::class,
            ProveedorSeeder::class,
            ClienteSeeder::class,
            ConsultaRucSeeder::class,
            EmpleadoSeeder::class,
            ProductoSeeder::class,
            OrdenCompraSeeder::class,
            UsuarioSeeder::class,
            ProductoProveedorSeeder::class,
            DetalleProductoMedidaSeeder::class,
            DetalleOrdenCompraSeeder::class,
            PedidoSeeder::class,
            AjusteInventarioSeeder::class,
            DetallePedidoProductosSeeder::class,
            MovimientoProductoSeeder::class,
            NotificacionSeeder::class,
            AuditoriaRoturaStockSeeder::class,
            PedidoHistorialCorreccionSeeder::class,
            AiGenerationFeedbackSeeder::class,
            AiIntentLogSeeder::class,
            AiToolLogSeeder::class,
        ]);

        $this->command->info('3/3 - Reactivando y verificando restricciones de integridad referencial...');
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all"');

        $this->command->info('✅ ¡Todos los datos de la base de datos han sido cargados exitosamente!');
    }
}
