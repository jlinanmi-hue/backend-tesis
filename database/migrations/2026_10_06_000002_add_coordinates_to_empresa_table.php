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
        if (Schema::hasTable('Empresa')) {
            Schema::table('Empresa', function (Blueprint $table) {
                if (!Schema::hasColumn('Empresa', 'EmpresaLatitud')) {
                    $table->decimal('EmpresaLatitud', 11, 8)->nullable();
                }
                if (!Schema::hasColumn('Empresa', 'EmpresaLongitud')) {
                    $table->decimal('EmpresaLongitud', 11, 8)->nullable();
                }
                if (!Schema::hasColumn('Empresa', 'EmpresaZonaReferencia')) {
                    $table->string('EmpresaZonaReferencia', 100)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('Empresa')) {
            Schema::table('Empresa', function (Blueprint $table) {
                $cols = ['EmpresaLatitud', 'EmpresaLongitud', 'EmpresaZonaReferencia'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('Empresa', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
