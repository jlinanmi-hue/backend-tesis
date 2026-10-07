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
        if (!Schema::hasTable('Zona_Delivery')) {
            Schema::create('Zona_Delivery', function (Blueprint $table) {
                $table->string('Zona_DeliveryId', 20)->primary(); // ZD-00001
                $table->string('Zona_DeliveryNombre', 100)->unique();
                $table->decimal('Zona_DeliveryTarifa', 10, 2)->default(0.00);
                $table->longText('Zona_DeliveryPoligonoGeoJSON'); // GeoJSON Polygon
                $table->char('Zona_DeliveryEstado', 1)->default('S'); // 'S' = Activa, 'N' = Inactiva

                // Auditoría estándar del proyecto Comercial Valencia
                $table->string('Zona_DeliveryUsuarioCreacion', 100)->default('SYSTEM');
                $table->string('Zona_DeliveryHostCreacion', 50)->default('127.0.0.1');
                $table->dateTime('Zona_DeliveryFechaCreacion')->useCurrent();
                $table->string('Zona_DeliveryUsuarioModificacion', 100)->nullable();
                $table->string('Zona_DeliveryHostModificacion', 50)->nullable();
                $table->dateTime('Zona_DeliveryFechaModificacion')->nullable();

                $table->index('Zona_DeliveryEstado', 'idx_zona_delivery_estado');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Zona_Delivery');
    }
};
