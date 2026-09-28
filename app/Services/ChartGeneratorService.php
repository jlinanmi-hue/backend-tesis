<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChartGeneratorService
 *
 * Genera estructuras de datos para gráficos y tablas dinámicos solicitados
 * por el usuario vía lenguaje natural al chatbot Valencia AI.
 *
 * Características principales:
 *  - Compatibilidad SQL Server (CAST AS DATE, DATEDIFF, agregaciones seguras)
 *  - Límite de 15 elementos por gráfico (agrupación «Otros» para excedentes)
 *  - Caché por combinación (métrica + filtros) con TTL = 60s
 *  - Estado vacío estructurado (render_empty_state)
 *  - Resumen ejecutivo textual en cada respuesta (política: gráfico + texto)
 *  - Chips sugeridos de métricas relacionadas
 */
class ChartGeneratorService
{
    private const MAX_CHART_ITEMS = 15;

    // ═══════════════════════════════════════════════════════
    // 1. PUNTO DE ENTRADA PRINCIPAL
    // ═══════════════════════════════════════════════════════

    /**
     * Genera el ui_action completo para renderizar un gráfico en el chat.
     */
    public function generar(array $args): array
    {
        $tipo       = $args['tipo_grafico'] ?? 'bar';
        $metrica    = $args['metrica'] ?? 'pedidos_por_estado';
        $filtros    = $args['filtros'] ?? [];
        $agrupacion = $args['agrupacion'] ?? null;

        // Si el usuario solicitó evolución temporal o agrupó por fecha, preferir ventas_por_dia
        $dias = $args['dias'] ?? $filtros['dias'] ?? null;
        if ($metrica === 'ventas_del_dia' && ($agrupacion === 'fecha' || ($dias !== null && (int)$dias > 1))) {
            $metrica = 'ventas_por_dia';
        }

        // Resolución inteligente de fechas y período
        if ($dias !== null && (int)$dias > 0) {
            $filtros['fecha_desde'] = now()->subDays(max(1, (int)$dias - 1))->format('Y-m-d');
            $filtros['fecha_hasta'] = now()->format('Y-m-d');
        } else {
            if (empty($filtros['fecha_hasta'])) {
                $filtros['fecha_hasta'] = now()->format('Y-m-d');
            }
            if (empty($filtros['fecha_desde'])) {
                // Métricas que representan evolución temporal o rankings requieren rango histórico por defecto (7 días)
                if (in_array($metrica, ['ventas_por_dia', 'top_productos', 'top_clientes', 'pedidos_por_estado', 'pedidos_por_canal', 'errores_por_tipo'])) {
                    $filtros['fecha_desde'] = now()->subDays(6)->format('Y-m-d');
                } else {
                    $filtros['fecha_desde'] = now()->format('Y-m-d');
                }
            }
        }

        $cacheKey = "chart:{$metrica}:" . md5(json_encode($filtros) . ($agrupacion ?? '') . $tipo);

        try {
            $data = Cache::remember($cacheKey, 60, function () use ($metrica, $filtros, $agrupacion) {
                return match ($metrica) {
                    'pedidos_por_estado'        => $this->pedidosPorEstado($filtros),
                    'pedidos_por_canal'         => $this->pedidosPorCanal($filtros),
                    'ventas_del_dia'            => $this->ventasDelDia($filtros, $agrupacion),
                    'ventas_por_dia'            => $this->ventasPorDia($filtros),
                    'top_productos'             => $this->topProductos($filtros),
                    'top_clientes'              => $this->topClientes($filtros),
                    'stock_critico'             => $this->stockCritico($filtros),
                    'errores_por_tipo'          => $this->erroresPorTipo($filtros),
                    'roturas_por_categoria'     => $this->roturasPorCategoria($filtros),
                    'tiempo_busqueda_promedio'  => $this->tiempoBusquedaPromedio($filtros),
                    default => throw new \Exception("Métrica analítica no soportada: {$metrica}"),
                };
            });

            // Si no hay datos numéricos o labels vacíos, devolver estado vacío amable
            $seriesArray = (array) ($data['series'] ?? []);
            $totalValores = array_sum(array_map(fn($v) => is_numeric($v) ? (float)$v : 0, $seriesArray));

            if (empty($data['labels']) || $totalValores == 0) {
                return $this->emptyState($metrica, $filtros);
            }

            $chips = $this->getSuggestedChips($metrica);

            $metadata = $data['metadata'] ?? $this->getMetricaMetadata($metrica);

            return [
                'success'   => true,
                'data'      => $data,
                'ui_action' => [
                    'type'  => 'render_chart',
                    'chart' => [
                        'type'     => $tipo,
                        'title'    => $data['title'] ?? $this->getTitulo($metrica, $filtros),
                        'labels'   => $data['labels'],
                        'series'   => $data['series'],
                        'total'    => $data['total'] ?? null,
                        'metadata' => $metadata,
                        'formato'  => $metadata['formato'] ?? 'entero',
                        'unidad'   => $metadata['unidad'] ?? '',
                    ],
                ],
                'message' => $data['message'] ?? 'Aquí tienes el gráfico solicitado.',
                'chips'   => $chips,
            ];
        } catch (\Exception $e) {
            Log::error('ChartGeneratorService::generar error', ['metric' => $metrica, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'data'    => null,
                'message' => "No fue posible generar el gráfico: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Consulta un KPI oficial de la investigación (PODE, PEOR, PRS, TBPP, TSA).
     */
    public function consultarKpi(array $args): array
    {
        $indicador = strtoupper($args['indicador'] ?? 'PODE');
        $periodo   = $args['periodo'] ?? now()->format('Y-m');

        /** @var DashboardService $dashboardService */
        $dashboardService = app(DashboardService::class);

        try {
            $data = match ($indicador) {
                'PODE'  => $dashboardService->getPODE($periodo),
                'PEOR'  => $dashboardService->getPEOR($periodo),
                'PRS'   => $dashboardService->getPRS($periodo),
                'TBPP'  => $dashboardService->getTBPP($periodo),
                'TSA'   => $this->calcularTSA($periodo),
                default => throw new \Exception("Indicador no reconocido: {$indicador}"),
            };

            $cumpleIcono = $data['cumple'] ? '✅ Cumple' : '❌ No cumple';
            $metaSufijo  = match ($indicador) {
                'PODE'  => '≥ ' . $data['meta'] . '%',
                'PEOR'  => '≤ ' . $data['meta'] . '%',
                'PRS'   => '≤ ' . $data['meta'] . '%',
                'TBPP'  => '≤ ' . $data['meta'] . 's',
                default => '' . $data['meta'],
            };

            $vars = $data['variables'] ?? [];
            $varsTexto = collect($vars)->map(fn($v, $k) => "• **{$k}**: {$v}")->implode("\n");
            $delta = $data['anterior']['delta_pct'] ?? 0;
            $deltaIcono = $delta >= 0 ? '↑' : '↓';

            $message = "📊 **{$indicador} — {$data['nombre']}**\n";
            $message .= "• **Período**: {$periodo}\n";
            $message .= "• **Resultado**: **{$data['resultado']}** (Meta: {$metaSufijo} | {$cumpleIcono})\n";
            $message .= "• **Fórmula**: `{$data['formula']}`\n\n";
            $message .= "**Variables del cálculo:**\n{$varsTexto}\n";

            if (isset($data['anterior']) && !empty($data['anterior']['periodo'])) {
                $message .= "\n**Comparativa con período anterior ({$data['anterior']['periodo']}):** {$data['anterior']['resultado']} {$deltaIcono} ({$delta}%)\n";
            }

            return [
                'success' => true,
                'data'    => $data,
                'message' => $message,
                'chips'   => [
                    ['label' => '📈 Ver subgráficos de ' . $indicador, 'query' => "¿Cuáles son los detalles de {$indicador} para {$periodo}?"],
                    ['label' => '📋 Reporte mensual', 'query' => "Genera un reporte mensual consolidado"],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data'    => null,
                'message' => "No pude consultar el indicador {$indicador}: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Genera un reporte ejecutivo completo del período.
     */
    public function generarReportePeriodo(array $args): array
    {
        $tipo = $args['tipo_reporte'] ?? 'diario';
        $hoy  = now()->format('Y-m-d');

        $desde = $args['fecha_desde'] ?? match ($tipo) {
            'semanal' => now()->startOfWeek()->format('Y-m-d'),
            'mensual' => now()->startOfMonth()->format('Y-m-d'),
            default   => $hoy,
        };
        $hasta = $args['fecha_hasta'] ?? $hoy;

        $filtros = ['fecha_desde' => $desde, 'fecha_hasta' => $hasta];

        $pedidos  = $this->pedidosPorEstado($filtros);
        $ventas   = $this->ventasDelDia($filtros, 'canal');
        $topProds = $this->topProductos($filtros);

        $comp  = $pedidos['labels_data']['Completados'] ?? 0;
        $pend  = $pedidos['labels_data']['Pendientes']  ?? 0;
        $canc  = $pedidos['labels_data']['Cancelados']  ?? 0;
        $total = $comp + $pend + $canc;
        $totalVentas = $ventas['total_raw'] ?? 0;

        $message = "📋 **Reporte Ejecutivo {$tipo} | {$desde} al {$hasta}**\n\n";
        $message .= "🛒 **Pedidos**: {$total} en total\n";
        $message .= "• Completados: **{$comp}**\n";
        $message .= "• Pendientes: **{$pend}**\n";
        $message .= "• Cancelados: **{$canc}**\n\n";
        $message .= "💰 **Órdenes Facturadas**: **S/ " . number_format($totalVentas, 2) . "**\n";

        $uiAction = null;
        if (!empty($args['incluir_graficos'])) {
            $uiAction = [
                'type'  => 'render_chart',
                'chart' => [
                    'type'   => 'bar',
                    'title'  => 'Pedidos por Estado',
                    'labels' => $pedidos['labels'],
                    'series' => $pedidos['series'],
                    'total'  => "{$total} pedidos",
                ],
            ];
        }

        return [
            'success'   => true,
            'data'      => compact('pedidos', 'ventas', 'topProds'),
            'message'   => $message,
            'ui_action' => $uiAction,
            'chips'     => [
                ['label' => '📊 Órdenes por canal', 'query' => 'Gráfico de órdenes por canal del período'],
                ['label' => '🏆 Top productos', 'query' => 'Top 5 productos más solicitados'],
            ],
        ];
    }

    /**
     * Compara dos períodos temporales para una métrica dada.
     */
    public function compararPeriodos(array $args): array
    {
        $metrica         = $args['metrica'] ?? 'ventas';
        $periodoActual   = $args['periodo_actual']  ?? now()->format('Y-m');
        $periodoAnterior = $args['periodo_anterior'] ?? now()->subMonth()->format('Y-m');

        try {
            [$actDesde, $actHasta] = $this->resolverPeriodo($periodoActual);
            [$antDesde, $antHasta] = $this->resolverPeriodo($periodoAnterior);

            $dataActual   = $this->calcularMetricaPeriodo($metrica, $actDesde, $actHasta);
            $dataAnterior = $this->calcularMetricaPeriodo($metrica, $antDesde, $antHasta);

            $valAct = $dataActual['valor'] ?? 0;
            $valAnt = $dataAnterior['valor'] ?? 0;
            $delta  = $valAnt > 0 ? round((($valAct - $valAnt) / $valAnt) * 100, 1) : 0;
            $deltaIcono = $delta >= 0 ? '↑' : '↓';
            $etiqueta   = $dataActual['etiqueta'] ?? $metrica;

            $message = "📊 **Comparativa de {$etiqueta}: {$periodoAnterior} vs {$periodoActual}**\n";
            $message .= "• Período actual ({$periodoActual}): **{$dataActual['formatted']}**\n";
            $message .= "• Período anterior ({$periodoAnterior}): **{$dataAnterior['formatted']}**\n";
            $message .= "• Variación: **" . ($delta >= 0 ? '+' : '') . "{$delta}%** {$deltaIcono}";

            return [
                'success'   => true,
                'data'      => compact('dataActual', 'dataAnterior', 'delta'),
                'ui_action' => [
                    'type'  => 'render_chart',
                    'chart' => [
                        'type'          => 'bar',
                        'title'         => "Comparativa de {$etiqueta}",
                        'labels'        => [$periodoAnterior, $periodoActual],
                        'series'        => [$valAnt, $valAct],
                        'is_comparison' => true,
                        'delta_pct'     => $delta,
                    ],
                ],
                'message' => $message,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data'    => null,
                'message' => "Error al comparar períodos: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Genera una tabla estructurada para mostrar en el chat.
     */
    public function renderizarTabla(array $args): array
    {
        $entidad  = $args['entidad'] ?? 'pedidos';
        $filtros  = $args['filtros'] ?? [];
        $limite   = min((int)($args['limite'] ?? 10), 50);

        try {
            [$columns, $rows, $title] = match ($entidad) {
                'pedidos'     => $this->tablaPedidos($filtros, $limite),
                'productos'   => $this->tablaProductos($filtros, $limite),
                'clientes'    => $this->tablaClientes($filtros, $limite),
                'movimientos' => $this->tablaMovimientos($filtros, $limite),
                default       => throw new \Exception("Entidad no soportada para tabla: {$entidad}"),
            };

            if (empty($rows)) {
                return [
                    'success'   => true,
                    'data'      => null,
                    'ui_action' => [
                        'type'    => 'render_empty_state',
                        'message' => "No se encontraron registros de {$entidad} con los filtros especificados.",
                        'icon'    => '📋',
                    ],
                    'message' => "No se encontraron registros de {$entidad}.",
                ];
            }

            return [
                'success'   => true,
                'data'      => ['columns' => $columns, 'rows' => $rows],
                'ui_action' => [
                    'type'  => 'render_table',
                    'table' => [
                        'title'   => $title,
                        'columns' => $columns,
                        'rows'    => $rows,
                    ],
                ],
                'message' => "Se encontraron **" . count($rows) . "** registros de {$title}.",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data'    => null,
                'message' => "Error generando la tabla: {$e->getMessage()}",
            ];
        }
    }

    // ═══════════════════════════════════════════════════════
    // 2. MÉTRICAS ANALÍTICAS INDIVIDUALES
    // ═══════════════════════════════════════════════════════

    private function pedidosPorEstado(array $f): array
    {
        $q = $this->baseQuery($f);
        $r = (clone $q)->selectRaw("
            SUM(CASE WHEN PedidoEstado_pedido = 'C' THEN 1 ELSE 0 END) as comp,
            SUM(CASE WHEN PedidoEstado_pedido = 'P' THEN 1 ELSE 0 END) as pend,
            SUM(CASE WHEN PedidoEstado_pedido = 'A' THEN 1 ELSE 0 END) as canc
        ")->first();

        $comp  = (int)($r->comp ?? 0);
        $pend  = (int)($r->pend ?? 0);
        $canc  = (int)($r->canc ?? 0);
        $total = $comp + $pend + $canc;

        return [
            'title'       => 'Pedidos por Estado',
            'labels'      => ['Completados', 'Pendientes', 'Cancelados'],
            'series'      => [$comp, $pend, $canc],
            'total'       => "{$total} pedidos",
            'labels_data' => ['Completados' => $comp, 'Pendientes' => $pend, 'Cancelados' => $canc],
            'message'     => "Pedidos en el período: **{$comp}** completados, **{$pend}** pendientes y **{$canc}** cancelados. Total: {$total}.",
        ];
    }

    private function pedidosPorCanal(array $f): array
    {
        $collection = $this->baseQuery($f)
            ->join('Canal_pedido', 'Canal_pedido.Canal_pedidoId', '=', 'Pedido.Pedido_canal_pedidoId')
            ->selectRaw('Canal_pedido.Canal_pedidoDescripcion as canal, COUNT(*) as total')
            ->groupBy('Canal_pedido.Canal_pedidoDescripcion')
            ->orderByRaw('COUNT(*) DESC')
            ->get();

        $rows = $collection->map(fn($r) => (array) $r)->toArray();
        $rows = $this->limitarItems($rows, 'canal', 'total');
        $total = array_sum(array_column($rows, 'total'));
        $resumen = collect($rows)->map(fn($r) => "• **{$r['canal']}**: {$r['total']} pedidos")->implode("\n");

        return [
            'title'   => 'Pedidos por Canal de Venta',
            'labels'  => array_column($rows, 'canal'),
            'series'  => array_map('intval', array_column($rows, 'total')),
            'total'   => "{$total} pedidos",
            'message' => "Distribución de pedidos por canal:\n{$resumen}",
        ];
    }

    private function ventasDelDia(array $f, ?string $agrupacion): array
    {
        $porCanal = ($agrupacion === 'canal' || $agrupacion === null);

        $q = DB::table('Pedido')
            ->where('PedidoEliminado', 'N')
            ->where('PedidoEstado_pedido', 'C')
            ->whereBetween(DB::raw('CAST(PedidoFechaCreacion AS DATE)'), [$f['fecha_desde'], $f['fecha_hasta']]);

        if (!empty($f['canal'])) {
            $q->where('Pedido_canal_pedidoId', $f['canal']);
        }

        if ($porCanal) {
            $collection = $q->join('Canal_pedido', 'Canal_pedido.Canal_pedidoId', '=', 'Pedido.Pedido_canal_pedidoId')
                ->selectRaw('Canal_pedido.Canal_pedidoDescripcion as grupo, SUM(PedidoTotal) as monto')
                ->groupBy('Canal_pedido.Canal_pedidoDescripcion')
                ->orderByRaw('SUM(PedidoTotal) DESC')
                ->get();
        } else {
            $collection = $q->selectRaw("CAST(PedidoFechaCreacion AS DATE) as grupo, SUM(PedidoTotal) as monto")
                ->groupByRaw('CAST(PedidoFechaCreacion AS DATE)')
                ->orderByRaw('CAST(PedidoFechaCreacion AS DATE)')
                ->get();
        }

        $rows = $collection->map(fn($r) => (array) $r)->toArray();
        $rows = $this->limitarItems($rows, 'grupo', 'monto');
        $totalRaw = array_sum(array_column($rows, 'monto'));
        $resumen  = collect($rows)->map(fn($r) => "• **{$r['grupo']}**: S/ " . number_format($r['monto'], 2))->implode("\n");

        return [
            'title'     => $porCanal ? 'Órdenes por Canal' : 'Órdenes por Fecha',
            'labels'    => array_column($rows, 'grupo'),
            'series'    => array_map(fn($v) => round((float)$v, 2), array_column($rows, 'monto')),
            'total'     => 'S/ ' . number_format($totalRaw, 2),
            'total_raw' => $totalRaw,
            'message'   => "Órdenes facturadas: **S/ " . number_format($totalRaw, 2) . "**\n{$resumen}",
        ];
    }

    private function ventasPorDia(array $f): array
    {
        $collection = DB::table('Pedido')
            ->where('PedidoEliminado', 'N')
            ->where('PedidoEstado_pedido', 'C')
            ->whereBetween(DB::raw('CAST(PedidoFechaCreacion AS DATE)'), [$f['fecha_desde'], $f['fecha_hasta']])
            ->selectRaw('CAST(PedidoFechaCreacion AS DATE) as fecha, SUM(PedidoTotal) as monto')
            ->groupByRaw('CAST(PedidoFechaCreacion AS DATE)')
            ->orderByRaw('CAST(PedidoFechaCreacion AS DATE)')
            ->get();

        $rows = $collection->map(fn($r) => (array) $r)->toArray();
        $rows = $this->limitarItems($rows, 'fecha', 'monto');
        $totalRaw = array_sum(array_column($rows, 'monto'));

        return [
            'title'     => 'Evolución Diaria de Órdenes',
            'labels'    => array_column($rows, 'fecha'),
            'series'    => array_map(fn($v) => round((float)$v, 2), array_column($rows, 'monto')),
            'total'     => 'S/ ' . number_format($totalRaw, 2),
            'total_raw' => $totalRaw,
            'message'   => "Evolución de órdenes: S/ " . number_format($totalRaw, 2) . " en el período.",
        ];
    }

    private function topProductos(array $f): array
    {
        $rows = DB::table('Detalle_Pedido_Productos')
            ->join('Pedido', 'Pedido.PedidoId', '=', 'Detalle_Pedido_Productos.Detalle_Pedido_Productos_PedidoId')
            ->join('Producto', 'Producto.ProductoId', '=', 'Detalle_Pedido_Productos.Detalle_Pedido_Productos_ProductoId')
            ->where('Pedido.PedidoEliminado', 'N')
            ->where('Detalle_Pedido_Productos.Detalle_Pedido_ProductosEliminado', 'N')
            ->where('Pedido.PedidoEstado_pedido', 'C')
            ->whereBetween(DB::raw('CAST(Pedido.PedidoFechaCreacion AS DATE)'), [$f['fecha_desde'], $f['fecha_hasta']])
            ->selectRaw('Producto.ProductoNombre as producto, SUM(Detalle_Pedido_Productos.Detalle_Pedido_Productos_cantidad) as unidades')
            ->groupBy('Producto.ProductoNombre')
            ->orderByRaw('SUM(Detalle_Pedido_Productos.Detalle_Pedido_Productos_cantidad) DESC')
            ->limit(self::MAX_CHART_ITEMS)
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'series' => [], 'total' => null, 'message' => 'Sin datos de órdenes en el período.'];
        }

        $resumen = $rows->map(fn($r) => "• **{$r->producto}**: {$r->unidades} unidades")->implode("\n");

        return [
            'title'   => 'Top Productos Más Solicitados',
            'labels'  => $rows->pluck('producto')->toArray(),
            'series'  => $rows->pluck('unidades')->map(fn($v) => (float)$v)->toArray(),
            'total'   => $rows->count() . ' productos',
            'message' => "Top productos más vendidos:\n{$resumen}",
        ];
    }

    private function topClientes(array $f): array
    {
        $rows = DB::table('Pedido')
            ->join('Cliente', 'Cliente.ClienteId', '=', 'Pedido.Pedido_ClienteId')
            ->where('Pedido.PedidoEliminado', 'N')
            ->where('Pedido.PedidoEstado_pedido', 'C')
            ->whereBetween(DB::raw('CAST(Pedido.PedidoFechaCreacion AS DATE)'), [$f['fecha_desde'], $f['fecha_hasta']])
            ->selectRaw('Cliente.ClienteNombre as cliente, COUNT(*) as pedidos, SUM(Pedido.PedidoTotal) as monto')
            ->groupBy('Cliente.ClienteNombre')
            ->orderByRaw('SUM(Pedido.PedidoTotal) DESC')
            ->limit(self::MAX_CHART_ITEMS)
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'series' => [], 'total' => null, 'message' => 'Sin compras registradas en el período.'];
        }

        $resumen = $rows->map(fn($r) => "• **{$r->cliente}**: S/ " . number_format($r->monto, 2) . " ({$r->pedidos} pedidos)")->implode("\n");

        return [
            'title'   => 'Top Clientes por Facturación',
            'labels'  => $rows->pluck('cliente')->toArray(),
            'series'  => $rows->pluck('monto')->map(fn($v) => round((float)$v, 2))->toArray(),
            'total'   => 'S/ ' . number_format($rows->sum('monto'), 2),
            'message' => "Top clientes por facturación:\n{$resumen}",
        ];
    }

    private function stockCritico(array $f): array
    {
        $rows = DB::table('Producto')
            ->where('ProductoEliminado', 'N')
            ->where('ProductoEstado', 'A')
            ->whereRaw('CAST(ProductoStockActual AS DECIMAL(10,2)) <= CAST(ProductoStockMinimo AS DECIMAL(10,2))')
            ->select('ProductoNombre as producto', 'ProductoStockActual as stock', 'ProductoStockMinimo as minimo')
            ->orderByRaw('CAST(ProductoStockActual AS DECIMAL(10,2)) ASC')
            ->limit(self::MAX_CHART_ITEMS)
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'series' => [], 'total' => null, 'message' => '¡Excelente! No hay productos en stock crítico.'];
        }

        $resumen = $rows->map(fn($r) => "• **{$r->producto}**: {$r->stock} ud. (mínimo: {$r->minimo})")->implode("\n");

        return [
            'title'   => 'Productos con Stock Crítico',
            'labels'  => $rows->pluck('producto')->toArray(),
            'series'  => $rows->pluck('stock')->map(fn($v) => (float)$v)->toArray(),
            'total'   => $rows->count() . ' productos en alerta',
            'message' => "{$rows->count()} producto(s) en alerta de stock:\n{$resumen}",
        ];
    }

    private function erroresPorTipo(array $f): array
    {
        $collection = $this->baseQuery($f)
            ->where('PedidoTieneError', 'S')
            ->selectRaw("COALESCE(PedidoTipoError, 'Sin clasificar') as tipo, COUNT(*) as total")
            ->groupBy('PedidoTipoError')
            ->orderByRaw('COUNT(*) DESC')
            ->get();

        if ($collection->isEmpty()) {
            return [
                'title'   => 'Errores por Tipo',
                'labels'  => ['Sin errores'],
                'series'  => [0],
                'total'   => '0 errores',
                'message' => '¡Sin errores registrados en el período!',
            ];
        }

        $rows = $collection->map(fn($r) => (array) $r)->toArray();
        $rows = $this->limitarItems($rows, 'tipo', 'total');
        $totalErr = array_sum(array_column($rows, 'total'));
        $resumen  = collect($rows)->map(fn($r) => "• **{$r['tipo']}**: {$r['total']}")->implode("\n");

        return [
            'title'   => 'Errores en Órdenes por Tipo',
            'labels'  => array_column($rows, 'tipo'),
            'series'  => array_map('intval', array_column($rows, 'total')),
            'total'   => "{$totalErr} errores",
            'message' => "{$totalErr} error(es) en órdenes:\n{$resumen}",
        ];
    }

    private function roturasPorCategoria(array $f): array
    {
        $collection = DB::table('Producto')
            ->join('Categoria_Producto', 'Categoria_Producto.Categoria_ProductoId', '=', 'Producto.Producto_Categoria_ProductoId')
            ->where('Producto.ProductoEliminado', 'N')
            ->where('Producto.ProductoEstado', 'A')
            ->whereRaw('CAST(Producto.ProductoStockActual AS DECIMAL(10,2)) = 0')
            ->selectRaw('Categoria_Producto.Categoria_ProductoDescripcion_categoria as categoria, COUNT(*) as roturas')
            ->groupBy('Categoria_Producto.Categoria_ProductoDescripcion_categoria')
            ->orderByRaw('COUNT(*) DESC')
            ->get();

        if ($collection->isEmpty()) {
            return ['labels' => [], 'series' => [], 'total' => null, 'message' => 'No hay roturas de stock.'];
        }

        $rows = $collection->map(fn($r) => (array) $r)->toArray();
        $rows = $this->limitarItems($rows, 'categoria', 'roturas');
        $totalRot = array_sum(array_column($rows, 'roturas'));
        $resumen  = collect($rows)->map(fn($r) => "• **{$r['categoria']}**: {$r['roturas']} producto(s)")->implode("\n");

        return [
            'title'   => 'Roturas de Stock por Categoría',
            'labels'  => array_column($rows, 'categoria'),
            'series'  => array_map('intval', array_column($rows, 'roturas')),
            'total'   => "{$totalRot} roturas",
            'message' => "{$totalRot} rotura(s) de stock:\n{$resumen}",
        ];
    }

    private function tiempoBusquedaPromedio(array $f): array
    {
        $rows = DB::table('ai_tool_logs')
            ->whereIn('tool_name', ['buscar_producto', 'consultar_stock', 'buscar_cliente'])
            ->where('status', 'success')
            ->whereBetween(DB::raw('CAST(created_at AS DATE)'), [$f['fecha_desde'], $f['fecha_hasta']])
            ->selectRaw('tool_name, AVG(duration_ms) as promedio_ms, COUNT(*) as total')
            ->groupBy('tool_name')
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'series' => [], 'total' => null, 'message' => 'Sin datos de tiempo de búsqueda en el período.'];
        }

        $resumen = $rows->map(fn($r) => "• **" . str_replace('_', ' ', $r->tool_name) . "**: " . round($r->promedio_ms) . " ms ({$r->total} ejecuciones)")->implode("\n");

        return [
            'title'   => 'Tiempo Promedio de Búsqueda',
            'labels'  => $rows->pluck('tool_name')->map(fn($t) => str_replace('_', ' ', $t))->toArray(),
            'series'  => $rows->pluck('promedio_ms')->map(fn($v) => round((float)$v))->toArray(),
            'total'   => 'milisegundos',
            'message' => "Tiempos promedio de búsqueda:\n{$resumen}",
        ];
    }

    // ═══════════════════════════════════════════════════════
    // 3. TABLAS DINÁMICAS
    // ═══════════════════════════════════════════════════════

    private function tablaPedidos(array $f, int $n): array
    {
        $rows = DB::table('Pedido')
            ->join('Cliente', 'Cliente.ClienteId', '=', 'Pedido.Pedido_ClienteId')
            ->join('Canal_pedido', 'Canal_pedido.Canal_pedidoId', '=', 'Pedido.Pedido_canal_pedidoId')
            ->where('Pedido.PedidoEliminado', 'N')
            ->when(!empty($f['estado']), fn($q) => $q->where('PedidoEstado_pedido', $f['estado']))
            ->when(!empty($f['fecha_desde']), fn($q) => $q->whereRaw('CAST(PedidoFechaCreacion AS DATE) >= ?', [$f['fecha_desde']]))
            ->when(!empty($f['fecha_hasta']), fn($q) => $q->whereRaw('CAST(PedidoFechaCreacion AS DATE) <= ?', [$f['fecha_hasta']]))
            ->selectRaw('Pedido.PedidoId, Cliente.ClienteNombre, Canal_pedido.Canal_pedidoDescripcion as canal, Pedido.PedidoTotal, Pedido.PedidoEstado_pedido, CAST(Pedido.PedidoFechaCreacion AS DATE) as fecha')
            ->orderByDesc('Pedido.PedidoFechaCreacion')
            ->limit($n)
            ->get()
            ->map(fn($r) => [
                $r->PedidoId,
                $r->ClienteNombre,
                $r->canal,
                'S/ ' . number_format($r->PedidoTotal, 2),
                match ($r->PedidoEstado_pedido) { 'C' => 'Completado', 'P' => 'Pendiente', 'A' => 'Cancelado', default => $r->PedidoEstado_pedido },
                $r->fecha,
            ])->toArray();

        return [['Pedido', 'Cliente', 'Canal', 'Total', 'Estado', 'Fecha'], $rows, 'Listado de Pedidos'];
    }

    private function tablaProductos(array $f, int $n): array
    {
        $rows = DB::table('Producto')
            ->join('Categoria_Producto', 'Categoria_Producto.Categoria_ProductoId', '=', 'Producto.Producto_Categoria_ProductoId')
            ->leftJoin('Detalle_Producto_medida', function ($j) {
                $j->on('Detalle_Producto_medida.Detalle_Producto_medida_ProductoId', '=', 'Producto.ProductoId')
                  ->where('Detalle_Producto_medida.Detalle_Producto_medidaEliminado', '=', 'N');
            })
            ->where('Producto.ProductoEliminado', 'N')
            ->when(!empty($f['categoria']), fn($q) => $q->where('Producto.Producto_Categoria_ProductoId', $f['categoria']))
            ->selectRaw('Producto.ProductoId, Producto.ProductoNombre, Categoria_Producto.Categoria_ProductoDescripcion_categoria as categoria, Producto.ProductoStockActual as stock, Producto.ProductoStockMinimo as minimo, COALESCE(Detalle_Producto_medida.Detalle_Producto_medida_precio_venta, 0) as precio')
            ->orderByRaw('CAST(Producto.ProductoStockActual AS DECIMAL(10,2)) ASC')
            ->limit($n)
            ->get()
            ->map(fn($r) => [
                $r->ProductoId,
                $r->ProductoNombre,
                $r->categoria,
                $r->stock,
                $r->minimo,
                'S/ ' . number_format((float)$r->precio, 2),
            ])->toArray();

        return [['Código', 'Producto', 'Categoría', 'Stock', 'Mínimo', 'Precio'], $rows, 'Catálogo de Productos'];
    }

    private function tablaClientes(array $f, int $n): array
    {
        $rows = DB::table('Cliente')
            ->where('ClienteEliminado', 'N')
            ->when(!empty($f['estado']), fn($q) => $q->where('ClienteEstado', $f['estado']))
            ->selectRaw('ClienteId, ClienteNombre, ClienteTelefono, ClienteDireccion, ClienteEstado')
            ->orderBy('ClienteNombre')
            ->limit($n)
            ->get()
            ->map(fn($r) => [
                $r->ClienteId,
                $r->ClienteNombre,
                $r->ClienteTelefono,
                $r->ClienteDireccion,
                $r->ClienteEstado === 'A' ? 'Activo' : 'Inactivo',
            ])->toArray();

        return [['Código', 'Cliente', 'Teléfono', 'Dirección', 'Estado'], $rows, 'Listado de Clientes'];
    }

    private function tablaMovimientos(array $f, int $n): array
    {
        $rows = DB::table('Movimiento_producto')
            ->join('Producto', 'Producto.ProductoId', '=', 'Movimiento_producto.Movimiento_producto_ProductoId')
            ->when(!empty($f['fecha_desde']), fn($q) => $q->whereRaw('CAST(Movimiento_producto.Movimiento_productoFecha AS DATE) >= ?', [$f['fecha_desde']]))
            ->when(!empty($f['fecha_hasta']), fn($q) => $q->whereRaw('CAST(Movimiento_producto.Movimiento_productoFecha AS DATE) <= ?', [$f['fecha_hasta']]))
            ->selectRaw('Producto.ProductoNombre as producto, Movimiento_producto.Movimiento_productoTipo as tipo, Movimiento_producto.Movimiento_productoCantidad as cantidad, Movimiento_producto.Movimiento_productoMotivo as motivo, CAST(Movimiento_producto.Movimiento_productoFecha AS DATE) as fecha')
            ->orderByDesc('Movimiento_producto.Movimiento_productoFecha')
            ->limit($n)
            ->get()
            ->map(fn($r) => [
                $r->producto,
                $r->tipo === 'E' ? 'Entrada' : 'Salida',
                $r->cantidad,
                $r->motivo,
                $r->fecha,
            ])->toArray();

        return [['Producto', 'Tipo', 'Cantidad', 'Motivo', 'Fecha'], $rows, 'Movimientos de Inventario'];
    }

    // ═══════════════════════════════════════════════════════
    // 4. UTILIDADES PRIVADAS
    // ═══════════════════════════════════════════════════════

    private function baseQuery(array $f): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('Pedido')->where('PedidoEliminado', 'N');

        if (!empty($f['fecha_desde'])) {
            $q->whereRaw('CAST(PedidoFechaCreacion AS DATE) >= ?', [$f['fecha_desde']]);
        }
        if (!empty($f['fecha_hasta'])) {
            $q->whereRaw('CAST(PedidoFechaCreacion AS DATE) <= ?', [$f['fecha_hasta']]);
        }
        if (!empty($f['canal'])) {
            $q->where('Pedido_canal_pedidoId', $f['canal']);
        }

        return $q;
    }

    private function limitarItems(array $items, string $lk, string $vk): array
    {
        if (count($items) <= self::MAX_CHART_ITEMS) {
            return $items;
        }

        $principal = array_slice($items, 0, self::MAX_CHART_ITEMS - 1);
        $resto     = array_slice($items, self::MAX_CHART_ITEMS - 1);
        $sumaResto = array_sum(array_column($resto, $vk));

        $principal[] = [$lk => 'Otros', $vk => $sumaResto];
        return $principal;
    }

    private function emptyState(string $metrica, array $f): array
    {
        $periodo = ($f['fecha_desde'] === $f['fecha_hasta'])
            ? "para el {$f['fecha_desde']}"
            : "del {$f['fecha_desde']} al {$f['fecha_hasta']}";
        $nombres = [
            'pedidos_por_estado'       => 'pedidos',
            'ventas_del_dia'           => 'órdenes facturadas',
            'ventas_por_dia'           => 'órdenes por fecha',
            'top_productos'            => 'órdenes de productos',
            'top_clientes'             => 'compras de clientes',
            'stock_critico'            => 'productos en nivel crítico',
            'errores_por_tipo'         => 'errores en órdenes',
            'roturas_por_categoria'    => 'roturas de stock',
            'tiempo_busqueda_promedio' => 'registros de búsqueda',
        ];
        $nombre = $nombres[$metrica] ?? $metrica;

        return [
            'success'   => true,
            'data'      => null,
            'ui_action' => [
                'type'    => 'render_empty_state',
                'message' => "No se registraron datos de {$nombre} {$periodo}.",
                'icon'    => '📊',
            ],
            'message' => "No encontré datos de {$nombre} {$periodo}.",
        ];
    }

    private function resolverPeriodo(string $periodo): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodo)) {
            $desde = \Carbon\Carbon::parse($periodo . '-01')->format('Y-m-d');
            $hasta = \Carbon\Carbon::parse($periodo . '-01')->endOfMonth()->format('Y-m-d');
        } else {
            $desde = $hasta = $periodo;
        }
        return [$desde, $hasta];
    }

