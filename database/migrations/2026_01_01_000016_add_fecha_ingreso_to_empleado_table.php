<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Empleado', function (Blueprint $table) {
            $table->date('EmpleadoFechaIngreso')->nullable()->after('EmpleadoSexo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Empleado', function (Blueprint $table) {
            $table->dropColumn('EmpleadoFechaIngreso');
        });
    }
};
