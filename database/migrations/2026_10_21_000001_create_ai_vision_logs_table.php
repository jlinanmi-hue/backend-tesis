<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_vision_logs')) {
            Schema::create('ai_vision_logs', function (Blueprint $table) {
                $table->id();
                $table->string('token_auditoria', 64)->unique();
                $table->string('pedido_id', 30)->nullable()->index();
                $table->string('usuario_id', 30)->nullable();
                $table->integer('tokens_consumidos')->default(258);
                $table->integer('tiempo_procesamiento_ms')->default(0);
                $table->json('items_detectados_ia');
                $table->json('items_guardados_final')->nullable();
                $table->integer('total_items_ia')->default(0);
                $table->integer('items_validados_sin_cambio')->default(0);
                $table->integer('items_modificados')->default(0);
                $table->integer('items_eliminados')->default(0);
                $table->integer('items_agregados')->default(0);
                $table->decimal('tasa_precision_pct', 5, 2)->default(100.00);
                $table->integer('tiempo_revision_humana_seg')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_vision_logs');
    }
};