    private function calcularMetricaPeriodo(string $metrica, string $desde, string $hasta): array
    {
        return match ($metrica) {
            'ventas', 'ventas_del_dia' => (function () use ($desde, $hasta) {
                $val = DB::table('Pedido')
                    ->where('PedidoEliminado', 'N')->where('PedidoEstado_pedido', 'C')
                    ->whereBetween(DB::raw('CAST(PedidoFechaCreacion AS DATE)'), [$desde, $hasta])
                    ->sum('PedidoTotal');
                return ['valor' => (float)$val, 'etiqueta' => 'Órdenes', 'formatted' => 'S/ ' . number_format($val, 2)];
            })(),
            'pedidos' => (function () use ($desde, $hasta) {
                $val = DB::table('Pedido')
                    ->where('PedidoEliminado', 'N')
                    ->whereBetween(DB::raw('CAST(PedidoFechaCreacion AS DATE)'), [$desde, $hasta])
                    ->count();
                return ['valor' => $val, 'etiqueta' => 'Pedidos', 'formatted' => "{$val} pedidos"];
            })(),
            'errores' => (function () use ($desde, $hasta) {
                $val = DB::table('Pedido')
                    ->where('PedidoEliminado', 'N')->where('PedidoTieneError', 'S')
                    ->whereBetween(DB::raw('CAST(PedidoFechaCreacion AS DATE)'), [$desde, $hasta])
                    ->count();
                return ['valor' => $val, 'etiqueta' => 'Errores', 'formatted' => "{$val} errores"];
            })(),
            default => ['valor' => 0, 'etiqueta' => $metrica, 'formatted' => '0'],
        };
    }

