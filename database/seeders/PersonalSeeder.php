<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PersonalSeeder extends Seeder
{
    /**
     * Poblador combinado de Empleados y Usuarios de Comercial Valencia.
     */
    public function run(): void
    {
        $this->call([
            EmpleadoSeeder::class,
            UsuarioSeeder::class,
        ]);
    }
}
