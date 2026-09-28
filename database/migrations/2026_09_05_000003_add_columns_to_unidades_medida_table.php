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
        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->string('unidades_medidaAbreviatura', 10)->nullable()->after('unidades_medidaDescripcionUnidades');
            $table->boolean('unidades_medidaEsBase')->default(false)->after('unidades_medidaAbreviatura');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->dropColumn(['unidades_medidaAbreviatura', 'unidades_medidaEsBase']);
        });
    }
};
