<?php

namespace App\Services;

use App\Models\DetalleOrdenCompra;
use App\Models\MovimientoProducto;
use App\Models\OrdenCompra;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PurchasePredictionService
 *
 * Calcula predicciones semanales de reposición de inventario para Comercial Valencia.
 * Modelo determinista: Fórmula + Estadística (sin IA generativa para cálculos numéricos).
 *
 * Semana ISO-8601: YYYY-WNN (ej. 2026-W38)
 */
class PurchasePredictionService
{
    // =========================================================================
    // CONSTANTES DE CONFIGURACIÓN
    // =========================================================================

    /** Número máximo de semanas históricas a analizar */
    private const MAX_SEMANAS_HISTORICO = 4;

    /** Factor de cobertura total: 1.5 semanas (1 operativa + 0.5 colchón de seguridad) */
    private const FACTOR_COBERTURA = 1.5;

    /** Clamp mínimo de tendencia (evitar sobreajustar a la baja) */
    private const TENDENCIA_MIN = 0.8;

    /** Clamp máximo de tendencia (evitar sobreajustar al alza) */
    private const TENDENCIA_MAX = 1.2;

    /** TTL de la caché semanal (7 días) */
    private const CACHE_TTL_SEMANA = 60 * 60 * 24 * 7;

    /** Pesos del score de urgencia */
    private const PESO_URGENCIA  = 0.5;
    private const PESO_ROTACION  = 0.3;
    private const PESO_TENDENCIA = 0.2;

    /** Pisos de urgencia para productos SIN ventas recientes */
    private const PISO_QUIEBRE   = 0.60;   // stock_actual <= 0
    private const PISO_BAJO_MIN  = 0.50;   // stock_actual <= stock_minimo
    private const SCORE_CUBIERTO = 0.00;   // sin ventas y stock OK

    // =========================================================================
    // API PÚBLICA
    // =========================================================================

