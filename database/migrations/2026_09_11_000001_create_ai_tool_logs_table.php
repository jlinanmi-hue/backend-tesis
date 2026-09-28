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
        Schema::create('ai_tool_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 100)->index();
            $table->string('tool_name', 100)->index();
            $table->string('pedido_id', 50)->nullable()->index();
            $table->text('args');
            $table->text('result')->nullable();
            $table->string('status', 30)->index(); // success | error | missing_fields | pending_confirmation
            $table->integer('duration_ms')->default(0);
            $table->integer('prompt_tokens')->default(0);
            $table->integer('candidates_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->index(['created_at', 'tool_name']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tool_logs');
    }
};
