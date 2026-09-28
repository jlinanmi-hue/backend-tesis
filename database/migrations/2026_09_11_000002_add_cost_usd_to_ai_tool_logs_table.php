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
        if (!Schema::hasColumn('ai_tool_logs', 'cost_usd')) {
            Schema::table('ai_tool_logs', function (Blueprint $table) {
                $table->decimal('cost_usd', 10, 6)->default(0)->after('total_tokens');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ai_tool_logs', 'cost_usd')) {
            Schema::table('ai_tool_logs', function (Blueprint $table) {
                $table->dropColumn('cost_usd');
            });
        }
    }
};
