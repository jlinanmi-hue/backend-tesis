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
        if (!Schema::hasTable('ai_generation_feedback')) {
            Schema::create('ai_generation_feedback', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 100)->index();
                $table->string('message_id', 100)->nullable()->index();
                $table->unsignedBigInteger('tool_log_id')->nullable()->index();
                $table->string('tool_name', 100)->nullable()->index();
                $table->string('pedido_id', 50)->nullable()->index();
                $table->string('tipo_operacion', 60)->default('orden_general')->index();
                $table->string('rating', 20)->index(); // 'like' | 'dislike'
                $table->boolean('es_correcto')->default(true);
                $table->string('motivo_dislike', 100)->nullable();
                $table->text('comentario')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['created_at', 'rating']);
                $table->index(['session_id', 'message_id']);
            });
        }

        // Agregar columnas de feedback a ai_tool_logs si no existen
        if (Schema::hasTable('ai_tool_logs')) {
            Schema::table('ai_tool_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_tool_logs', 'feedback')) {
                    $table->string('feedback', 20)->nullable()->index();
                }
                if (!Schema::hasColumn('ai_tool_logs', 'motivo_dislike')) {
                    $table->string('motivo_dislike', 100)->nullable();
                }
                if (!Schema::hasColumn('ai_tool_logs', 'feedback_at')) {
                    $table->timestamp('feedback_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generation_feedback');

        if (Schema::hasTable('ai_tool_logs')) {
            Schema::table('ai_tool_logs', function (Blueprint $table) {
                if (Schema::hasColumn('ai_tool_logs', 'feedback')) {
                    $table->dropColumn('feedback');
                }
                if (Schema::hasColumn('ai_tool_logs', 'motivo_dislike')) {
                    $table->dropColumn('motivo_dislike');
                }
                if (Schema::hasColumn('ai_tool_logs', 'feedback_at')) {
                    $table->dropColumn('feedback_at');
                }
            });
        }
    }
};