    private function calcularTSA(string $periodo): array
    {
        [$inicio, $fin] = [\Carbon\Carbon::parse($periodo . '-01')->format('Y-m-d'), \Carbon\Carbon::parse($periodo . '-01')->endOfMonth()->format('Y-m-d')];
        $total    = DB::table('ai_tool_logs')->whereBetween(DB::raw('CAST(created_at AS DATE)'), [$inicio, $fin])->count();
        $exitosas = DB::table('ai_tool_logs')->whereBetween(DB::raw('CAST(created_at AS DATE)'), [$inicio, $fin])->where('status', 'success')->count();
        $TSA      = $total > 0 ? round(($exitosas / $total) * 100, 2) : 0;

        return [
            'indicador'  => 'TSA',
            'nombre'     => 'Tasa de Servicio de la IA',
            'formula'    => '(Consultas Exitosas / Total Consultas) × 100',
            'frecuencia' => 'Mensual',
            'periodo'    => $periodo,
            'variables'  => ['Exitosas' => $exitosas, 'Total' => $total],
            'resultado'  => $TSA,
            'meta'       => 95.0,
            'cumple'     => $TSA >= 95.0,
            'anterior'   => ['periodo' => '', 'resultado' => 0, 'delta_pct' => 0],
            'tooltip'    => 'Porcentaje de ejecuciones de herramientas de IA exitosas en el período.',
        ];
    }

