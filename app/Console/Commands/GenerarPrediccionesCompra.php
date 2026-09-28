<?php

namespace App\Console\Commands;

use App\Services\PurchasePredictionService;
use Illuminate\Console\Command;

/**
 * GenerarPrediccionesCompra
 *
 * Comando Artisan para pre-calcular y cachear las predicciones semanales de compra.
 * Puede ejecutarse manualmente o desde el scheduler semanal.
 *
 * Uso:
 *   php artisan predicciones:generar
 *   php artisan predicciones:generar --semana=2026-W38
 *   php artisan predicciones:generar --forzar
 */
class GenerarPrediccionesCompra extends Command
{
    protected $signature = 'predicciones:generar
                            {--semana=  : Semana ISO a calcular (ej. 2026-W38). Default: semana actual.}
                            {--forzar   : Invalida la caché existente antes de calcular.}';

    protected $description = 'Pre-calcula y cachea las predicciones semanales de reposición de inventario de Comercial Valencia.';

    public function handle(PurchasePredictionService $service): int
    {
        $semana  = $this->option('semana') ?: null;
        $forzar  = (bool)$this->option('forzar');

        $semanaLabel = $semana ?? $service->semanaActual();
        $this->info("⏳ Generando predicciones de compra para la semana: {$semanaLabel}");

        if ($forzar) {
            $service->invalidarCacheSemanal($semana);
            $this->line('   → Caché invalidada (--forzar activo).');
        }

        $inicio = microtime(true);

        $datos = $service->generarPrediccionesSemanales($semana);

        $duracion = round(microtime(true) - $inicio, 2);

        $this->info("✅ Predicciones generadas correctamente.");
        $this->table(
            ['Semana', 'Productos', 'Críticos', 'Inversión Est. (S/)', 'Tiempo (s)'],
            [[
                $datos['semana'],
                $datos['total_productos'],
                $datos['criticos'],
                number_format($datos['total_estimado'], 2),
                $duracion,
            ]]
        );

        return Command::SUCCESS;
    }
}
