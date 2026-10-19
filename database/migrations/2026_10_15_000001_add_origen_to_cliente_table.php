<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('Cliente', 'ClienteOrigen')) {
            Schema::table('Cliente', function (Blueprint $table) {
                $table->string('ClienteOrigen', 20)->nullable()->default('Tienda');
            });
        }

        // Los clientes existentes fueron registrados de manera presencial (tienda).
        DB::table('Cliente')->whereNull('ClienteOrigen')->update(['ClienteOrigen' => 'Tienda']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('Cliente', 'ClienteOrigen')) {
            Schema::table('Cliente', function (Blueprint $table) {
                $table->dropColumn('ClienteOrigen');
            });
        }
    }
};
