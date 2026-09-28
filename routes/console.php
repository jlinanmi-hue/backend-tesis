<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:check-timeout {--hours= : Horas de timeout para expirar ordenes}', function (\App\Services\PedidoService $pedidoService) {
    $hours = $this->option('hours') ? (int) $this->option('hours') : null;
    $resultado = $pedidoService->cancelarPedidosPorTimeout($hours);
    $this->info("Verificación completada: {$resultado['total_cancelados']} órdenes expiradas canceladas (> {$resultado['horas_limite']} horas).");
})->purpose('Verificar y cancelar automáticamente órdenes de cliente que excedieron el límite de horas');

Artisan::command('orders:notify-expiring {--hours= : Horas de timeout} {--window=2 : Ventana de alerta en horas}', function (\App\Services\PedidoService $pedidoService) {
    $hours = $this->option('hours') ? (int) $this->option('hours') : null;
    $window = (int) $this->option('window');
    $resultado = $pedidoService->notificarPedidosPorExpirar($hours, $window);
    $this->info("Notificación completada: {$resultado['total_notificados']} órdenes próximas a expirar en menos de {$resultado['ventana_alerta_horas']} horas.");
})->purpose('Notificar órdenes de cliente próximas a expirar por timeout');

Artisan::command('ai:warm-cache', function (\App\Services\GeminiToolsService $toolsService) {
    $this->info("🔥 Pre-calentando caché de consultas frecuentes de Valencia AI...");

    $commonQueries = [
        [
            'query' => 'ventas por periodo ultimos 30 dias',
            'tool'  => 'obtener_ventas_por_periodo',
            'args'  => ['dias' => 30],
        ],
        [
            'query' => 'top 10 productos mas vendidos',
            'tool'  => 'obtener_top_productos',
            'args'  => ['limite' => 10, 'dias' => 30],
        ],
        [
            'query' => 'productos con bajo stock o agotados',
            'tool'  => 'listar_productos_bajo_stock',
            'args'  => ['limite' => 20],
        ],
        [
            'query' => 'grafico de barras de pedidos por estado',
            'tool'  => 'generar_grafico_dashboard',
            'args'  => ['tipo_grafico' => 'bar', 'metrica' => 'pedidos_por_estado'],
        ],
        [
            'query' => 'resumen general de metricas kpi',
            'tool'  => 'consultar_kpi',
            'args'  => ['indicador' => 'PODE'],
        ],
        [
            'query' => 'reporte consolidado de inventario valorizado',
            'tool'  => 'obtener_reporte_inventario',
            'args'  => [],
        ],
    ];

    $epoch = (int) \Illuminate\Support\Facades\Cache::get('ai_cache_epoch', 1);
    $warmed = 0;

    foreach ($commonQueries as $item) {
        $q = mb_strtolower(trim($item['query']));
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ];
        $norm = strtr($q, $replacements);
        $norm = preg_replace('/[?¿!¡.,;:_"\'-]/u', ' ', $norm);
        $norm = preg_replace('/\s+/u', ' ', trim($norm));

        $cacheKey = "ai_query_cache:v{$epoch}:" . md5($norm);

        $this->line(" - Pre-calentando: '{$item['query']}' ({$item['tool']})...");
        $t0 = microtime(true);
        $result = $toolsService->dispatch($item['tool'], $item['args']);
        $ms = round((microtime(true) - $t0) * 1000, 1);

        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'success' => true,
            'data'    => [
                'reply'            => $result['message'] ?? 'Datos consolidados obtenidos del sistema.',
                'tool_used'        => $item['tool'],
                'tool_data'        => $result['data'] ?? null,
                'tool_success'     => true,
                'tool_status'      => 'executed',
                'ui_action'        => $result['ui_action'] ?? null,
                'chips'            => $result['chips'] ?? null,
                'campos_faltantes' => null,
                'tools_executed'   => [
                    [
                        'tool'        => $item['tool'],
                        'args'        => $item['args'],
                        'success'     => true,
                        'status'      => 'executed',
                        'duration_ms' => $ms,
                    ],
                ],
                'session_id'       => (string) \Illuminate\Support\Str::uuid(),
                'total_duration_ms'=> $ms,
                'model'            => 'cache-prewarmed',
            ],
            'message' => 'Respuesta pre-cargada en caché.',
        ], now()->addMinutes(10));

        $warmed++;
        $this->info("   ✔ OK ({$ms}ms) -> Clave: {$cacheKey}");
    }

    $this->info("\n✨ Pre-calentamiento completado: {$warmed} consultas cacheadas exitosamente (Epoch {$epoch}).");
})->purpose('Pre-calentar el caché de consultas frecuentes de BI y Dashboard para Valencia AI');

// Schedule: Pre-calcular predicciones de compra cada lunes a las 06:00 AM (inicio de semana)
use Illuminate\Support\Facades\Schedule;
Schedule::command('predicciones:generar')->weeklyOn(Carbon\Carbon::MONDAY, '06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/predicciones_compra.log'));