    private function getSuggestedChips(string $metrica): array
    {
        return [
            'pedidos_por_estado' => [
                ['label' => '💰 Órdenes del día', 'query' => 'Muéstrame las órdenes de hoy'],
                ['label' => '🏆 Top clientes', 'query' => 'Top 5 clientes de hoy'],
            ],
            'ventas_del_dia' => [
                ['label' => '📦 Pedidos por canal', 'query' => 'Gráfico de pedidos por canal de hoy'],
                ['label' => '📈 Evolución semanal', 'query' => 'Órdenes de los últimos 7 días'],
            ],
            'top_productos' => [
                ['label' => '⚠️ Stock crítico', 'query' => 'Muéstrame los productos con stock crítico'],
                ['label' => '🏆 Top clientes', 'query' => 'Top 5 clientes del período'],
            ],
            'stock_critico' => [
                ['label' => '📦 Roturas por categoría', 'query' => 'Roturas de stock por categoría'],
                ['label' => '🛒 Pedidos pendientes', 'query' => 'Tabla de pedidos pendientes de hoy'],
            ],
        ][$metrica] ?? [
            ['label' => '📊 PODE del mes', 'query' => '¿Cuál es el PODE de este mes?'],
            ['label' => '🏆 Top productos', 'query' => 'Top 5 productos más solicitados'],
        ];
    }

