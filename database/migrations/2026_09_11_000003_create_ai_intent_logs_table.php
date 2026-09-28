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
        Schema::create('ai_intent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('intent', 50)->index(); // 'BI', 'pedidos', 'stock', 'clientes', 'proveedores', 'alertas', 'general'
            $table->text('query');
            $table->string('complexity', 20)->default('media'); // 'simple', 'media', 'compleja'
            $table->string('model_used', 100)->nullable()->index();
            $table->string('tool_used', 100)->nullable()->index();
            $table->integer('tools_sent')->default(0);
            $table->integer('tokens_used')->default(0);
            $table->integer('gemini_ms')->default(0);
            $table->integer('tool_ms')->default(0);
            $table->integer('total_ms')->default(0);
            $table->boolean('cache_hit')->default(false)->index();
            $table->boolean('success')->default(true)->index();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->index(['created_at', 'intent']);
            $table->index(['intent', 'success']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_intent_logs');
    }
};