    /**
     * Genera predicciones para TODOS los productos activos de la semana.
     * Cachea el resultado de forma semanal.
     *
     * @param  string|null $semana  Semana ISO (ej. "2026-W38"). Si null, usa la semana actual.
     * @return array
     */
    public function generarPrediccionesSemanales(?string $semana = null): array
    {
        $semana  = $semana ?? $this->semanaActual();
        $cacheKey = "ai_purchase_predictions:{$semana}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SEMANA, function () use ($semana) {
            $productos = $this->productosActivos();
            $predicciones = [];

            foreach ($productos as $producto) {
                $pred = $this->calcularPrediccionProducto($producto, $semana);
                if ($pred !== null) {
                    $predicciones[] = $pred;
                }
            }

            // Ordenar por score descendente
            usort($predicciones, fn($a, $b) => $b['score'] <=> $a['score']);

            $aReponer = array_filter($predicciones, fn($p) => ($p['cantidad_empaques'] ?? 0) > 0 || ($p['cantidad_base'] ?? 0) > 0);
            $totalEstimado = array_sum(array_column($predicciones, 'total_estimado'));

            return [
                'semana'           => $semana,
                'generado_en'      => now()->toIso8601String(),
                'predicciones'     => $predicciones,
                'total_estimado'   => round($totalEstimado, 2),
                'total_productos'  => count($predicciones),
                'total_a_reponer'  => count($aReponer),
                'criticos'         => count(array_filter($predicciones, fn($p) => $p['estado_critico'])),
            ];
        });
    }

    /**
     * Genera predicciones filtradas por categoría.
     *
     * @param  string      $categoriaId
     * @param  string|null $semana
     * @return array
     */
    public function generarPrediccionesPorCategoria(string $categoriaId, ?string $semana = null): array
    {
        $semana = $semana ?? $this->semanaActual();
        $datos  = $this->generarPrediccionesSemanales($semana);
        $predicciones = $datos['predicciones'] ?? [];

        $filtrados = array_values(array_filter(
            $predicciones,
            fn($p) => ($p['categoria_id'] ?? null) === $categoriaId
        ));

        $aReponer = array_filter($filtrados, fn($p) => ($p['cantidad_empaques'] ?? 0) > 0 || ($p['cantidad_base'] ?? 0) > 0);
        $totalEstimado = array_sum(array_column($filtrados, 'total_estimado'));

        return [
            'semana'           => $semana,
            'generado_en'      => $datos['generado_en'] ?? now()->toIso8601String(),
            'categoria_id'     => $categoriaId,
            'predicciones'     => $filtrados,
            'total_estimado'   => round($totalEstimado, 2),
            'total_productos'  => count($filtrados),
            'total_a_reponer'  => count($aReponer),
            'criticos'         => count(array_filter($filtrados, fn($p) => !empty($p['estado_critico']))),
        ];
    }

    /**
     * Genera la predicción de un producto individual.
     *
     * @param  string      $productoId
     * @param  string|null $semana
     * @return array|null  null si el producto no existe, está inactivo o eliminado.
     */
    public function predecirProducto(string $productoId, ?string $semana = null): ?array
    {
        $semana  = $semana ?? $this->semanaActual();
        $datos   = $this->generarPrediccionesSemanales($semana);
        $predicciones = $datos['predicciones'] ?? [];

        foreach ($predicciones as $p) {
            if (($p['producto_id'] ?? null) === $productoId) {
                return $p;
            }
        }

        // Fallback: intentar calcular directamente si el producto existe
        $producto = Producto::with([
            'categoria',
            'detalleProductoMedidas' => fn($q) => $q->where('Detalle_Producto_medidaEliminado', 'N'),
            'proveedores',
        ])
        ->where('ProductoId', $productoId)
        ->where('ProductoEliminado', 'N')
        ->where('ProductoEstado', 'A')
        ->first();

        return $producto ? $this->calcularPrediccionProducto($producto, $semana) : null;
    }

    /**
     * Invalida la caché de predicciones de compra (por defecto, únicamente la semana actual).
     *
     * @param  string|null $semana  Si null, invalida únicamente la semana activa en curso.
     */
    public function invalidarCacheSemanal(?string $semana = null): void
    {
        $semanaTarget = $semana ?? $this->semanaActual();
        Cache::forget("ai_purchase_predictions:{$semanaTarget}");

        Log::info('PurchasePredictionService: Caché semanal invalidada.', ['semana' => $semanaTarget]);
    }

    // =========================================================================
    // CÁLCULO PRINCIPAL POR PRODUCTO
    // =========================================================================

    /**
     * Calcula todos los datos de predicción para un producto en una semana dada.
     *
     * @param  \App\Models\Producto $producto
     * @param  string               $semana
     * @return array|null
     */
    private function calcularPrediccionProducto(Producto $producto, string $semana): ?array
    {
        try {
            $productoId   = $producto->ProductoId;
            $stockActual  = (float)($producto->ProductoStockActual ?? 0);
            $stockMinimo  = (float)($producto->ProductoStockMinimo ?? 0);
            $semanaInicio = $this->inicioSemana($semana);

            // --- 1. Semanas históricas con sus ventas y detección de quiebres ---
            $historial = $this->historialSemanal($productoId, $semanaInicio, self::MAX_SEMANAS_HISTORICO);

            // --- 2. Semanas efectivas (excluyendo quiebres de stock confirmados) ---
            $semanasEfectivas  = $this->calcularSemanasEfectivas($historial);
            $ventasTotales     = array_sum(array_column($semanasEfectivas, 'ventas'));
            $numSemanasEfect   = max(1, count($semanasEfectivas));
            $demandaSemanal    = $ventasTotales / $numSemanasEfect;

            // --- 3. Tendencia (W1+W2 vs W3+W4) ---
            $tendencia = $this->calcularTendencia($historial);

            // --- 4. Demanda ajustada ---
            $demandaAjustada = $demandaSemanal * $tendencia;

            // --- 5. Stock en tránsito ---
            $stockTransito = $this->stockEnTransito($productoId);

            // --- 6. Stock objetivo y cantidad sugerida ---
            $stockObjetivo    = $demandaAjustada * self::FACTOR_COBERTURA;
            $cantidadBase     = max(0, $stockObjetivo - $stockActual - $stockTransito);
            $cantidadBase     = round($cantidadBase, 2);

            // --- 7. Presentación preferida de compra ---
            $presentacion    = $this->presentacionPreferida($producto);
            $multiploEmpaque = $presentacion['factor'] ?? 1;
            $cantidadEmpaques = $multiploEmpaque > 1
                ? (int)ceil($cantidadBase / $multiploEmpaque)
                : $cantidadBase;

            // --- 8. Precio de referencia ponderado ---
            $precioInfo = $this->calcularPrecioReferencia($productoId, $semanaInicio, $multiploEmpaque, $presentacion);

            // --- 9. Total estimado ---
            $totalEstimado = $precioInfo['precio_referencia_empaque'] > 0
                ? round($cantidadEmpaques * $precioInfo['precio_referencia_empaque'], 2)
                : 0.0;

            // --- 10. Cobertura en días ---
            $coberturaDias = $this->calcularCoberturaDias($stockActual, $demandaSemanal);

            // --- 11. Score de urgencia ---
            $ventas4Semanas = $ventasTotales;
            $score = $this->calcularScore(
                $stockActual,
                $stockMinimo,
                $stockObjetivo,
                $demandaSemanal,
                $tendencia,
                $historial,
                $ventas4Semanas
            );

            // --- 12. Estado crítico ---
            $estadoCritico = $stockActual <= 0 || $stockActual <= $stockMinimo;

            // --- 13. Alerta de precio ---
            $alertaPrecio = $this->evaluarAlertaPrecio($precioInfo['variacion_pct']);

            // Proveedor habitual
            $proveedor = $producto->proveedores->first();

            return [
                'producto_id'          => $productoId,
                'producto_nombre'      => $producto->ProductoNombre,
                'categoria_id'         => $producto->Categoria_ProductoId ?? ($producto->categoria->Categoria_ProductoId ?? null),
                'categoria_nombre'     => $producto->categoria?->Categoria_ProductoDescripcion_categoria ?? 'Sin categoría',
                'proveedor_id'         => $proveedor?->ProveedorId,
                'proveedor_nombre'     => $proveedor?->ProveedorRazonSocial,

                // Presentación
                'unidad_base_desc'     => $presentacion['unidad_base_desc'] ?? 'Unidad',
                'empaque_preferido'    => $presentacion['descripcion'] ?? 'Unidad',
                'multiplo_empaque'     => $multiploEmpaque,

                // Stock
                'stock_actual'         => $stockActual,
                'stock_minimo'         => $stockMinimo,
                'stock_transito'       => $stockTransito,
                'stock_objetivo'       => round($stockObjetivo, 2),
                'estado_critico'       => $estadoCritico,

                // Demanda
                'demanda_semanal'      => round($demandaSemanal, 2),
                'semanas_efectivas'    => $numSemanasEfect,
                'tendencia'            => round($tendencia, 3),
                'demanda_ajustada'     => round($demandaAjustada, 2),

                // Sugerencia de compra
                'cantidad_base'        => $cantidadBase,
                'cantidad_empaques'    => $cantidadEmpaques,
                'total_estimado'       => $totalEstimado,

                // Precios
                'precio_referencia_base'     => $precioInfo['precio_referencia_base'],
                'precio_referencia_empaque'  => $precioInfo['precio_referencia_empaque'],
                'precio_fallback'            => $precioInfo['precio_fallback'],
                'variacion_precio_pct'       => $precioInfo['variacion_pct'],
                'alerta_precio'              => $alertaPrecio,

                // Cobertura
                'cobertura_dias'       => $coberturaDias,
                'cobertura_dias_texto' => $this->textoCoberturaLabel($coberturaDias),

                // Score y semanas
                'score'                => round($score, 4),
                'historial_semanas'    => $this->formatearHistorialParaJSON($historial),
            ];
        } catch (\Throwable $e) {
            Log::error("PurchasePredictionService: Error en producto {$producto->ProductoId}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // =========================================================================
    // HISTORIAL Y QUIEBRES DE STOCK
    // =========================================================================

    /**
     * Obtiene el historial de ventas semanales de las últimas N semanas antes de $semanaInicio.
     * Devuelve un array ordenado del más reciente (W-1) al más antiguo (W-4).
     *
     * @param  string   $productoId
     * @param  Carbon   $semanaInicio  Inicio (lunes) de la semana actual de predicción.
     * @param  int      $maxSemanas
     * @return array    Cada elemento: ['semana'=>'YYYY-WNN', 'inicio'=>Carbon, 'fin'=>Carbon, 'ventas'=>float, 'quiebre'=>bool]
     */
    private function historialSemanal(string $productoId, Carbon $semanaInicio, int $maxSemanas): array
    {
        $historial = [];

        for ($i = 1; $i <= $maxSemanas; $i++) {
            $inicio = $semanaInicio->copy()->subWeeks($i)->startOfWeek(Carbon::MONDAY);
            $fin    = $inicio->copy()->endOfWeek(Carbon::SUNDAY);

            // Ventas de la semana (Detalle_Pedido_Productos): cantidad en unidades base
            $ventas = DB::table('Detalle_Pedido_Productos as dpp')
                ->join('Pedido as p', 'p.PedidoId', '=', 'dpp.Detalle_Pedido_Productos_PedidoId')
                ->where('dpp.Detalle_Pedido_Productos_ProductoId', $productoId)
                ->where('dpp.Detalle_Pedido_ProductosEliminado', 'N')
                ->whereIn('p.PedidoEstado_pedido', ['C', 'E']) // Completado, Entregado
                ->where('p.PedidoEliminado', 'N')
                ->whereBetween('p.PedidoFecha_pedido', [$inicio->toDateTimeString(), $fin->toDateTimeString()])
                ->sum('dpp.Detalle_Pedido_Productos_cantidad_base');

            $ventas = (float)$ventas;

            // Quiebre de stock: detectar solo si ventas == 0 Y stock <= 0 en esa semana
            $quiebre = false;
            if ($ventas == 0) {
                // Verificar saldo de Kardex en esa semana
                $saldoKardex = DB::table('Movimiento_producto')
                    ->where('Movimiento_producto_ProductoId', $productoId)
                    ->where('Movimiento_productoEliminado', 'N')
                    ->whereBetween('Movimiento_productoFecha_Movimiento', [
                        $inicio->toDateTimeString(),
                        $fin->toDateTimeString(),
                    ])
                    ->orderBy('Movimiento_productoFecha_Movimiento', 'desc')
                    ->value('Movimiento_productoCantidadSaldo');

                if ($saldoKardex !== null) {
                    $quiebre = (float)$saldoKardex <= 0;
                } else {
                    // Sin movimientos en esa semana: usar stock actual como proxy
                    $stockProxy = DB::table('Producto')
                        ->where('ProductoId', $productoId)
                        ->value('ProductoStockActual');
                    $quiebre = (float)($stockProxy ?? 1) <= 0;
                }
            }

            $historial[] = [
                'semana'  => $inicio->format('o') . '-W' . $inicio->format('W'),
                'inicio'  => $inicio,
                'fin'     => $fin,
                'ventas'  => $ventas,
                'quiebre' => $quiebre,
            ];
        }

        return $historial; // [W-1, W-2, W-3, W-4]
    }

    /**
     * Filtra el historial conservando solo las semanas efectivas:
     * se excluyen aquellas con ventas == 0 y quiebre == true.
     *
     * @param  array $historial
     * @return array Semanas efectivas (array filtrado)
     */
    private function calcularSemanasEfectivas(array $historial): array
    {
        return array_values(array_filter(
            $historial,
            fn($s) => !($s['ventas'] == 0 && $s['quiebre'])
        ));
    }

    // =========================================================================
    // TENDENCIA
    // =========================================================================

    /**
     * Calcula la tendencia comparando el promedio de W-1+W-2 vs W-3+W-4.
     * Si el denominador es 0 (sin datos históricos), devuelve 1.0 (neutral).
     *
     * @param  array $historial
     * @return float  Clamp en [0.8, 1.2]
     */
    private function calcularTendencia(array $historial): float
    {
        $ventasRecientes  = (($historial[0]['ventas'] ?? 0) + ($historial[1]['ventas'] ?? 0)) / 2;
        $ventasAnteriores = (($historial[2]['ventas'] ?? 0) + ($historial[3]['ventas'] ?? 0)) / 2;

        if ($ventasAnteriores <= 0) {
            return 1.0; // Neutral: sin denominador
        }

        $raw = $ventasRecientes / $ventasAnteriores;
        return max(self::TENDENCIA_MIN, min(self::TENDENCIA_MAX, $raw));
    }

    // =========================================================================
    // STOCK EN TRÁNSITO
    // =========================================================================

    /**
     * Obtiene la cantidad en tránsito del producto desde órdenes de compra en estado 'P' (Pendiente).
     *
     * @param  string $productoId
     * @return float
     */
    private function stockEnTransito(string $productoId): float
    {
        // Buscamos en Detalle_Orden_Compra con join a Orden_Compra en estado 'P'
        $enTransito = DB::table('Detalle_Orden_Compra as doc')
            ->join('Orden_Compra as oc', 'oc.Orden_CompraId', '=', 'doc.Detalle_Orden_Compra_Orden_CompraId')
            ->where('doc.Detalle_ProductoId', $productoId)
            ->where('doc.Detalle_Orden_CompraEliminado', 'N')
            ->where('oc.Orden_CompraEstado', 'P')
            ->where('oc.Orden_CompraEliminado', 'N')
            // Normalizamos a unidades base usando LEFT JOIN con Detalle_Producto_medida
            ->leftJoin('Detalle_Producto_medida as dpm', function ($j) {
                $j->on('dpm.Detalle_Producto_medida_ProductoId', '=', 'doc.Detalle_ProductoId')
                  ->on('dpm.Detalle_Producto_medida_unidades_medidaId', '=', 'doc.Detalle_UnidadMedidaId')
                  ->where('dpm.Detalle_Producto_medidaEliminado', '=', 'N');
            })
            ->selectRaw('SUM(doc.Detalle_Orden_CompraCantidad * ISNULL(NULLIF(dpm.Detalle_Producto_medida_factor_conversion, 0), 1)) as total_base')
            ->value('total_base');

        return (float)($enTransito ?? 0);
    }

    // =========================================================================
    // PRECIOS PONDERADOS
    // =========================================================================

    /**
     * Calcula el precio de referencia ponderado a unidad base y lo proyecta al empaque.
     *
     * JOIN: Detalle_Orden_Compra.Detalle_UnidadMedidaId + Detalle_Orden_Compra.Detalle_ProductoId
     *       → Detalle_Producto_medida_factor_conversion
     *
     * @param  string $productoId
     * @param  Carbon $semanaInicio   Para calcular las últimas 4 semanas de compras
     * @param  int    $multiploEmpaque
     * @param  array  $presentacion   Presentación preferida (contiene precio_fallback)
     * @return array ['precio_referencia_base'=>float, 'precio_referencia_empaque'=>float, 'precio_fallback'=>bool, 'variacion_pct'=>float|null]
     */
    private function calcularPrecioReferencia(
        string $productoId,
        Carbon $semanaInicio,
        int $multiploEmpaque,
        array $presentacion
    ): array {
        $hace4Semanas = $semanaInicio->copy()->subWeeks(4)->startOfWeek(Carbon::MONDAY);

        // Obtener compras de las últimas 4 semanas con factor de conversión
        $compras = DB::table('Detalle_Orden_Compra as doc')
            ->join('Orden_Compra as oc', 'oc.Orden_CompraId', '=', 'doc.Detalle_Orden_Compra_Orden_CompraId')
            ->leftJoin('Detalle_Producto_medida as dpm', function ($j) {
                $j->on('dpm.Detalle_Producto_medida_ProductoId', '=', 'doc.Detalle_ProductoId')
                  ->on('dpm.Detalle_Producto_medida_unidades_medidaId', '=', 'doc.Detalle_UnidadMedidaId')
                  ->where('dpm.Detalle_Producto_medidaEliminado', '=', 'N');
            })
            ->where('doc.Detalle_ProductoId', $productoId)
            ->where('doc.Detalle_Orden_CompraEliminado', 'N')
            ->where('oc.Orden_CompraEliminado', 'N')
            ->whereIn('oc.Orden_CompraEstado', ['C', 'P']) // Completadas y en tránsito
            ->where('oc.Orden_CompraFecha', '>=', $hace4Semanas->toDateTimeString())
            ->selectRaw('
                doc.Detalle_Orden_CompraCantidad,
                doc.Detalle_Orden_CompraPrecioUnitario,
                ISNULL(NULLIF(dpm.Detalle_Producto_medida_factor_conversion, 0), 1) as factor
            ')
            ->get();

        if ($compras->isEmpty()) {
            // Fallback: usar precio_compra de la presentación preferida en el catálogo
            $precioFallback = (float)($presentacion['precio_compra'] ?? 0);
            return [
                'precio_referencia_base'    => $precioFallback > 0 ? round($precioFallback / max(1, $multiploEmpaque), 4) : 0.0,
                'precio_referencia_empaque' => $precioFallback,
                'precio_fallback'           => true,
                'variacion_pct'             => null,
            ];
        }

        // Calcular precio ponderado en unidad base
        $sumaPonderada  = 0.0;
        $sumaBaseTotal  = 0.0;
        $ultimoPrecioBase = 0.0;

        foreach ($compras as $c) {
            $factor         = max(1, (int)$c->factor);
            $costoBase      = (float)$c->Detalle_Orden_CompraPrecioUnitario / $factor;
            $cantidadBase   = (float)$c->Detalle_Orden_CompraCantidad * $factor;
            $sumaPonderada += $cantidadBase * $costoBase;
            $sumaBaseTotal += $cantidadBase;
            $ultimoPrecioBase = $costoBase; // El último íter queda como la "última compra" (al final del loop)
        }

        $precioReferenciaBase = $sumaBaseTotal > 0
            ? round($sumaPonderada / $sumaBaseTotal, 4)
            : 0.0;

        $precioReferenciaEmpaque = round($precioReferenciaBase * $multiploEmpaque, 2);

        // Variación respecto a la última compra vs el promedio
        $variacionPct = null;
        if ($ultimoPrecioBase > 0 && $precioReferenciaBase > 0) {
            $variacionPct = round((($ultimoPrecioBase - $precioReferenciaBase) / $precioReferenciaBase) * 100, 1);
        }

        return [
            'precio_referencia_base'    => $precioReferenciaBase,
            'precio_referencia_empaque' => $precioReferenciaEmpaque,
            'precio_fallback'           => false,
            'variacion_pct'             => $variacionPct,
        ];
    }

    // =========================================================================
    // COBERTURA DE DÍAS
    // =========================================================================

    /**
     * Calcula la cobertura en días del stock actual sobre la demanda semanal.
     * Guard: demandaSemanal == 0 → 999.0 (stock cubierto) o 0.0 (sin stock, sin demanda).
     *
     * @param  float $stockActual
     * @param  float $demandaSemanal
     * @return float
     */
    private function calcularCoberturaDias(float $stockActual, float $demandaSemanal): float
    {
        if ($demandaSemanal > 0) {
            return round(($stockActual / $demandaSemanal) * 7, 1);
        }
        return $stockActual > 0 ? 999.0 : 0.0;
    }

    /**
     * Devuelve el texto legible para `cobertura_dias`.
     * NUNCA devuelve "999.0 días" — lo reemplaza por "Cubierto / Sin rotación".
     */
    private function textoCoberturaLabel(float $dias): string
    {
        if ($dias >= 999.0) {
            return 'Cubierto / Sin rotación';
        }
        if ($dias <= 0.0) {
            return '0 días (Quiebre inmediato)';
        }
        return number_format($dias, 1) . ' días';
    }

    // =========================================================================
    // SCORE DE URGENCIA
    // =========================================================================

    /**
     * Calcula el score ponderado de urgencia.
     *
     * Regla de piso: aplicada EXCLUSIVAMENTE si ventas_4_semanas == 0.
     * Para productos CON ventas, rige la fórmula estándar (la urgencia ya eleva el score naturalmente).
     *
     * @param  float $stockActual
     * @param  float $stockMinimo
     * @param  float $stockObjetivo
     * @param  float $demandaSemanal
     * @param  float $tendencia
     * @param  array $historial
     * @param  float $ventas4Semanas  Total de ventas en las 4 semanas históricas (sin filtrar quiebres)
     * @return float Score [0.0, 1.0]
     */
    private function calcularScore(
        float $stockActual,
        float $stockMinimo,
        float $stockObjetivo,
        float $demandaSemanal,
        float $tendencia,
        array $historial,
        float $ventas4Semanas
    ): float {
        // ---- Componente Urgencia ----
        if ($stockObjetivo > 0) {
            $deficitRatio = max(0, ($stockObjetivo - $stockActual)) / $stockObjetivo;
            $urgencia = min(1.0, $deficitRatio);
        } else {
            $urgencia = $stockActual <= 0 ? 1.0 : 0.0;
        }

        // ---- Componente Rotación ----
        // Normalizar respecto al máximo de ventas de las 4 semanas (o 1 si todas son 0)
        $maxVentas = max(1, ...array_column($historial, 'ventas'));
        $rotacion  = $demandaSemanal > 0 ? min(1.0, $demandaSemanal / $maxVentas) : 0.0;

        // ---- Componente Tendencia (normalizada: si > 1 → bueno, si < 1 → menos urgente) ----
        $tendenciaNorm = max(0.0, min(1.0, ($tendencia - self::TENDENCIA_MIN) / (self::TENDENCIA_MAX - self::TENDENCIA_MIN)));

        // ---- Score ponderado ----
        $score = ($urgencia * self::PESO_URGENCIA)
               + ($rotacion  * self::PESO_ROTACION)
               + ($tendenciaNorm * self::PESO_TENDENCIA);

        $score = max(0.0, min(1.0, $score));

        // ---- Piso de urgencia: SOLO para productos sin ventas recientes ----
        if ($ventas4Semanas == 0) {
            if ($stockActual <= 0) {
                $score = max(self::PISO_QUIEBRE, $score);
            } elseif ($stockActual <= $stockMinimo) {
                $score = max(self::PISO_BAJO_MIN, $score);
            } else {
                $score = self::SCORE_CUBIERTO;
            }
        }

        return $score;
    }

    // =========================================================================
    // PRESENTACIÓN PREFERIDA DE COMPRA
    // =========================================================================

    /**
     * Devuelve la presentación preferida del producto para compras (mayor factor_conversion).
     *
     * @param  \App\Models\Producto $producto
     * @return array ['factor'=>int, 'descripcion'=>string, 'precio_compra'=>float, 'unidad_base_desc'=>string]
     */
    private function presentacionPreferida(Producto $producto): array
    {
        $medidas = $producto->detalleProductoMedidas ?? collect();

        if ($medidas->isEmpty()) {
            return [
                'factor'          => 1,
                'descripcion'     => 'Unidad',
                'precio_compra'   => 0.0,
                'unidad_base_desc'=> 'Unidad',
            ];
        }

        // La presentación con mayor factor es la preferida para compras mayoristas
        $preferida = $medidas->sortByDesc('Detalle_Producto_medida_factor_conversion')->first();
        $base      = $medidas->sortBy('Detalle_Producto_medida_factor_conversion')->first();

        return [
            'factor'           => max(1, (int)$preferida->Detalle_Producto_medida_factor_conversion),
            'descripcion'      => $preferida->unidadMedida?->unidades_medidaDescripcion ?? 'Unidad',
            'precio_compra'    => (float)($preferida->Detalle_Producto_medida_precio_compra ?? 0),
            'unidad_base_desc' => $base?->unidadMedida?->unidades_medidaDescripcion ?? 'Unidad',
        ];
    }

    // =========================================================================
    // ALERTA DE PRECIO
    // =========================================================================

    /**
     * Evalúa si existe alerta de variación de precio.
     *
     * @param  float|null $variacionPct
     * @return string|null  'alza'|'baja'|null
     */
    private function evaluarAlertaPrecio(?float $variacionPct): ?string
    {
        if ($variacionPct === null) {
            return null;
        }
        if ($variacionPct >= 5.0) {
            return 'alza';
        }
        if ($variacionPct <= -5.0) {
            return 'baja';
        }
        return null;
    }

    // =========================================================================
    // HELPERS DE SEMANA ISO
    // =========================================================================

    /**
     * Devuelve la semana ISO actual en formato YYYY-WNN.
     */
    public function semanaActual(): string
    {
        return now()->format('o') . '-W' . now()->format('W');
    }

    /**
     * Devuelve la semana ISO anterior al la semana actual.
     */
    private function semanaAnterior(): string
    {
        return now()->subWeek()->format('o') . '-W' . now()->subWeek()->format('W');
    }

    /**
     * Devuelve el Carbon del lunes (inicio) de una semana ISO.
     *
     * @param  string $semana  Formato: "2026-W38"
     * @return Carbon
     */
    private function inicioSemana(string $semana): Carbon
    {
        // Parsear "2026-W38"
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $semana, $m)) {
            $year  = (int)$m[1];
            $week  = (int)$m[2];
            return Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
        }
        return now()->startOfWeek(Carbon::MONDAY);
    }

    // =========================================================================
    // HELPERS DE PRESENTACIÓN
    // =========================================================================

    /**
     * Obtiene los productos activos con todas las relaciones necesarias para el cálculo.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function productosActivos()
    {
        return Producto::with([
            'categoria',
            'detalleProductoMedidas' => fn($q) => $q
                ->where('Detalle_Producto_medidaEliminado', 'N')
                ->with('unidadMedida'),
            'proveedores',
        ])
        ->where('ProductoEliminado', 'N')
        ->where('ProductoEstado', 'A')
        ->get();
    }

    /**
     * Formatea el historial de semanas para la respuesta JSON final (sin objetos Carbon).
     *
     * @param  array $historial
     * @return array
     */
    private function formatearHistorialParaJSON(array $historial): array
    {
        return array_map(fn($s) => [
            'semana'  => $s['semana'],
            'ventas'  => $s['ventas'],
            'quiebre' => $s['quiebre'],
        ], $historial);
    }

    // =========================================================================
    // HERRAMIENTA DEL CHATBOT (Invocada desde GeminiToolsService)
    // =========================================================================

    /**
     * Método de integración con el chatbot Valencia AI.
     * Expone las predicciones como respuesta estructurada para el dispatcher.
     *
     * @param  array $args  Keys opcionales: 'categoria_id', 'producto_id', 'semana', 'limite'
     * @return array
     */
    public function obtenerPrediccionesParaChatbot(array $args): array
    {
        try {
            $semana      = $args['semana'] ?? null;
            $categoriaId = $args['categoria_id'] ?? null;
            $productoId  = $args['producto_id'] ?? null;
            $limite      = (int)($args['limite'] ?? 10);

            if ($productoId) {
                $pred = $this->predecirProducto($productoId, $semana);
                if (!$pred) {
                    return [
                        'success'        => true,
                        'semana'         => $semana ?? $this->semanaActual(),
                        'predicciones'   => [],
                        'total_estimado' => 0.0,
                        'total_a_reponer'=> 0,
                        'message'        => 'Producto no encontrado o sin datos suficientes.',
                    ];
                }

                $cantEmp = $pred['cantidad_empaques'] ?? 0;
                $totEst  = (float)($pred['total_estimado'] ?? 0.0);

                if ($cantEmp <= 0) {
                    $msg = "El producto {$pred['producto_nombre']} tiene stock suficiente (Stock físico: {$pred['stock_actual']}, En tránsito: {$pred['stock_transito']}) para cubrir la demanda semanal (cobertura: {$pred['cobertura_dias_texto']}). No requiere compra esta semana (Inversión: S/ 0.00).";
                } else {
                    $msg = "Predicción para {$pred['producto_nombre']}: Sugerir comprar {$cantEmp} {$pred['empaque_preferido']} (S/ " . number_format($totEst, 2) . " estimado).";
                }

                return [
                    'success'         => true,
                    'semana'          => $semana ?? $this->semanaActual(),
                    'predicciones'    => [$pred],
                    'total_estimado'  => $totEst,
                    'total_a_reponer' => $cantEmp > 0 ? 1 : 0,
                    'message'         => $msg,
                ];
            }

            if ($categoriaId) {
                $datos = $this->generarPrediccionesPorCategoria($categoriaId, $semana);
            } else {
                $datos = $this->generarPrediccionesSemanales($semana);
            }

            $todos        = $datos['predicciones'] ?? [];
            $aReponer     = array_values(array_filter($todos, fn($p) => ($p['cantidad_empaques'] ?? 0) > 0));
            $numAReponer  = count($aReponer);
            $totalEst     = (float)($datos['total_estimado'] ?? 0.0);

            if ($numAReponer === 0) {
                $mensaje = "Todos los productos analizados ({$datos['total_productos']}) cuentan actualmente con stock suficiente o mercadería en tránsito para cubrir la demanda proyectada de 1.5 semanas. No se requiere emitir órdenes de compra esta semana (Inversión estimada: S/ 0.00).";
                $lista = array_slice($todos, 0, $limite);
            } else {
                $mensaje = "Se encontraron {$numAReponer} producto(s) que requieren reposición esta semana. Inversión estimada: S/ " . number_format($totalEst, 2) . ".";
                $lista = array_slice($aReponer, 0, $limite);
            }

            return [
                'success'          => true,
                'semana'           => $datos['semana'],
                'predicciones'     => $lista,
                'total_estimado'   => $totalEst,
                'total_productos'  => $datos['total_productos'],
                'total_a_reponer'  => $numAReponer,
                'criticos'         => $datos['criticos'],
                'message'          => $mensaje,
            ];
        } catch (\Throwable $e) {
            Log::error('PurchasePredictionService::obtenerPrediccionesParaChatbot', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'data'    => null,
                'message' => 'No fue posible calcular las predicciones en este momento.',
            ];
        }
    }
}