    private function getTitulo(string $metrica, array $f): string
    {
        $periodo = !empty($f['fecha_desde']) ? " ({$f['fecha_desde']}" . (!empty($f['fecha_hasta']) && $f['fecha_hasta'] !== $f['fecha_desde'] ? " al {$f['fecha_hasta']}" : '') . ')' : '';

        return match ($metrica) {
            'pedidos_por_estado'       => "Pedidos por Estado{$periodo}",
            'pedidos_por_canal'        => "Pedidos por Canal{$periodo}",
            'ventas_del_dia'           => "Órdenes del Período{$periodo}",
            'ventas_por_dia'           => "Evolución Diaria de Órdenes{$periodo}",
            'top_productos'            => "Top Productos Más Solicitados{$periodo}",
            'top_clientes'             => "Top Clientes por Facturación{$periodo}",
            'stock_critico'            => 'Productos en Stock Crítico',
            'errores_por_tipo'         => "Errores en Órdenes{$periodo}",
            'roturas_por_categoria'    => 'Roturas de Stock por Categoría',
            'tiempo_busqueda_promedio' => "Tiempo de Búsqueda Promedio{$periodo}",
            default                    => ucwords(str_replace('_', ' ', $metrica)),
        };
    }

    private function getMetricaMetadata(string $metrica): array
    {
        return match ($metrica) {
            'ventas_por_dia', 'ventas_del_dia', 'top_clientes' => [
                'formato' => 'moneda',
                'tipo'    => 'moneda',
                'unidad'  => 'soles',
                'simbolo' => 'S/',
            ],
            'pedidos_por_estado', 'pedidos_por_canal' => [
                'formato' => 'entero',
                'tipo'    => 'cantidad',
                'unidad'  => 'pedidos',
                'simbolo' => '',
            ],
            'top_productos', 'stock_critico' => [
                'formato' => 'entero',
                'tipo'    => 'cantidad',
                'unidad'  => 'unidades',
                'simbolo' => '',
            ],
            'errores_por_tipo' => [
                'formato' => 'entero',
                'tipo'    => 'cantidad',
                'unidad'  => 'errores',
                'simbolo' => '',
            ],
            'roturas_por_categoria' => [
                'formato' => 'entero',
                'tipo'    => 'cantidad',
                'unidad'  => 'roturas',
                'simbolo' => '',
            ],
            'tiempo_busqueda_promedio' => [
                'formato' => 'decimal',
                'tipo'    => 'tiempo',
                'unidad'  => 'segundos',
                'simbolo' => 's',
            ],
            default => [
                'formato' => 'entero',
                'tipo'    => 'cantidad',
                'unidad'  => '',
                'simbolo' => '',
            ],
        };
    }
}
