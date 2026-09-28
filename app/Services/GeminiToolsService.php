<?php

namespace App\Services;

use App\Models\CategoriaProducto;
use App\Models\MovimientoProducto;
use App\Models\Notificacion;
use App\Models\OrdenCompra;
use App\Models\Pedido;
use App\Models\Producto;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * GeminiToolsService
 *
 * Dispatcher central para el ciclo de Function Calling de Gemini (58 Tools).
 * Recibe (toolName, args) y enruta a la lógica de dominio existente.
 * Devuelve siempre: ['success' => bool, 'data' => mixed, 'message' => string]
 */
class GeminiToolsService
{
    /**
     * Catálogo estricto de las herramientas disponibles en Valencia AI.
     */
    public const AVAILABLE_TOOLS = [
        // Pedidos (13)
        'crear_pedido_cliente', 'abrir_formulario_pedido', 'limpiar_formulario_pedido', 'recuperar_borrador_pedido', 'listar_pedidos', 'obtener_pedido', 'completar_pedido',
        'cancelar_pedido', 'actualizar_pedido', 'eliminar_pedido', 'obtener_pedidos_del_dia',
        'obtener_estadisticas_pedidos', 'verificar_timeout_pedidos',
        // Clientes (8)
        'crear_cliente', 'buscar_cliente', 'obtener_cliente', 'listar_clientes',
        'actualizar_cliente', 'eliminar_cliente', 'obtener_historial_cliente', 'abrir_formulario_cliente',
        // Productos (10)
        'crear_producto', 'buscar_producto', 'obtener_producto', 'listar_productos',
        'actualizar_producto', 'eliminar_producto', 'consultar_stock', 'listar_productos_bajo_stock', 'ajustar_stock',
        'listar_categorias',
        // Proveedores (7)
        'crear_proveedor', 'buscar_proveedor', 'obtener_proveedor', 'listar_proveedores',
        'actualizar_proveedor', 'eliminar_proveedor', 'abrir_formulario_proveedor',
        // Órdenes de Compra (10)
        'crear_orden_compra', 'abrir_formulario_orden_compra', 'limpiar_formulario_orden_compra', 'recuperar_borrador_orden_compra', 'listar_ordenes_compra', 'obtener_orden_compra', 'completar_orden_compra',
        'cancelar_orden_compra', 'actualizar_orden_compra', 'obtener_estadisticas_compras',
        // Canales (3)
        'listar_canales', 'crear_canal', 'actualizar_canal',
        // Kárdex (3)
        'consultar_kardex', 'listar_movimientos', 'obtener_resumen_kardex',
        // Reportes (5)
        'obtener_estadisticas_generales', 'obtener_ventas_por_periodo', 'obtener_top_productos',
        'obtener_top_clientes', 'obtener_reporte_inventario',
        // Notificaciones (3)
        'listar_alertas', 'marcar_alerta_leida', 'notificar_pedidos_por_expirar',
        // Utilidades (4)
        'solicitar_campo_faltante', 'confirmar_accion', 'buscar_global', 'obtener_ayuda',
        // MODULO 11: BI Conversacional — Gráficos y Análisis Dinámicos (6) — Total: 67 tools
        'generar_grafico_dashboard', 'consultar_kpi', 'generar_reporte_periodo', 'comparar_periodos', 'renderizar_tabla',
        'listar_graficos_disponibles',
        // MODULO 12: Predicciones de Reabastecimiento (1) — Total: 68 tools
        'obtener_predicciones_compra',
    ];

    /**
     * Herramientas de solo lectura cuyos resultados pueden ser cacheados y permitidos a roles de consulta.
     */
    public const READ_ONLY_TOOLS = [
        'abrir_formulario_pedido', 'limpiar_formulario_pedido', 'recuperar_borrador_pedido',
        'listar_pedidos', 'obtener_pedido', 'obtener_pedidos_del_dia', 'obtener_estadisticas_pedidos',
        'buscar_cliente', 'obtener_cliente', 'listar_clientes', 'obtener_historial_cliente', 'abrir_formulario_cliente',
        'buscar_producto', 'obtener_producto', 'listar_productos', 'consultar_stock', 'listar_productos_bajo_stock',
        'listar_categorias',
        'buscar_proveedor', 'obtener_proveedor', 'listar_proveedores', 'abrir_formulario_proveedor',
        'abrir_formulario_orden_compra', 'limpiar_formulario_orden_compra', 'recuperar_borrador_orden_compra',
        'listar_ordenes_compra', 'obtener_orden_compra', 'obtener_estadisticas_compras',
        'listar_canales',
        'consultar_kardex', 'listar_movimientos', 'obtener_resumen_kardex',
        'obtener_estadisticas_generales', 'obtener_ventas_por_periodo', 'obtener_top_productos',
        'obtener_top_clientes', 'obtener_reporte_inventario',
        'listar_alertas',
        'buscar_global', 'obtener_ayuda',
        // BI Conversacional (solo lectura – consultas analíticas)
        'generar_grafico_dashboard', 'consultar_kpi', 'generar_reporte_periodo', 'comparar_periodos', 'renderizar_tabla',
        'listar_graficos_disponibles',
        // Predicciones de Reabastecimiento (solo lectura)
        'obtener_predicciones_compra',
    ];

    public function __construct(
        protected PedidoService          $pedidoService,
        protected ClienteService         $clienteService,
        protected InventarioService      $inventarioService,
        protected ProveedorService       $proveedorService,
        protected OrdenCompraService     $ordenCompraService,
        protected CanalPedidoService     $canalPedidoService,
        protected NotificacionService    $notificacionService,
        protected DashboardService       $dashboardService,
        protected ChartGeneratorService  $chartGeneratorService
    ) {}

    /**
     * Convierte recursivamente cualquier estructura de datos (Modelos Eloquent, Colecciones, stdClass)
     * a arrays primitivos de PHP para evitar bugs de deserialización 'Incomplete object' en Cache.
     */
    public static function toPlainArray(mixed $data): mixed
    {
        if ($data instanceof \Illuminate\Contracts\Support\Arrayable) {
            $data = $data->toArray();
        } elseif ($data instanceof \stdClass) {
            $data = (array) $data;
        } elseif ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::toPlainArray($value);
            }
        }

        return $data;
    }

    /**
     * Determina si una herramienta es de solo lectura.
     */
    public function isReadOnly(string $toolName): bool
    {
        return in_array($toolName, self::READ_ONLY_TOOLS, true);
    }

    /**
     * Dispatch principal con validación de existencia, control de solo lectura y caché.
     */
    public function dispatch(string $toolName, array $args, bool $readOnlyMode = false, ?array $liveDraft = null, ?string $userMessage = null, ?string $sessionId = null): array
    {
        // 1. Manejo de tool no reconocida (Alucinaciones de Gemini)
        if (!in_array($toolName, self::AVAILABLE_TOOLS, true)) {
            Log::warning("Valencia AI: Tool inexistente solicitada por Gemini", [
                'tool' => $toolName,
                'args' => $args,
            ]);
            return [
                'success'    => false,
                'data'       => null,
                'message'    => "La herramienta '{$toolName}' no está disponible en Valencia AI.",
                'suggestion' => 'Intenta reformular tu solicitud o consultar las capacidades disponibles con obtener_ayuda.',
            ];
        }

        // 2. Control de permisos para modo de solo lectura
        if ($readOnlyMode && !$this->isReadOnly($toolName)) {
            Log::info("Valencia AI: Bloqueada tool de escritura '{$toolName}' por modo solo lectura");
            return [
                'success' => false,
                'status'  => 'permission_denied',
                'data'    => null,
                'message' => "Acción restringida: Te encuentras en modo de solo lectura y la herramienta '{$toolName}' realiza modificaciones en la base de datos.",
            ];
        }

        // 3. Caché de consultas con TTL específico por herramienta
        $cacheTtl = $this->getToolCacheTtl($toolName);
        if ($cacheTtl !== null && $cacheTtl > 0) {
            $normArgs = $this->normalizeCacheArgs($args);
            $cacheKey = "ai_tool:{$toolName}:" . md5(json_encode($normArgs));
            return Cache::remember($cacheKey, now()->addSeconds($cacheTtl), function () use ($toolName, $args, $liveDraft, $userMessage, $sessionId) {
                return self::toPlainArray($this->executeTool($toolName, $args, $liveDraft, $userMessage, $sessionId));
            });
        }

        $result = $this->executeTool($toolName, $args, $liveDraft, $userMessage, $sessionId);

        // Si la herramienta ejecutada fue una acción mutante exitosa (escritura en BD),
        // invalidar el epoch global del caché semántico de consultas de Valencia AI.
        if (!$this->isReadOnly($toolName) && ($result['success'] ?? false) === true) {
            $epoch = (int) Cache::get('ai_cache_epoch', 1);
            $newEpoch = $epoch + 1;
            Cache::put('ai_cache_epoch', $newEpoch, now()->addDays(30));
            Log::info("Valencia AI: Epoch de caché semántico incrementado por mutación en '{$toolName}'. Nuevo epoch: {$newEpoch}");
        }

        return $result;
    }

    /**
     * Retorna el TTL de caché en segundos para herramientas de lectura, o null si NUNCA debe cachearse.
     */
    protected function getToolCacheTtl(string $toolName): ?int
    {
        // ❌ NUNCA cachear mutaciones o UI actions interactivas
        if (
            str_starts_with($toolName, 'crear_') ||
            str_starts_with($toolName, 'actualizar_') ||
            str_starts_with($toolName, 'eliminar_') ||
            str_starts_with($toolName, 'cancelar_') ||
            str_starts_with($toolName, 'completar_') ||
            str_starts_with($toolName, 'abrir_') ||
            str_starts_with($toolName, 'limpiar_') ||
            str_starts_with($toolName, 'recuperar_') ||
            in_array($toolName, ['ajustar_stock', 'marcar_alerta_leida', 'solicitar_campo_faltante', 'confirmar_accion'], true)
        ) {
            return null;
        }

        return match (true) {
            $toolName === 'consultar_stock' => 30, // 30 seg (el stock fluctúa rápido)
            $toolName === 'listar_categorias' => 600, // 10 min (catálogo casi estático)
            str_starts_with($toolName, 'obtener_estadisticas_') ||
            $toolName === 'obtener_pedidos_del_dia' ||
            $toolName === 'obtener_ventas_por_periodo' => 60, // 1 min

            // BI Conversacional: caché por combinación (metrica + filtros), TTL=60s
            in_array($toolName, [
                'generar_grafico_dashboard', 'consultar_kpi',
                'generar_reporte_periodo', 'comparar_periodos', 'renderizar_tabla',
            ], true) => 60, // 60s — la lógica interna de ChartGeneratorService agrega su propio caché

            in_array($toolName, [
                'buscar_cliente', 'buscar_producto', 'buscar_proveedor',
                'obtener_cliente', 'obtener_producto', 'obtener_proveedor', 'obtener_pedido', 'obtener_orden_compra',
                'listar_productos', 'listar_clientes', 'listar_proveedores', 'listar_canales', 'consultar_kardex'
            ], true) => 180, // 3 min

            $this->isReadOnly($toolName) => 120, // 2 min para resto de tools de lectura
            default => null,
        };
    }

    /**
     * Normaliza argumentos para maximizar aciertos de caché (minúsculas, trim y orden de claves).
     */
    protected function normalizeCacheArgs(array $args): array
    {
        $normalized = [];
        foreach ($args as $k => $v) {
            if (is_string($v)) {
                $normalized[$k] = mb_strtolower(trim($v));
            } elseif (is_array($v)) {
                $normalized[$k] = $this->normalizeCacheArgs($v);
            } else {
                $normalized[$k] = $v;
            }
        }
        ksort($normalized);
        return $normalized;
    }

    /**
     * Valida si el mensaje del usuario expresa una confirmación explícita e inequívoca.
     * Evita que palabras ambiguas como "el pedido", "la orden" o consultas creen registros por error.
     */
    public function tieneConfirmacionExplicita(?string $message): bool
    {
        if (empty($message)) {
            return false;
        }

        $norm = mb_strtolower(trim($message));
        $norm = preg_replace('/[.,\/#!$%\^&\*;:{}=\-_`~()¿?¡!]/u', ' ', $norm);
        $norm = trim(preg_replace('/\s+/', ' ', $norm));

        // 1. Coincidencia exacta de respuestas cortas afirmativas
        $palabrasExactas = [
            'si', 'sí', 'confirmo', 'confirmado', 'confirmar', 'correcto', 'procede', 'dale',
            'adelante', 'hazlo', 'de acuerdo', 'ok', 'okay', 'listo', 'ya', 'claro',
            'guardalo', 'guárdalo', 'crealo', 'créalo', 'registralo', 'regístralo',
            'guardar', 'guarda', 'crear', 'crea', 'registrar', 'registra'
        ];

        if (in_array($norm, $palabrasExactas, true)) {
            return true;
        }

        // 2. Frases inequívocas de confirmación
        $patronesConfirmacion = [
            '/\b(?:si|sí)\b/u',
            '/\bconfirmo\b/u',
            '/\bconfirmar\b/u',
            '/\bconfirmado\b/u',
            '/\bguardar?\s+(?:el\s+|la\s+)?(?:pedido|orden|cliente)\b/u',
            '/\bcrear?\s+(?:el\s+|la\s+)?(?:pedido|orden|cliente)\b/u',
            '/\bregistrar?\s+(?:el\s+|la\s+)?(?:pedido|orden|cliente)\b/u',
            '/\b(?:guardalo|guárdalo|crealo|créalo|registralo|regístralo)\b/u',
            '/\b(?:dale|procede|adelante|hazlo)\b/u',
            '/\besta\s+bien\b/u',
            '/\bestá\s+bien\b/u',
            '/\bde\s+acuerdo\b/u',
        ];

        foreach ($patronesConfirmacion as $patron) {
            if (preg_match($patron, $norm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina si una colección (Eloquent Collection, Support Collection, array) tiene resultados.
     * Evita el bug de !empty() sobre objetos y el crash de $coleccion[0] en colecciones vacías.
     */
    private function tieneResultados(mixed $coleccion): bool
    {
        return collect($coleccion)->isNotEmpty();
    }

    /**
     * Determina si el término referencia a un cliente genérico (mostrador, público, etc.).
     * IMPORTANTE: cadenas vacías o sólo espacios retornan FALSE para que el sistema solicite aclaración.
     */
    private function esClienteGenerico(?string $term): bool
    {
        if ($term === null || trim($term) === '') {
            return false;
        }

        // Normalización textual determinista (sin fonética/soundex para evitar falsos positivos)
        $norm = mb_strtolower(trim($term));
        $norm = strtr($norm, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $norm = preg_replace('/[^a-z0-9\s]/u', ' ', $norm);
        $norm = trim(preg_replace('/\s+/', ' ', $norm));

        $listaBlanca = [
            'cliente general', 'general', 'mostrador', 'venta mostrador', 'venta en mostrador',
            'publico general', 'publico en general', 'venta al publico', 'cliente publico',
            'consumidor final', 'cf', 'cliente ocasional', 'ocasional',
            'venta rapida', 'varios', 'cliente varios', 'anonimo', 'sin nombre',
            'cli 00011', 'cli00011', 'clienete general', // typos comunes
        ];

        return in_array($norm, $listaBlanca, true);
    }

    /**
     * Genera la clave de caché particionada del borrador de pedido pendiente para una sesión.
     */
    private function draftCacheKey(string $sessionId): string
    {
        return "ai_pending_order_draft:session:{$sessionId}";
    }

    /**
     * Genera la clave de la papelera de borradores de una sesión.
     */
    private function draftTrashCacheKey(string $sessionId): string
    {
        return "ai_pending_order_draft_trash:session:{$sessionId}";
    }

    /**
     * Genera la clave de caché particionada del borrador de orden de compra pendiente para una sesión.
     */
    private function purchaseDraftCacheKey(string $sessionId): string
    {
        return "ai_pending_purchase_order_draft:session:{$sessionId}";
    }

    /**
     * Genera la clave de la papelera de órdenes de compra de una sesión.
     */
    private function purchaseDraftTrashCacheKey(string $sessionId): string
    {
        return "ai_pending_purchase_order_draft_trash:session:{$sessionId}";
    }

    /**
     * Determina si el término referencia a un proveedor comodín o compras menores (caja chica, varios, etc.).
     */
    private function esProveedorGenerico(?string $term): bool
    {
        if ($term === null || trim($term) === '') {
            return false;
        }

        $norm = mb_strtolower(trim($term));
        $norm = strtr($norm, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $norm = preg_replace('/[^a-z0-9\s]/u', ' ', $norm);
        $norm = trim(preg_replace('/\s+/', ' ', $norm));

        $listaBlanca = [
            'proveedor general', 'general', 'varios', 'proveedor varios', 'compras varias',
            'compra menor', 'compras menores', 'caja chica', 'gastos menores', 'ocasional',
            'proveedor ocasional', 'sin proveedor', 'anonimo', 'prv 00000', 'prv00000',
        ];

        return in_array($norm, $listaBlanca, true);
    }

    /**
     * Normaliza una razón social peruana eliminando sufijos societarios y opcionalmente prefijos de rubro.
     */
    private function normalizarRazonSocial(string $text, bool $quitarPrefijosRubro = false): string
    {
        $norm = mb_strtolower(trim($text), 'UTF-8');
        $norm = strtr($norm, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        // Eliminar sufijos societarios peruanos comunes
        $sufijos = [
            '/\b(sociedad\s+anonima\s+cerrada|sociedad\s+anonima|sociedad\s+comercial\s+de\s+responsabilidad\s+limitada|empresa\s+individual\s+de\s+responsabilidad\s+limitada)\b/i',
            '/\b(s\.?a\.?a\.?|s\.?a\.?c\.?|s\.?a\.?|e\.?i\.?r\.?l\.?|s\.?r\.?l\.?|s\.?c\.?r\.?l\.?)\b/i',
            '/\b(saa|sac|eirl|srl|scrl)\b/i',
        ];
        foreach ($sufijos as $pattern) {
            $norm = preg_replace($pattern, ' ', $norm);
        }

        if ($quitarPrefijosRubro) {
            $prefijos = [
                '/^\b(distribuidora|comercial|corporacion|inversiones|grupo|importaciones|exportaciones|import|export|empresa|representaciones)\b\s+/i',
            ];
            foreach ($prefijos as $pattern) {
                $norm = preg_replace($pattern, ' ', $norm);
            }
        }

        $norm = preg_replace('/[^a-z0-9\s]/u', ' ', $norm);
        return trim(preg_replace('/\s+/', ' ', $norm));
    }

    /**
     * Evalúa si dos razones sociales son coherentes entre sí aplicando normalización y token matching.
     */
    private function sonRazonesSocialesCoherentes(string $buscado, string $nuevo): bool
    {
        $normA = $this->normalizarRazonSocial($buscado, false);
        $normB = $this->normalizarRazonSocial($nuevo, false);

        if (empty($normA) || empty($normB)) {
            return false;
        }

        // 1. Coincidencia idéntica completa o error tipográfico menor en la razón social
        if ($normA === $normB || levenshtein($normA, $normB) <= 2) {
            return true;
        }

        // 2. Extraer los nombres distintivos "core" (sin prefijos genéricos de rubro como 'distribuidora', 'comercial', etc.)
        // Evita que dos empresas distintas compartiendo solo la palabra 'distribuidora' alcancen similitud >= 75% artificialmente.
        $coreA = $this->normalizarRazonSocial($buscado, true);
        $coreB = $this->normalizarRazonSocial($nuevo, true);

        if (!empty($coreA) && !empty($coreB)) {
            if ($coreA === $coreB) {
                return true;
            }
            similar_text($coreA, $coreB, $simCore);
            if ($simCore >= 75 || levenshtein($coreA, $coreB) <= 2) {
                return true;
            }

            // Inclusión de tokens core significativos (ej: tokens de 'los andes' dentro de 'los andes del sur')
            $tokensA = array_values(array_filter(explode(' ', $coreA), fn($t) => strlen($t) >= 3));
            $tokensB = array_values(array_filter(explode(' ', $coreB), fn($t) => strlen($t) >= 3));

            if (!empty($tokensA) && !empty($tokensB)) {
                $intersect = array_intersect($tokensA, $tokensB);
                if (count($intersect) === count($tokensA) || count($intersect) === count($tokensB)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Obtiene proveedores alternativos activos sugeridos para reasignación,
     * priorizando similitud textual y frecuencia de compras en los últimos 90 días.
     */
    private function obtenerProveedoresAlternativos(string $termino, int $limit = 3): array
    {
        $activos = \App\Models\Proveedor::where('ProveedorEliminado', 'N')
            ->where('ProveedorEstado', 'A')
            ->get();

        if ($activos->isEmpty()) {
            return [];
        }

        // Frecuencia en órdenes de compra en los últimos 90 días
        $hace90Dias = Carbon::now()->subDays(90)->toDateString();
        $comprasRecientes = DB::table('Orden_Compra')
            ->select('Orden_Compra_ProveedorId', DB::raw('COUNT(*) as total_ordenes'))
            ->where('Orden_CompraEliminado', 'N')
            ->where('Orden_CompraFecha', '>=', $hace90Dias)
            ->groupBy('Orden_Compra_ProveedorId')
            ->pluck('total_ordenes', 'Orden_Compra_ProveedorId')
            ->toArray();

        $maxFrecuencia = !empty($comprasRecientes) ? max($comprasRecientes) : 1;
        $normTermino = $this->normalizarRazonSocial($termino, true);

        $scored = [];
        foreach ($activos as $prv) {
            $normRazon = $this->normalizarRazonSocial($prv->ProveedorRazonSocial, true);
            similar_text($normTermino, $normRazon, $similitud);

            $frec = $comprasRecientes[$prv->ProveedorId] ?? 0;
            $frecNorm = $maxFrecuencia > 0 ? ($frec / $maxFrecuencia) * 100 : 0;

            // Score ponderado: 70% similitud textual + 30% frecuencia reciente
            $score = ($similitud * 0.70) + ($frecNorm * 0.30);

            $scored[] = [
                'proveedor_id'          => $prv->ProveedorId,
                'proveedor_razon_social'=> $prv->ProveedorRazonSocial,
                'proveedor_ruc'         => $prv->ProveedorRuc,
                'score'                 => $score,
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Resuelve un proveedor por ID, RUC o razón social, detectando ambigüedades entre homónimos.
     */
    private function resolverProveedorOAmbiguedad(string $termino): array
    {
        $termino = trim($termino);
        if (empty($termino)) {
            return ['status' => 'not_found'];
        }

        // 1. Coincidencia directa por ID de proveedor (PRV-XXXXX)
        if (str_starts_with($termino, 'PRV-')) {
            $p = \App\Models\Proveedor::where('ProveedorId', $termino)->where('ProveedorEliminado', 'N')->first();
            if ($p) {
                return ['status' => 'exact', 'proveedor' => $p];
            }
        }

        // 2. Coincidencia por RUC exacto (11 dígitos)
        if (preg_match('/^\d{11}$/', $termino)) {
            $p = \App\Models\Proveedor::where('ProveedorRuc', $termino)->where('ProveedorEliminado', 'N')->first();
            if ($p) {
                return ['status' => 'exact', 'proveedor' => $p];
            }
        }

        // 3. Búsqueda rápida en BD
        $encontrados = $this->proveedorService->buscarProveedoresRapido($termino, 15);
        $activos = $encontrados->filter(fn($prv) => ($prv->ProveedorEstado ?? 'A') === 'A' && ($prv->ProveedorEliminado ?? 'N') === 'N');

        // Filtrar proveedores activos cuya razón social sea realmente coherente con el término buscado
        // (descarta falsos positivos que solo coinciden por palabras genéricas como 'distribuidora' o 'comercial')
        $coherentes = $activos->filter(fn($prv) => $this->sonRazonesSocialesCoherentes($termino, $prv->ProveedorRazonSocial));

        if ($coherentes->isEmpty()) {
            return ['status' => 'not_found'];
        }

        // Si solo hay 1 proveedor coherente
        if ($coherentes->count() === 1) {
            return ['status' => 'exact', 'proveedor' => $coherentes->first()];
        }

        // Si hay varios proveedores coherentes:
        // Si múltiples proveedores en BD coinciden en su nombre core (ej. 'Gloria S.A.' vs 'Distribuidora Gloria S.A.C.'),
        // pedir desambiguación obligatoria para evitar asignaciones silenciosas a proveedores homónimos
        $normTerminoCore = $this->normalizarRazonSocial($termino, true);
        $candidatosCore = $coherentes->filter(function ($prv) use ($normTerminoCore) {
            $prvCore = $this->normalizarRazonSocial($prv->ProveedorRazonSocial, true);
            return !empty($prvCore) && ($prvCore === $normTerminoCore || str_contains($prvCore, $normTerminoCore) || str_contains($normTerminoCore, $prvCore));
        });

        if ($candidatosCore->count() > 1) {
            return ['status' => 'ambiguous', 'candidatos' => $candidatosCore->values()];
        }

        if ($candidatosCore->count() === 1) {
            return ['status' => 'exact', 'proveedor' => $candidatosCore->first()];
        }

        return ['status' => 'ambiguous', 'candidatos' => $coherentes->values()];
    }

    /**
     * Ejecuta la herramienta contra los servicios de dominio correspondientes.
     */
    protected function executeTool(string $toolName, array $args, ?array $liveDraft = null, ?string $userMessage = null, ?string $sessionId = null): array
    {
        try {
            return match ($toolName) {
                // MODULO 1: PEDIDOS (13 tools)
                'crear_pedido_cliente'         => $this->crearPedidoCliente($args, $liveDraft, $userMessage, $sessionId),
                'abrir_formulario_pedido'      => $this->abrirFormularioPedido($args, $sessionId),
                'limpiar_formulario_pedido'    => $this->limpiarFormularioPedido($args, $sessionId),
                'recuperar_borrador_pedido'    => $this->recuperarBorradorPedido($args, $sessionId),
                'listar_pedidos'               => $this->listarPedidos($args),
                'obtener_pedido'               => $this->obtenerPedido($args),
                'completar_pedido'             => $this->completarPedido($args),
                'cancelar_pedido'              => $this->cancelarPedido($args),
                'actualizar_pedido'            => $this->actualizarPedido($args),
                'eliminar_pedido'              => $this->eliminarPedido($args),
                'obtener_pedidos_del_dia'      => $this->obtenerPedidosDelDia($args),
                'obtener_estadisticas_pedidos' => $this->obtenerEstadisticasPedidos($args),
                'verificar_timeout_pedidos'    => $this->verificarTimeoutPedidos($args),

                // MODULO 2: CLIENTES (8 tools)
                'crear_cliente'                => $this->crearCliente($args, $sessionId),
                'buscar_cliente'               => $this->buscarCliente($args),
                'obtener_cliente'              => $this->obtenerCliente($args),
                'listar_clientes'              => $this->listarClientes($args),
                'actualizar_cliente'           => $this->actualizarCliente($args),
                'eliminar_cliente'             => $this->eliminarCliente($args),
                'obtener_historial_cliente'    => $this->obtenerHistorialCliente($args),
                'abrir_formulario_cliente'     => $this->abrirFormularioCliente($args, $sessionId),

                // MODULO 3: PRODUCTOS (9 tools)
                'crear_producto'               => $this->crearProducto($args),
                'buscar_producto'              => $this->buscarProducto($args),
                'obtener_producto'             => $this->obtenerProducto($args),
                'listar_productos'             => $this->listarProductos($args),
                'actualizar_producto'          => $this->actualizarProducto($args),
                'eliminar_producto'            => $this->eliminarProducto($args),
                'consultar_stock'              => $this->consultarStock($args),
                'listar_productos_bajo_stock'  => $this->listarProductosBajoStock($args),
                'ajustar_stock'                => $this->ajustarStock($args),
                'listar_categorias'            => $this->listarCategorias($args),

                // MODULO 4: PROVEEDORES (7 tools)
                'crear_proveedor'              => $this->crearProveedor($args, $sessionId),
                'buscar_proveedor'             => $this->buscarProveedor($args),
                'obtener_proveedor'            => $this->obtenerProveedor($args),
                'listar_proveedores'           => $this->listarProveedores($args),
                'actualizar_proveedor'         => $this->actualizarProveedor($args),
                'eliminar_proveedor'           => $this->eliminarProveedor($args),
                'abrir_formulario_proveedor'   => $this->abrirFormularioProveedor($args, $sessionId),

                // MODULO 5: ORDENES DE COMPRA (10 tools)
                'crear_orden_compra'             => $this->crearOrdenCompra($args, $liveDraft, $userMessage, $sessionId),
                'abrir_formulario_orden_compra'  => $this->abrirFormularioOrdenCompra($args, $sessionId),
                'limpiar_formulario_orden_compra'=> $this->limpiarFormularioOrdenCompra($args, $sessionId),
                'recuperar_borrador_orden_compra'=> $this->recuperarBorradorOrdenCompra($args, $sessionId),
                'listar_ordenes_compra'          => $this->listarOrdenesCompra($args),
                'obtener_orden_compra'         => $this->obtenerOrdenCompra($args),
                'completar_orden_compra'       => $this->completarOrdenCompra($args),
                'cancelar_orden_compra'        => $this->cancelarOrdenCompra($args),
                'actualizar_orden_compra'      => $this->actualizarOrdenCompra($args),
                'obtener_estadisticas_compras' => $this->obtenerEstadisticasCompras($args),

                // MODULO 6: CANALES (3 tools)
                'listar_canales'               => $this->listarCanales($args),
                'crear_canal'                  => $this->crearCanal($args),
                'actualizar_canal'             => $this->actualizarCanal($args),

                // MODULO 7: KARDEX (3 tools)
                'consultar_kardex'             => $this->consultarKardex($args),
                'listar_movimientos'           => $this->listarMovimientos($args),
                'obtener_resumen_kardex'       => $this->obtenerResumenKardex($args),

                // MODULO 8: REPORTES (5 tools)
                'obtener_estadisticas_generales' => $this->obtenerEstadisticasGenerales($args),
                'obtener_ventas_por_periodo'   => $this->obtenerVentasPorPeriodo($args),
                'obtener_top_productos'        => $this->obtenerTopProductos($args),
                'obtener_top_clientes'         => $this->obtenerTopClientes($args),
                'obtener_reporte_inventario'   => $this->obtenerReporteInventario($args),

                // MODULO 9: NOTIFICACIONES (3 tools)
                'listar_alertas'               => $this->listarAlertas($args),
                'marcar_alerta_leida'          => $this->marcarAlertaLeida($args),
                'notificar_pedidos_por_expirar' => $this->notificarPedidosPorExpirar($args),

                // MODULO 10: UTILIDADES (4 tools)
                'solicitar_campo_faltante'     => $this->solicitarCampoFaltante($args),
                'confirmar_accion'             => $this->confirmarAccion($args),
                'buscar_global'                => $this->buscarGlobal($args),
                'obtener_ayuda'                => $this->obtenerAyuda($args),

                // MODULO 11: BI CONVERSACIONAL (6 tools)
                'listar_graficos_disponibles'  => $this->listarGraficosDisponibles($args),
                'generar_grafico_dashboard'    => $this->chartGeneratorService->generar($args),
                'consultar_kpi'                => $this->chartGeneratorService->consultarKpi($args),
                'generar_reporte_periodo'      => $this->chartGeneratorService->generarReportePeriodo($args),
                'comparar_periodos'            => $this->chartGeneratorService->compararPeriodos($args),
                'renderizar_tabla'             => $this->chartGeneratorService->renderizarTabla($args),

                // MODULO 12: PREDICCIONES DE REABASTECIMIENTO (1 tool)
                'obtener_predicciones_compra'  => app(\App\Services\PurchasePredictionService::class)
                                                     ->obtenerPrediccionesParaChatbot($args),

                default => [
                    'success' => false,
                    'data'    => null,
                    'message' => "Tool '{$toolName}' no reconocida por el sistema.",
                ],
            };
        } catch (QueryException $e) {
            Log::error("GeminiToolsService DB Query Error [{$toolName}]", [
                'error' => $e->getMessage(),
                'sql'   => $e->getSql(),
            ]);
            return [
                'success'    => false,
                'status'     => 'db_error',
                'data'       => null,
                'message'    => 'Lo siento, hubo un problema al consultar la base de datos en este momento. Por favor, intenta nuevamente.',
                'error_code' => 'DB_QUERY_ERROR',
            ];
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->flatten()->implode(' | ');
            return ['success' => false, 'data' => null, 'message' => "Validación: {$errors}"];
        } catch (\Throwable $e) {
            Log::error("GeminiToolsService::dispatch [{$toolName}]", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'data'    => null,
                'message' => 'No fue posible completar la consulta en este momento. Por favor, intenta más tarde.',
            ];
        }
    }

    // =========================================================================
    // MODULO 1: PEDIDOS
    // =========================================================================

    /**
     * Resuelve de forma segura y cacheada la unidad de medida, factor de conversión y precio
     * para un producto dado, devolviendo arrays primitivos para evitar errores de deserialización.
     */
    private function resolverDetalleProductoMedida(string $prodId, ?string $unidadId, ?float $factor, ?float $precio): array
    {
        $cacheProdKey = "ai_prod_medidas:{$prodId}";
        $medidas = Cache::remember($cacheProdKey, now()->addMinutes(10), function () use ($prodId) {
            $p = Producto::with(['detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
            }])->where('ProductoId', $prodId)->first();
            return $p ? self::toPlainArray($p->detalleProductoMedidas) : [];
        });

        $unidadAbrev = 'UND';
        $unidadDesc = 'Unidades';

        if (!empty($medidas)) {
            $matchedMedida = null;
            $medidasCol = collect($medidas);

            // 1. Coincidencia directa por ID de unidad
            if (!empty($unidadId) && str_starts_with($unidadId, 'UND-')) {
                $matchedMedida = $medidasCol->first(fn($m) => data_get($m, 'Detalle_Producto_medida_unidades_medidaId') === $unidadId);
            }

            // 2. Extraer factor numérico del texto si no se especificó (ej: "paquete x 6" -> 6, "caja x 24" -> 24)
            if ($factor === null && !empty($unidadId) && preg_match('/\d+/', $unidadId, $matches)) {
                $factor = (float)$matches[0];
            }

            // 3. Coincidencia por factor de conversión exacto (ej: factor 6, factor 24)
            if (!$matchedMedida && $factor !== null && $factor > 1) {
                $matchedMedida = $medidasCol->first(fn($m) => (int)data_get($m, 'Detalle_Producto_medida_factor_conversion') === (int)$factor);
            }

            // 4. Coincidencia por texto en descripción o abreviatura de la unidad
            if (!$matchedMedida && !empty($unidadId)) {
                $unidadLower = strtolower($unidadId);
                $matchedMedida = $medidasCol->first(function ($m) use ($unidadLower) {
                    $desc = strtolower((string)data_get($m, 'unidad_medida.unidades_medidaDescripcionUnidades'));
                    $abrev = strtolower((string)data_get($m, 'unidad_medida.unidades_medidaAbreviatura'));
                    return str_contains($desc, $unidadLower) || str_contains($abrev, $unidadLower);
                });
                if (!$matchedMedida && (str_contains($unidadLower, 'caja') || str_contains($unidadLower, 'paquete') || str_contains($unidadLower, 'docena'))) {
                    $matchedMedida = $medidasCol->first(fn($m) => (int)data_get($m, 'Detalle_Producto_medida_factor_conversion') > 1);
                }
            }

            $precioCompra = 0.0;
            if ($matchedMedida) {
                $unidadId = data_get($matchedMedida, 'Detalle_Producto_medida_unidades_medidaId');
                $factor = (float)data_get($matchedMedida, 'Detalle_Producto_medida_factor_conversion');
                $unidadAbrev = data_get($matchedMedida, 'unidad_medida.unidades_medidaAbreviatura') ?? 'UND';
                $unidadDesc = data_get($matchedMedida, 'unidad_medida.unidades_medidaDescripcionUnidades') ?? 'Presentación';
                if (empty($precio) || $precio <= 0) {
                    $precio = (float)data_get($matchedMedida, 'Detalle_Producto_medida_precio_venta');
                }
                $precioCompra = (float)data_get($matchedMedida, 'Detalle_Producto_medida_precio_compra');
            } else {
                // Presentación base (factor 1)
                $baseMedida = $medidasCol->first(fn($m) => (int)data_get($m, 'Detalle_Producto_medida_factor_conversion') === 1) ?? $medidasCol->first();
                if ($baseMedida) {
                    if (empty($unidadId)) $unidadId = data_get($baseMedida, 'Detalle_Producto_medida_unidades_medidaId');
                    if (empty($factor)) $factor = (float)data_get($baseMedida, 'Detalle_Producto_medida_factor_conversion');
                    $unidadAbrev = data_get($baseMedida, 'unidad_medida.unidades_medidaAbreviatura') ?? 'UND';
                    $unidadDesc = data_get($baseMedida, 'unidad_medida.unidades_medidaDescripcionUnidades') ?? 'Unidades';
                    if (empty($precio) || $precio <= 0) $precio = (float)data_get($baseMedida, 'Detalle_Producto_medida_precio_venta');
                    $precioCompra = (float)data_get($baseMedida, 'Detalle_Producto_medida_precio_compra');
                }
            }
        }

        return [
            'unidad_id'          => $unidadId ?: 'UND-00001',
            'unidad_abreviatura' => $unidadAbrev ?: 'UND',
            'unidad_descripcion' => $unidadDesc ?: 'Unidades',
            'factor_conversion'  => $factor ?: 1.0,
            'precio_unitario'    => $precio ?: 0.0,
            'precio_compra'      => $precioCompra ?? 0.0,
        ];
    }

    /**
     * Resuelve un producto por ID o término de búsqueda, detectando ambigüedades.
     * Si hay múltiples candidatos válidos (ej: 2 productos con la misma marca y presentación),
     * devuelve status 'ambiguous' con la lista de candidatos y chips de respuesta rápida.
     */
    private function resolverProductoOAmbiguedad(
        string $prodIdent,
        ?string $unidadOTexto = null,
        ?float $factorRequerido = null,
        ?float $precioRequerido = null,
        string $modo = 'venta'
    ): array {
        $prodIdent = trim($prodIdent);

        // 1. Si ya es un código de producto exacto PROD-XXXXX
        if (str_starts_with($prodIdent, 'PROD-')) {
            $producto = Producto::with(['categoria', 'detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
            }])->where('ProductoId', $prodIdent)->where('ProductoEliminado', 'N')->first();

            if ($producto) {
                return [
                    'status'   => 'resolved',
                    'producto' => $producto,
                ];
            }
        }

        // 2. Extraer factor de conversión del término o unidad si no vino explícito
        if ($factorRequerido === null) {
            $textoParaFactor = "{$prodIdent} {$unidadOTexto}";
            if (preg_match('/(?:x|\*|paquete|pack|caja)\s*(\d+)/i', $textoParaFactor, $m)) {
                $factorRequerido = (float)$m[1];
            }
        }

        // Limpiar término de búsqueda para la consulta SQL (quitar "x 6", "pack 6", etc. para buscar el nombre base)
        $cleanSearchTerm = preg_replace('/(?:\*|x)\s*\d+/i', '', $prodIdent);
        $cleanSearchTerm = preg_replace('/\b(?:pack|paquete|caja|bolsa|fardo)\b/i', '', $cleanSearchTerm);
        $cleanSearchTerm = trim($cleanSearchTerm);
        if (strlen($cleanSearchTerm) < 2) {
            $cleanSearchTerm = $prodIdent;
        }

        // 3. Buscar productos candidatos
        $candidatos = $this->inventarioService->buscarProductosRapido($cleanSearchTerm, 10);
        if (empty($candidatos)) {
            $candidatos = $this->inventarioService->buscarProductosRapido($prodIdent, 10);
        }

        if (empty($candidatos)) {
            return [
                'status'  => 'not_found',
                'message' => "No se encontró ningún producto que coincida con '{$prodIdent}'.",
            ];
        }

        // 4. Si hay más de 1 producto encontrado, filtrar si es posible por factor de presentación
        if (count($candidatos) > 1 && $factorRequerido !== null && $factorRequerido > 1) {
            $candidatosConPresentacion = [];
            foreach ($candidatos as $c) {
                $medidas = $c['unidades_medida'] ?? [];
                foreach ($medidas as $um) {
                    if ((int)($um['factor_conversion'] ?? 0) === (int)$factorRequerido) {
                        $candidatosConPresentacion[] = $c;
                        break;
                    }
                }
            }
            if (!empty($candidatosConPresentacion)) {
                $candidatos = $candidatosConPresentacion;
            }
        }

        // 5. Si exactamente 1 producto coincide, resolverlo directamente
        if (count($candidatos) === 1) {
            $pid = $candidatos[0]['producto_id'];
            $producto = Producto::with(['categoria', 'detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
            }])->where('ProductoId', $pid)->where('ProductoEliminado', 'N')->first();

            if ($producto) {
                return [
                    'status'   => 'resolved',
                    'producto' => $producto,
                ];
            }
        }

        // 6. Si hay 2 o más productos que coinciden -> ¡AMBIGÜEDAD DETECTADA!
        $opcionesTexto = [];
        $chips = [];
        $candidatosData = [];

        foreach ($candidatos as $idx => $cand) {
            $pid = $cand['producto_id'];
            $pnom = $cand['nombre'];
            $pmarca = $cand['marca'] ?? '';

            $pres = null;
            if (!empty($cand['unidades_medida'])) {
                if ($factorRequerido !== null) {
                    foreach ($cand['unidades_medida'] as $um) {
                        if ((int)($um['factor_conversion'] ?? 0) === (int)$factorRequerido) {
                            $pres = $um;
                            break;
                        }
                    }
                }
                if (!$pres) {
                    $pres = collect($cand['unidades_medida'])->first(fn($u) => (int)($u['factor_conversion'] ?? 0) > 1)
                        ?? ($cand['unidades_medida'][0] ?? null);
                }
            }

            $presDesc = $pres['descripcion'] ?? 'Unidad';
            $precio = ($modo === 'compra')
                ? (float)($pres['precio_compra'] ?? 0)
                : (float)($pres['precio_venta'] ?? 0);

            $num = $idx + 1;
            $opcionesTexto[] = "{$num}. **{$pnom}** ({$presDesc} a S/ " . number_format($precio, 2) . ")";

            $chips[] = [
                'label' => "{$pnom} ({$presDesc} - S/ " . number_format($precio, 2) . ")",
                'query' => "Quiero {$pnom} ({$presDesc})",
            ];

            $candidatosData[] = [
                'producto_id'  => $pid,
                'nombre'       => $pnom,
                'marca'        => $pmarca,
                'presentacion' => $presDesc,
                'factor'       => $pres['factor_conversion'] ?? 1,
                'precio'       => $precio,
            ];
        }

        $presTexto = ($factorRequerido !== null && $factorRequerido > 1) ? " con presentación de " . (int)$factorRequerido . " unidades" : "";
        $pregunta = "Encontré " . count($candidatos) . " productos que coinciden con '{$prodIdent}'{$presTexto}:\n" .
            implode("\n", $opcionesTexto) . "\n\n" .
            "¿A cuál de los dos te refieres para agregarlo al pedido?";

        return [
            'status'     => 'ambiguous',
            'termino'    => $prodIdent,
            'candidatos' => $candidatosData,
            'chips'      => $chips,
            'message'    => $pregunta,
        ];
    }

    private function crearPedidoCliente(array $args, ?array $liveDraft = null, ?string $userMessage = null, ?string $sessionId = null): array
    {
        $clienteId = $args['cliente_id'] ?? $args['cliente'] ?? null;
        if (empty($clienteId) && !empty($liveDraft['cliente_id'])) {
            $clienteId = $liveDraft['cliente_id'];
        }

        $clienteNoEncontrado = false;
        $nombreClienteBuscado = $clienteId;

        // Si el clienteId no viene en formato de ID oficial (CLI-XXXXX), resolverlo por nombre o desde liveDraft
        if (!empty($clienteId) && !str_starts_with($clienteId, 'CLI-')) {
            if (!empty($liveDraft['cliente_id']) && str_starts_with($liveDraft['cliente_id'], 'CLI-')) {
                $clienteId = $liveDraft['cliente_id'];
            } elseif ($this->esClienteGenerico($clienteId)) {
                $clienteId = 'CLI-00011';
            } else {
                $encontrados = $this->clienteService->buscarClientesRapido($clienteId, 1);
                if ($this->tieneResultados($encontrados)) {
                    $first = collect($encontrados)->first();
                    $foundId = is_array($first) ? ($first['cliente_id'] ?? $first['ClienteId']) : $first->ClienteId;
                    if ($foundId === 'CLI-00011' && !$this->esClienteGenerico($clienteId)) {
                        $clienteNoEncontrado = true;
                    } else {
                        $clienteId = $foundId;
                    }
                } else {
                    $clienteNoEncontrado = true;
                }
            }
        } elseif (empty($clienteId) && !empty($liveDraft['cliente_nombre'])) {
            $nombreClienteBuscado = $liveDraft['cliente_nombre'];
            if ($this->esClienteGenerico($liveDraft['cliente_nombre'])) {
                $clienteId = 'CLI-00011';
            } else {
                $encontrados = $this->clienteService->buscarClientesRapido($liveDraft['cliente_nombre'], 1);
                if ($this->tieneResultados($encontrados)) {
                    $first = collect($encontrados)->first();
                    $foundId = is_array($first) ? ($first['cliente_id'] ?? $first['ClienteId']) : $first->ClienteId;
                    if ($foundId === 'CLI-00011' && !$this->esClienteGenerico($liveDraft['cliente_nombre'])) {
                        $clienteNoEncontrado = true;
                    } else {
                        $clienteId = $foundId;
                    }
                } else {
                    $clienteNoEncontrado = true;
                }
            }
        }

        $detalles = $args['detalles'] ?? $args['productos'] ?? [];

        // Sincronización en vivo con la pantalla activa del usuario (liveDraft)
        if (!empty($liveDraft) && ($liveDraft['tipo'] ?? '') === 'pedido_cliente' && !empty($liveDraft['detalles'])) {
            if (empty($detalles)) {
                $detalles = $liveDraft['detalles'];
            } else {
                $liveMap = [];
                $liveMapByName = [];
                foreach ($liveDraft['detalles'] as $ld) {
                    $pid = $ld['producto_id'] ?? $ld['id'] ?? null;
                    $pnom = strtolower(trim($ld['nombre'] ?? $ld['producto_nombre'] ?? ''));
                    if ($pid) $liveMap[$pid] = $ld;
                    if ($pnom) $liveMapByName[$pnom] = $ld;
                }

                // Sincronizar cantidades y unidades con lo que está en pantalla
                foreach ($detalles as &$d) {
                    $pid = $d['producto_id'] ?? $d['id'] ?? null;
                    $matchedLd = null;
                    if ($pid && isset($liveMap[$pid])) {
                        $matchedLd = $liveMap[$pid];
                    } else {
                        $term = strtolower(trim((string)($pid ?? $d['nombre'] ?? '')));
                        if ($term && isset($liveMapByName[$term])) {
                            $matchedLd = $liveMapByName[$term];
                        }
                    }

                    if ($matchedLd) {
                        if (!empty($matchedLd['cantidad'])) {
                            $d['cantidad'] = $matchedLd['cantidad'];
                        }
                        if (!empty($matchedLd['unidad_id']) && empty($d['unidad_id'])) {
                            $d['unidad_id'] = $matchedLd['unidad_id'];
                        }
                        if (!empty($matchedLd['precio_unitario']) && empty($d['precio_unitario'])) {
                            $d['precio_unitario'] = $matchedLd['precio_unitario'];
                        }
                    }
                }
                unset($d);

                // Agregar productos presentes en pantalla que Gemini haya omitido
                $existingKeys = [];
                foreach ($detalles as $d) {
                    $pid = $d['producto_id'] ?? $d['id'] ?? null;
                    if ($pid) $existingKeys[$pid] = true;
                    $pnom = strtolower(trim($d['nombre'] ?? ''));
                    if ($pnom) $existingKeys[$pnom] = true;
                }

                foreach ($liveDraft['detalles'] as $ld) {
                    $pid = $ld['producto_id'] ?? $ld['id'] ?? null;
                    $pnom = strtolower(trim($ld['nombre'] ?? $ld['producto_nombre'] ?? ''));
                    $found = ($pid && isset($existingKeys[$pid])) || ($pnom && isset($existingKeys[$pnom]));
                    if (!$found) {
                        $detalles[] = $ld;
                        if ($pid) $existingKeys[$pid] = true;
                    }
                }
            }
        }

        if (empty($args['canal_id']) && !empty($liveDraft['canal_id'])) {
            $args['canal_id'] = $liveDraft['canal_id'];
        }
        if (empty($args['acuerdo_comercial']) && !empty($liveDraft['acuerdo_comercial'])) {
            $args['acuerdo_comercial'] = $liveDraft['acuerdo_comercial'];
        }

        $faltantes = [];
        if (empty($clienteId)) $faltantes[] = 'cliente_id';
        if (empty($detalles) || !is_array($detalles)) $faltantes[] = 'detalles (productos a pedir)';

        if (!empty($faltantes)) {
            return [
                'success'          => false,
                'status'           => 'missing_fields',
                'campos_faltantes' => $faltantes,
                'data'             => null,
                'message'          => 'Para registrar el pedido faltan los campos: ' . implode(', ', $faltantes),
            ];
        }

        // Si el cliente no existe en el sistema y no es genérico, derivar a apertura con retención de borrador
        if ($clienteNoEncontrado) {
            return $this->abrirFormularioPedido([
                'cliente_nombre'    => $nombreClienteBuscado,
                'detalles'          => $detalles,
                'canal_id'          => $args['canal_id'] ?? null,
                'acuerdo_comercial' => $args['acuerdo_comercial'] ?? null,
            ], $sessionId);
        }

        // VALIDACIÓN ESTRICTA DE CONFIRMACIÓN DEL USUARIO:
        // Evita crear pedidos accidentales ante audios como "El pedido.", dudas o errores.
        // Solo se persiste en BD si el argumento trae 'confirmado' => true o el mensaje del usuario expresa confirmación inequívoca.
        $estaConfirmado = ($args['confirmado'] ?? false) === true || $this->tieneConfirmacionExplicita($userMessage);
        if (!$estaConfirmado) {
            Log::info("Valencia AI: crear_pedido_cliente interceptado sin confirmación explícita. Mostrando borrador en pantalla.", [
                'userMessage' => $userMessage,
                'clienteId'   => $clienteId,
            ]);

            $resApertura = $this->abrirFormularioPedido([
                'cliente_id'        => $clienteId,
                'detalles'          => $detalles,
                'canal_id'          => $args['canal_id'] ?? null,
                'acuerdo_comercial' => $args['acuerdo_comercial'] ?? null,
            ], $sessionId);

            if (($resApertura['status'] ?? '') === 'client_not_found') {
                return $resApertura;
            }

            $numProds = count($detalles);
            $resApertura['message'] = "He preparado el borrador del pedido en pantalla con {$numProds} producto(s). Por favor, revísalo y confírmame si deseas guardarlo en el sistema (responde 'Sí' o 'Confirmo').";
            $resApertura['requiere_confirmacion'] = true;
            return $resApertura;
        }

        // Normalizar cada detalle de productos
        $detallesNormalizados = [];
        foreach ($detalles as $item) {
            $prodId = trim((string)($item['producto_id'] ?? $item['productoId'] ?? $item['id'] ?? ''));
            $cantidad = (float)($item['cantidad'] ?? $item['qty'] ?? 0);
            $unidadId = trim((string)($item['unidad_id'] ?? $item['unidades_medidaId'] ?? $item['unidad'] ?? ''));
            $factor = !empty($item['factor_conversion']) ? (float)$item['factor_conversion'] : null;
            $precio = !empty($item['precio_unitario']) ? (float)$item['precio_unitario'] : (!empty($item['precio']) ? (float)$item['precio'] : null);

            // Resolver producto detectando ambigüedades
            $resProd = $this->resolverProductoOAmbiguedad($prodId, $unidadId, $factor, $precio, 'venta');
            if ($resProd['status'] === 'ambiguous') {
                return [
                    'success'                => true,
                    'status'                 => 'ambiguous_product',
                    'termino'                => $resProd['termino'],
                    'productos_coincidentes' => $resProd['candidatos'],
                    'chips'                  => $resProd['chips'],
                    'data'                   => [
                        'termino'    => $resProd['termino'],
                        'candidatos' => $resProd['candidatos'],
                        'chips'      => $resProd['chips'],
                    ],
                    'message'                => $resProd['message'],
                ];
            }
            if ($resProd['status'] === 'not_found') {
                return [
                    'success' => false,
                    'status'  => 'not_found',
                    'data'    => null,
                    'message' => $resProd['message'],
                ];
            }
            $producto = $resProd['producto'];
            $prodId = $producto->ProductoId;

            // Resolver presentación en DetalleProductoMedida
            $resMedida = $this->resolverDetalleProductoMedida($prodId, $unidadId, $factor, $precio);

            $detallesNormalizados[] = [
                'producto_id'       => $prodId,
                'cantidad'          => $cantidad,
                'unidad_id'         => $resMedida['unidad_id'],
                'factor_conversion' => $resMedida['factor_conversion'],
                'precio_unitario'   => $resMedida['precio_unitario'],
            ];
        }

        $payload = array_merge($args, [
            'cliente_id' => $clienteId,
            'detalles'   => $detallesNormalizados,
        ]);

        $pedido = $this->pedidoService->crearPedido($payload);

        $detallesResumen = $pedido->detalles->map(function ($d) {
            $nomProd = $d->producto?->ProductoNombre ?? $d->Detalle_Pedido_Productos_ProductoId;
            $nomPres = $d->unidadMedida?->unidades_medidaDescripcionUnidades ?? $d->Detalle_Pedido_Productos_unidades_medidaId;
            $cant = (float)$d->Detalle_Pedido_Productos_cantidad;
            $factor = (int)$d->Detalle_Pedido_Productos_factor_conversion;
            $cantBase = (float)$d->Detalle_Pedido_Productos_cantidad_base;
            $precio = (float)$d->Detalle_Pedido_Productos_precio_unitario_venta;
            $subtotal = (float)$d->Detalle_Pedido_Productos_subtotal;

            return [
                'producto'          => $nomProd,
                'cantidad'          => $cant,
                'presentacion'      => $nomPres,
                'factor_conversion' => $factor,
                'unidades_totales'  => $cantBase,
                'precio_unitario'   => $precio,
                'subtotal'          => $subtotal,
            ];
        })->toArray();

        $nombreCliente = $pedido->cliente?->ClienteNombre ?? $clienteId;

        return [
            'success'   => true,
            'ui_action' => [
                'type'      => 'order_created',
                'pedido_id' => $pedido->PedidoId,
                'total'     => (float)$pedido->PedidoTotal,
            ],
            'data'    => [
                'pedido_id'        => $pedido->PedidoId,
                'estado'           => $pedido->PedidoEstado_pedido ?? 'P',
                'cliente'          => $nombreCliente,
                'total'            => (float)$pedido->PedidoTotal,
                'igv'              => (float)$pedido->PedidoIgv,
                'fecha'            => $pedido->PedidoFecha_pedido,
                'items_ordenados'  => $detallesResumen,
            ],
            'message' => "Pedido {$pedido->PedidoId} creado exitosamente para {$nombreCliente}. Total: S/ " . number_format($pedido->PedidoTotal, 2) . ".",
        ];
    }

    /**
     * Prepara y abre el formulario de Nuevo Pedido en la interfaz con el cliente y productos precargados.
     * Esta es la herramienta Copiloto por defecto: NO guarda en base de datos, NO consume stock.
     */
    private function abrirFormularioPedido(array $args, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: 'sess_fb_' . strtolower(\Illuminate\Support\Str::random(12));

        // 1. Resolver cliente
        $clienteId     = $args['cliente_id'] ?? null;
        $clienteNombre = $args['cliente_nombre'] ?? null;
        $cliente       = null;
        $clienteNoRegistrado = false;

        if (!empty($clienteId) && str_starts_with($clienteId, 'CLI-')) {
            $cliente = \App\Models\Cliente::where('ClienteId', $clienteId)->where('ClienteEliminado', 'N')->first();
        }

        if (!$cliente && (!empty($clienteId) || !empty($clienteNombre))) {
            $termino   = $clienteId ?: $clienteNombre;
            $encontrados = $this->clienteService->buscarClientesRapido($termino, 1);

            if ($this->tieneResultados($encontrados)) {
                $first   = collect($encontrados)->first();
                $foundId = is_array($first) ? ($first['cliente_id'] ?? $first['ClienteId']) : $first->ClienteId;
                $foundClient = \App\Models\Cliente::where('ClienteId', $foundId)->first();

                // Si el cliente encontrado es genérico (CLI-00011 / Cliente General), pero el término buscado
                // NO era genérico (ej: "Alex", "Josue"), se trata de un falso positivo -> tratar como no registrado.
                if ($foundClient && ($foundClient->ClienteId === 'CLI-00011' || $this->esClienteGenerico($foundClient->ClienteNombre)) && !$this->esClienteGenerico($termino)) {
                    $cliente = null;
                    $clienteNoRegistrado = true;
                } else {
                    $cliente = $foundClient;
                }
            } elseif (!$this->esClienteGenerico($termino)) {
                // Cliente buscado no existe y no es cliente genérico → retener pedido pendiente
                $clienteNoRegistrado = true;
            }
        }

        // 2. Si el cliente no existe y no es genérico, resolver productos primero y guardar draft
        if ($clienteNoRegistrado) {
            $detallesRaw = $args['detalles'] ?? [];
            if (empty($detallesRaw) || !is_array($detallesRaw)) {
                return [
                    'success'          => false,
                    'status'           => 'missing_fields',
                    'campos_faltantes' => ['detalles (productos a pedir)'],
                    'data'             => null,
                    'message'          => 'Indica al menos un producto y cantidad para preparar el pedido en pantalla.',
                ];
            }

            $productosResueltos = [];
            foreach ($detallesRaw as $item) {
                $prodId  = trim((string)($item['producto_id'] ?? $item['termino'] ?? $item['nombre'] ?? ''));
                $cant    = (float)($item['cantidad'] ?? 1);
                $unidadId = $item['unidad_id'] ?? null;
                $factor   = !empty($item['factor_conversion']) ? (float)$item['factor_conversion'] : null;
                $precio   = !empty($item['precio_unitario']) ? (float)$item['precio_unitario'] : null;
                if (empty($prodId)) continue;

                $resProd = $this->resolverProductoOAmbiguedad($prodId, $unidadId, $factor, $precio, 'venta');
                if ($resProd['status'] === 'ambiguous') {
                    return [
                        'success'                => true,
                        'status'                 => 'ambiguous_product',
                        'termino'                => $resProd['termino'],
                        'productos_coincidentes' => $resProd['candidatos'],
                        'chips'                  => $resProd['chips'],
                        'data'                   => ['termino' => $resProd['termino'], 'candidatos' => $resProd['candidatos'], 'chips' => $resProd['chips']],
                        'message'                => $resProd['message'],
                    ];
                }
                if ($resProd['status'] === 'not_found') {
                    return ['success' => false, 'status' => 'not_found', 'data' => null, 'message' => $resProd['message']];
                }

                $producto   = $resProd['producto'];
                $resMedida  = $this->resolverDetalleProductoMedida($producto->ProductoId, $unidadId, $factor, $precio);
                $factorFinal = $resMedida['factor_conversion'];
                $precioUnitario = $resMedida['precio_unitario'] > 0 ? $resMedida['precio_unitario'] : (float)($producto->ProductoPrecioVenta ?? 0);
                $subtotal = round($cant * $precioUnitario, 2);

                $productosResueltos[] = [
                    'producto_id'       => $producto->ProductoId,
                    'producto_nombre'   => $producto->ProductoNombre,
                    'unidad_id'         => $resMedida['unidad_id'],
                    'unidad_abreviatura'=> $resMedida['unidad_abreviatura'],
                    'factor_conversion' => $factorFinal,
                    'precio_unitario'   => $precioUnitario,
                    'cantidad'          => $cant,
                    'subtotal'          => $subtotal,
                ];
            }

            if (empty($productosResueltos)) {
                return ['success' => false, 'data' => null, 'message' => 'No se encontraron productos disponibles con los datos especificados.'];
            }

            $subtotalGeneral = array_sum(array_column($productosResueltos, 'subtotal'));
            $igvGeneral      = round($subtotalGeneral * 0.18, 2);
            $totalGeneral    = round($subtotalGeneral + $igvGeneral, 2);

            // Idempotencia: si ya existe draft para esta sesión + mismo cliente + mismos productos → reutilizar draft_id
            $draftKey = $this->draftCacheKey($sessionId);
            $existingDraft = Cache::get($draftKey);
            $nombreBuscado = $clienteNombre ?: $clienteId;

            $sameClient   = isset($existingDraft['cliente_nombre_buscado']) && $existingDraft['cliente_nombre_buscado'] === $nombreBuscado;
            $sameProducts = isset($existingDraft['productos']) && collect($existingDraft['productos'])->pluck('producto_id')->sort()->values()->toArray()
                === collect($productosResueltos)->pluck('producto_id')->sort()->values()->toArray();

            if ($existingDraft && $sameClient && $sameProducts) {
                $draftId = $existingDraft['draft_id'];
            } else {
                $draftId = 'drf-' . strtolower(\Illuminate\Support\Str::random(12));
            }

            $now = now();
            $draft = [
                'draft_id'              => $draftId,
                'session_id'            => $sessionId,
                'cliente_nombre_buscado'=> $nombreBuscado,
                'productos'             => $productosResueltos,
                'acuerdo_comercial'     => $args['acuerdo_comercial'] ?? 'Contado Mostrador',
                'canal_id'              => $args['canal_id'] ?? 'CNL-00001',
                'totales'               => ['subtotal' => $subtotalGeneral, 'igv' => $igvGeneral, 'total' => $totalGeneral],
                'created_at'            => $existingDraft['created_at'] ?? $now->toIso8601String(),
                'updated_at'            => $now->toIso8601String(),
                'expires_at'            => $now->addMinutes(30)->toIso8601String(),
            ];

            Cache::put($draftKey, $draft, now()->addMinutes(30));

            Log::info('Valencia AI: Borrador de pedido pendiente guardado', ['session_id' => $sessionId, 'draft_id' => $draftId, 'cliente' => $nombreBuscado]);

            return [
                'success'    => true,
                'status'     => 'client_not_found',
                'ui_action'  => [
                    'type'          => 'client_not_found',
                    'pending_order' => $draft,
                ],
                'chips'      => [
                    ['label' => '📋 Registrar a ' . $nombreBuscado, 'query' => "Registra al cliente {$nombreBuscado}", 'metadata' => ['draft_id' => $draftId, 'intent' => 'crear_cliente']],
                    ['label' => '✏️ Abrir formulario de cliente', 'query' => "Abre el formulario para registrar a {$nombreBuscado}", 'metadata' => ['draft_id' => $draftId, 'intent' => 'abrir_formulario_cliente']],
                    ['label' => '🗑️ Descartar pedido', 'query' => 'Descarta el pedido pendiente', 'metadata' => ['draft_id' => $draftId, 'intent' => 'limpiar_formulario_pedido']],
                ],
                'data'       => ['cliente_buscado' => $nombreBuscado, 'productos_count' => count($productosResueltos), 'total' => $totalGeneral],
                'message'    => "No encontré al cliente '{$nombreBuscado}' en el sistema. He guardado el pedido pendiente de " . count($productosResueltos) . " producto(s) por S/ " . number_format($totalGeneral, 2) . ". ¿Deseas registrarlo como nuevo cliente para continuar?",
            ];
        }

        // 3. Asignar datos de cliente (existente o cliente general)
        $clienteData = [
            'id'       => $cliente?->ClienteId ?? ($clienteId ?: 'CLI-00011'),
            'nombre'   => $cliente?->ClienteNombre ?? ($clienteNombre ?: 'CLIENTE GENERAL / VENTA AL POR MENOR'),
            'dni_ruc'  => $cliente?->ClienteRuc ?? '00000000000',
            'telefono' => $cliente?->ClienteNumero ?? '000000000',
            'direccion'=> $cliente?->ClienteDireccion ?? 'Venta en Mostrador',
        ];

        // 4. Resolver detalles de productos
        $detallesRaw = $args['detalles'] ?? [];
        if (empty($detallesRaw) || !is_array($detallesRaw)) {
            return [
                'success'          => false,
                'status'           => 'missing_fields',
                'campos_faltantes' => ['detalles (productos a pedir)'],
                'data'             => null,
                'message'          => 'Indica al menos un producto y cantidad para preparar el pedido en pantalla.',
            ];
        }

        $productosResueltos = [];

        foreach ($detallesRaw as $item) {
            $prodId   = trim((string)($item['producto_id'] ?? $item['termino'] ?? $item['nombre'] ?? ''));
            $cant     = (float)($item['cantidad'] ?? 1);
            $unidadId = $item['unidad_id'] ?? null;
            $factor   = !empty($item['factor_conversion']) ? (float)$item['factor_conversion'] : null;
            $precio   = !empty($item['precio_unitario']) ? (float)$item['precio_unitario'] : null;

            if (empty($prodId)) continue;

            $resProd = $this->resolverProductoOAmbiguedad($prodId, $unidadId, $factor, $precio, 'venta');
            if ($resProd['status'] === 'ambiguous') {
                return [
                    'success'                => true,
                    'status'                 => 'ambiguous_product',
                    'termino'                => $resProd['termino'],
                    'productos_coincidentes' => $resProd['candidatos'],
                    'chips'                  => $resProd['chips'],
                    'data'                   => [
                        'termino'    => $resProd['termino'],
                        'candidatos' => $resProd['candidatos'],
                        'chips'      => $resProd['chips'],
                    ],
                    'message'                => $resProd['message'],
                ];
            }
            if ($resProd['status'] === 'not_found') {
                return [
                    'success' => false,
                    'status'  => 'not_found',
                    'data'    => null,
                    'message' => $resProd['message'],
                ];
            }

            $producto = $resProd['producto'];

            $resMedida      = $this->resolverDetalleProductoMedida($producto->ProductoId, $unidadId, $factor, $precio);
            $factor         = $resMedida['factor_conversion'];
            $precioUnitario = $resMedida['precio_unitario'] > 0 ? $resMedida['precio_unitario'] : (float)($producto->ProductoPrecioVenta ?? 0);

            $stockFisico           = max(0.0, (float)$producto->ProductoStockActual);
            $stockVirtual          = (float)($producto->ProductoStockVirtual ?? 50);
            $stockVirtualConsumido = (float)($producto->ProductoStockVirtualConsumido ?? 0);
            $stockVirtualDisp      = max(0.0, $stockVirtual - $stockVirtualConsumido);
            $stockTotal            = $stockFisico + $stockVirtualDisp;

            $totalEnUnidadBase = round($cant * $factor, 2);
            $cantFisica        = min($totalEnUnidadBase, $stockFisico);
            $cantVirtual       = max(0.0, round($totalEnUnidadBase - $cantFisica, 2));
            $subtotal          = round($cant * $precioUnitario, 2);

            $productosResueltos[] = [
                'producto_id'               => $producto->ProductoId,
                'producto_nombre'           => $producto->ProductoNombre,
                'producto_marca'            => $producto->ProductoMarca ?: 'Genérico',
                'categoria_nombre'          => $producto->categoria?->Categoria_productoNombre ?? '',
                'unidad_id'                 => $resMedida['unidad_id'],
                'unidad_abreviatura'        => $resMedida['unidad_abreviatura'],
                'unidad_descripcion'        => $resMedida['unidad_descripcion'],
                'factor_conversion'         => $factor,
                'stock_fisico'              => $stockFisico,
                'stock_virtual_disponible'  => $stockVirtualDisp,
                'stock_total'               => $stockTotal,
                'cantidad_fisica_estimada'  => $cantFisica,
                'cantidad_virtual_estimada' => $cantVirtual,
                'precio_unitario'           => $precioUnitario,
                'cantidad'                  => $cant,
                'subtotal'                  => $subtotal,
            ];
        }

        if (empty($productosResueltos)) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'No se encontraron productos disponibles con los datos especificados.',
            ];
        }

        $subtotalGeneral = array_sum(array_column($productosResueltos, 'subtotal'));
        $igvGeneral      = round($subtotalGeneral * 0.18, 2);
        $totalGeneral    = round($subtotalGeneral + $igvGeneral, 2);

        $acuerdoComercial = $args['acuerdo_comercial'] ?? 'Contado Mostrador';
        $canalId          = $args['canal_id'] ?? 'CNL-00001';

        return [
            'success'   => true,
            'ui_action' => [
                'type'    => 'open_order_form',
                'form'    => 'pedido',
                'prefill' => [
                    'cliente'           => $clienteData,
                    'productos'         => $productosResueltos,
                    'acuerdo_comercial' => $acuerdoComercial,
                    'canal_id'          => $canalId,
                    'totales'           => [
                        'subtotal' => $subtotalGeneral,
                        'igv'      => $igvGeneral,
                        'total'    => $totalGeneral,
                    ],
                ],
            ],
            'data'      => [
                'cliente'     => $clienteData['nombre'],
                'items_count' => count($productosResueltos),
                'subtotal'    => $subtotalGeneral,
                'igv'         => $igvGeneral,
                'total'       => $totalGeneral,
                'items'       => array_map(fn($p) => [
                    'producto'     => $p['producto_nombre'],
                    'cantidad'     => $p['cantidad'],
                    'presentacion' => $p['unidad_descripcion'] ?: $p['unidad_abreviatura'],
                    'precio'       => $p['precio_unitario'],
                    'subtotal'     => $p['subtotal'],
                ], $productosResueltos),
            ],
            'message'   => "Se ha cargado la orden para {$clienteData['nombre']} con " . count($productosResueltos) . " producto(s). Total estimado: S/ " . number_format($totalGeneral, 2) . ". La pantalla de Nuevo Pedido ha sido abierta para tu revisión y confirmación final.",
        ];
    }

    /**
     * Limpia y vacía el formulario de Nuevo Pedido en la interfaz cuando el usuario solicita descartar o vaciar el borrador.
     * Si hay borrador pendiente en caché, lo mueve a la papelera (TTL 10 min) para permitir "deshacer".
     */
    private function limpiarFormularioPedido(array $args, ?string $sessionId = null): array
    {
        if ($sessionId !== null) {
            $draftKey  = $this->draftCacheKey($sessionId);
            $trashKey  = $this->draftTrashCacheKey($sessionId);
            $draft     = Cache::get($draftKey);

            if ($draft) {
                Cache::put($trashKey, $draft, now()->addMinutes(10));
                Cache::forget($draftKey);
                Log::info('Valencia AI: Borrador movido a papelera', ['session_id' => $sessionId, 'draft_id' => $draft['draft_id'] ?? null]);
            }
        }

        return [
            'success'   => true,
            'ui_action' => [
                'type' => 'clear_order_form',
                'form' => 'pedido',
            ],
            'data'    => null,
            'message' => 'El formulario de nuevo pedido en pantalla ha sido limpiado y vaciado exitosamente.',
        ];
    }

    /**
     * Restaura un borrador de pedido desde la papelera (acción "deshacer").
     */
    private function recuperarBorradorPedido(array $args, ?string $sessionId = null): array
    {
        if ($sessionId === null) {
            return ['success' => false, 'data' => null, 'message' => 'No se pudo identificar la sesión para recuperar el borrador.'];
        }

        $trashKey = $this->draftTrashCacheKey($sessionId);
        $draft    = Cache::get($trashKey);

        if (!$draft) {
            return [
                'success' => false,
                'status'  => 'no_draft',
                'data'    => null,
                'message' => 'No hay ningún borrador reciente que pueda restaurarse.',
            ];
        }

        // Restaurar: mover de papelera a la clave activa
        $draftKey = $this->draftCacheKey($sessionId);
        $draft['updated_at'] = now()->toIso8601String();
        $draft['expires_at'] = now()->addMinutes(30)->toIso8601String();

        Cache::put($draftKey, $draft, now()->addMinutes(30));
        Cache::forget($trashKey);

        Log::info('Valencia AI: Borrador restaurado desde papelera', ['session_id' => $sessionId, 'draft_id' => $draft['draft_id'] ?? null]);

        return [
            'success'    => true,
            'ui_action'  => [
                'type'          => 'client_not_found',
                'pending_order' => $draft,
            ],
            'chips'      => [
                ['label' => '📋 Registrar a ' . ($draft['cliente_nombre_buscado'] ?? '?'), 'query' => 'Registra al cliente ' . ($draft['cliente_nombre_buscado'] ?? ''), 'metadata' => ['draft_id' => $draft['draft_id'], 'intent' => 'crear_cliente']],
                ['label' => '🗑️ Descartar pedido', 'query' => 'Descarta el pedido pendiente', 'metadata' => ['draft_id' => $draft['draft_id'], 'intent' => 'limpiar_formulario_pedido']],
            ],
            'data'       => $draft,
            'message'    => "Borrador restaurado. El pedido pendiente para '{$draft['cliente_nombre_buscado']}' está disponible nuevamente.",
        ];
    }

    private function listarPedidos(array $args): array
    {
        $filtros = array_filter([
            'estado'       => $args['estado'] ?? null,
            'cliente_id'   => $args['cliente_id'] ?? null,
            'fecha_desde'  => $args['fecha_desde'] ?? null,
            'fecha_hasta'  => $args['fecha_hasta'] ?? null,
            'canal_id'     => $args['canal_id'] ?? null,
            'search'       => $args['search'] ?? null,
        ]);
        $pedidos = $this->pedidoService->listarPedidos($filtros, $args['per_page'] ?? 15);
        $total = method_exists($pedidos, 'total') ? $pedidos->total() : count($pedidos);
        $items = method_exists($pedidos, 'items') ? $pedidos->items() : $pedidos;

        // Simplificar para evitar miles de tokens en relaciones anidadas
        $resumenItems = collect($items)->map(function ($p) {
            $estadoCod = is_array($p) ? ($p['PedidoEstado_pedido'] ?? $p['PedidoEstado'] ?? '?') : ($p->PedidoEstado_pedido ?? '?');
            $estadoNombre = match (strtoupper(trim($estadoCod))) {
                'P' => 'Pendiente',
                'C' => 'Completada',
                'A' => 'Cancelada / Anulada',
                default => $estadoCod,
            };
            return [
                'pedido_id' => is_array($p) ? $p['PedidoId'] : $p->PedidoId,
                'fecha'     => is_array($p) ? ($p['PedidoFecha_pedido'] ?? '') : ($p->PedidoFecha_pedido ? $p->PedidoFecha_pedido->format('Y-m-d H:i') : ''),
                'total'     => is_array($p) ? (float) ($p['PedidoTotal'] ?? 0) : (float) $p->PedidoTotal,
                'estado'    => $estadoNombre,
                'cliente'   => is_array($p) ? ($p['cliente']['ClienteNombre'] ?? 'Desconocido') : ($p->cliente->ClienteNombre ?? 'Desconocido'),
            ];
        })->values()->all();

        $estadoFiltro = !empty($args['estado']) ? match (strtoupper(trim($args['estado']))) {
            'A' => 'canceladas (anuladas)',
            'P' => 'pendientes',
            'C' => 'completadas',
            default => "en estado {$args['estado']}",
        } : '';

        $desc = $estadoFiltro
            ? "Se encontraron {$total} órdenes {$estadoFiltro} en el sistema."
            : "Se encontraron {$total} órdenes en total.";

        return [
            'success'           => true,
            'total_encontrados' => $total,
            'data'              => [
                'total'   => $total,
                'pedidos' => $resumenItems,
            ],
            'message'           => $desc,
        ];
    }

    private function obtenerPedido(array $args): array
    {
        if (empty($args['pedido_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['pedido_id'], 'data' => null, 'message' => 'Se requiere el pedido_id.'];
        }
        $pedido = $this->pedidoService->obtenerPedidoPorId($args['pedido_id']);
        return ['success' => true, 'data' => $pedido, 'message' => "Detalle del pedido {$args['pedido_id']}."];
    }

    /**
     * Parsea de manera flexible uno o múltiples IDs de registros (strings individuales, listas separadas por coma/y, o arrays).
     */
    private function parsearListaIds(array $args, string $singularKey, string $pluralKey, string $prefix = 'PED-'): array
    {
        $raw = $args[$pluralKey] ?? $args[$singularKey] ?? $args['pedidos'] ?? $args['ids'] ?? [];
        $items = [];

        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw)) {
            $cleaned = str_ireplace([' y ', ' and '], ',', $raw);
            $items = explode(',', $cleaned);
        }

        $result = [];
        foreach ($items as $item) {
            $val = strtoupper(trim((string)$item));
            if (empty($val)) continue;
            // Normalizar si es puramente numérico (ej: "20" -> "PED-00020")
            if (!empty($prefix) && !str_starts_with($val, $prefix) && is_numeric($val)) {
                $val = $prefix . str_pad($val, 5, '0', STR_PAD_LEFT);
            }
            if (!in_array($val, $result, true)) {
                $result[] = $val;
            }
        }

        return $result;
    }

    private function completarPedido(array $args): array
    {
        $ids = $this->parsearListaIds($args, 'pedido_id', 'pedido_ids', 'PED-');
        if (empty($ids)) {
            return [
                'success'          => false,
                'status'           => 'missing_fields',
                'campos_faltantes' => ['pedido_id o pedido_ids'],
                'data'             => null,
                'message'          => 'Se requiere el código del pedido (o pedidos) a completar.',
            ];
        }

        $completados = [];
        $errores = [];

        foreach ($ids as $id) {
            try {
                $pedido = $this->pedidoService->completarPedido($id);
                $completados[] = [
                    'pedido_id' => $pedido->PedidoId,
                    'estado'    => $pedido->PedidoEstado_pedido ?? 'C',
                    'total'     => (float)$pedido->PedidoTotal,
                    'cliente'   => $pedido->cliente?->ClienteNombre ?? $pedido->Pedido_ClienteId,
                ];
            } catch (Exception $e) {
                Log::warning("GeminiToolsService::completarPedido fallo para {$id}: {$e->getMessage()}");
                $errores[] = "{$id}: {$e->getMessage()}";
            }
        }

        if (empty($completados)) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'No se pudo completar ningún pedido: ' . implode(' | ', $errores),
            ];
        }

        $idsCompletados = array_column($completados, 'pedido_id');
        $idsStr = implode(' y ', $idsCompletados);
        $msg = count($completados) === 1
            ? "Pedido {$idsStr} marcado como COMPLETADO."
            : "Los pedidos {$idsStr} fueron marcados como COMPLETADOS exitosamente.";

        if (!empty($errores)) {
            $msg .= " (Hubo problemas con: " . implode(', ', $errores) . ")";
        }

        return [
            'success'   => true,
            'ui_action' => [
                'type'          => 'orders_completed',
                'completed_ids' => $idsCompletados,
            ],
            'data'      => [
                'total_completados' => count($completados),
                'pedidos'           => $completados,
                'errores'           => $errores,
            ],
            'message'   => $msg,
        ];
    }

    private function cancelarPedido(array $args): array
    {
        $ids = $this->parsearListaIds($args, 'pedido_id', 'pedido_ids', 'PED-');
        if (empty($ids)) {
            return [
                'success'          => false,
                'status'           => 'missing_fields',
                'campos_faltantes' => ['pedido_id o pedido_ids'],
                'data'             => null,
                'message'          => 'Se requiere el código del pedido (o pedidos) a cancelar.',
            ];
        }

        $cancelados = [];
        $errores = [];

        foreach ($ids as $id) {
            try {
                $pedido = $this->pedidoService->cancelarPedido($id);
                $cancelados[] = [
                    'pedido_id' => $pedido->PedidoId,
                    'estado'    => $pedido->PedidoEstado_pedido ?? 'A',
                    'total'     => (float)$pedido->PedidoTotal,
                    'cliente'   => $pedido->cliente?->ClienteNombre ?? $pedido->Pedido_ClienteId,
                ];
            } catch (Exception $e) {
                Log::warning("GeminiToolsService::cancelarPedido fallo para {$id}: {$e->getMessage()}");
                $errores[] = "{$id}: {$e->getMessage()}";
            }
        }

        if (empty($cancelados)) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'No se pudo cancelar ningún pedido: ' . implode(' | ', $errores),
            ];
        }

        $idsCancelados = array_column($cancelados, 'pedido_id');
        $idsStr = implode(' y ', $idsCancelados);
        $msg = count($cancelados) === 1
            ? "Pedido {$idsStr} CANCELADO exitosamente. Stock devuelto al inventario."
            : "Los pedidos {$idsStr} han sido CANCELADOS exitosamente en el sistema y el stock correspondiente ha sido devuelto al inventario.";

        if (!empty($errores)) {
            $msg .= " (Hubo problemas con: " . implode(', ', $errores) . ")";
        }

        return [
            'success'   => true,
            'ui_action' => [
                'type'          => 'orders_cancelled',
                'cancelled_ids' => $idsCancelados,
            ],
            'data'      => [
                'total_cancelados' => count($cancelados),
                'pedidos'          => $cancelados,
                'errores'          => $errores,
            ],
            'message'   => $msg,
        ];
    }

    private function actualizarPedido(array $args): array
    {
        if (empty($args['pedido_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['pedido_id'], 'data' => null, 'message' => 'Se requiere el pedido_id.'];
        }
        $id = $args['pedido_id'];
        unset($args['pedido_id']);

        // Si viene producto_id, resolver nombre a ID si no empieza con PROD-
        if (!empty($args['producto_id']) && !str_starts_with($args['producto_id'], 'PROD-')) {
            $encontrados = $this->inventarioService->buscarProductosRapido($args['producto_id'], 1);
            if ($this->tieneResultados($encontrados)) {
                $first = collect($encontrados)->first();
                $args['producto_id'] = is_array($first) ? ($first['producto_id'] ?? $first['ProductoId']) : $first->ProductoId;
            }
        }

        // Si viene producto y cantidad, resolver medidas de presentación
        if (!empty($args['producto_id']) && !empty($args['cantidad'])) {
            $resMedida = $this->resolverDetalleProductoMedida(
                $args['producto_id'],
                $args['unidad_id'] ?? null,
                !empty($args['factor_conversion']) ? (float)$args['factor_conversion'] : null,
                !empty($args['precio_unitario']) ? (float)$args['precio_unitario'] : null
            );
            $args['unidad_id'] = $resMedida['unidad_id'];
            $args['factor_conversion'] = $resMedida['factor_conversion'];
            if (empty($args['precio_unitario'])) {
                $args['precio_unitario'] = $resMedida['precio_unitario'];
            }
        }

        $pedido = $this->pedidoService->actualizarPedido($id, $args);

        $detallesResumen = $pedido->detalles->map(function ($d) {
            return [
                'producto'        => $d->producto?->ProductoNombre ?? $d->Detalle_Pedido_Productos_ProductoId,
                'cantidad'        => (float)$d->Detalle_Pedido_Productos_cantidad,
                'presentacion'    => $d->unidadMedida?->unidades_medidaDescripcionUnidades ?? 'Unidades',
                'factor'          => (int)$d->Detalle_Pedido_Productos_factor_conversion,
                'precio_unitario' => (float)$d->Detalle_Pedido_Productos_precio_unitario_venta,
                'subtotal'        => (float)$d->Detalle_Pedido_Productos_subtotal,
            ];
        })->toArray();

        return [
            'success' => true,
            'data'    => [
                'pedido_id' => $pedido->PedidoId,
                'estado'    => $pedido->PedidoEstado_pedido ?? 'P',
                'cliente'   => $pedido->cliente?->ClienteNombre ?? $pedido->Pedido_ClienteId,
                'total'     => (float)$pedido->PedidoTotal,
                'igv'       => (float)$pedido->PedidoIgv,
                'items'     => $detallesResumen,
            ],
            'message' => "Pedido {$id} actualizado correctamente. Nuevo total: S/ " . number_format($pedido->PedidoTotal, 2) . ".",
        ];
    }

    private function eliminarPedido(array $args): array
    {
        $ids = $this->parsearListaIds($args, 'pedido_id', 'pedido_ids', 'PED-');
        if (empty($ids)) {
            return [
                'success'          => false,
                'status'           => 'missing_fields',
                'campos_faltantes' => ['pedido_id o pedido_ids'],
                'data'             => null,
                'message'          => 'Se requiere el código del pedido (o pedidos) a eliminar.',
            ];
        }

        $eliminados = [];
        $errores = [];

        foreach ($ids as $id) {
            try {
                $this->pedidoService->eliminarPedido($id);
                $eliminados[] = $id;
            } catch (Exception $e) {
                Log::warning("GeminiToolsService::eliminarPedido fallo para {$id}: {$e->getMessage()}");
                $errores[] = "{$id}: {$e->getMessage()}";
            }
        }

        if (empty($eliminados)) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'No se pudo eliminar ningún pedido: ' . implode(' | ', $errores),
            ];
        }

        $idsStr = implode(' y ', $eliminados);
        $msg = count($eliminados) === 1
            ? "Pedido {$idsStr} eliminado lógicamente y stock devuelto."
            : "Los pedidos {$idsStr} fueron eliminados lógicamente y el stock devuelto.";

        if (!empty($errores)) {
            $msg .= " (Hubo problemas con: " . implode(', ', $errores) . ")";
        }

        return [
            'success'   => true,
            'ui_action' => [
                'type'        => 'orders_deleted',
                'deleted_ids' => $eliminados,
            ],
            'data'      => [
                'total_eliminados' => count($eliminados),
                'pedidos'          => $eliminados,
                'errores'          => $errores,
            ],
            'message'   => $msg,
        ];
    }

    private function obtenerPedidosDelDia(array $args): array
    {
        $hoy = Carbon::today()->toDateString();
        $pedidos = $this->pedidoService->listarPedidos(['fecha_desde' => $hoy, 'fecha_hasta' => $hoy], 50);
        $items = method_exists($pedidos, 'items') ? $pedidos->items() : $pedidos->toArray();
        $total = method_exists($pedidos, 'total') ? $pedidos->total() : count($items);

        $porEstado = [];
        foreach ($items as $p) {
            $estado = is_array($p) ? ($p['PedidoEstado_pedido'] ?? $p['PedidoEstado'] ?? '?') : ($p->PedidoEstado_pedido ?? '?');
            $porEstado[$estado] = ($porEstado[$estado] ?? 0) + 1;
        }

        return [
            'success' => true,
            'data'    => ['total' => $total, 'por_estado' => $porEstado, 'fecha' => $hoy],
            'message' => "Hoy ({$hoy}) hay {$total} pedidos registrados.",
        ];
    }

    private function obtenerEstadisticasPedidos(array $args): array
    {
        $filtros = [];
        if (!empty($args['estado'])) {
            $filtros['estado'] = $args['estado'];
        }
        if (!empty($args['dias']) && (int) $args['dias'] > 0) {
            $filtros['fecha_desde'] = Carbon::now()->subDays((int) $args['dias'])->toDateString();
        }

        $stats = $this->pedidoService->obtenerEstadisticas($filtros);

        $canceladas  = $stats['conteo_estados']['canceladas'] ?? 0;
        $pendientes  = $stats['conteo_estados']['pendientes'] ?? 0;
        $completadas = $stats['conteo_estados']['completadas'] ?? 0;
        $total       = $stats['total_ordenes'] ?? ($canceladas + $pendientes + $completadas);

        if (!empty($args['estado']) && strtoupper($args['estado']) === 'A') {
            $mensaje = "Actualmente hay {$canceladas} órdenes canceladas (anuladas) en el sistema.";
        } elseif (!empty($args['estado']) && strtoupper($args['estado']) === 'P') {
            $mensaje = "Actualmente hay {$pendientes} órdenes pendientes en el sistema.";
        } elseif (!empty($args['estado']) && strtoupper($args['estado']) === 'C') {
            $mensaje = "Actualmente hay {$completadas} órdenes completadas en el sistema.";
        } else {
            $mensaje = "Actualmente hay un total de {$total} órdenes en el sistema: {$canceladas} canceladas, {$pendientes} pendientes y {$completadas} completadas.";
        }

        return [
            'success' => true,
            'data'    => [
                'total_ordenes'            => $total,
                'ordenes_canceladas'       => $canceladas,
                'ordenes_pendientes'       => $pendientes,
                'ordenes_completadas'      => $completadas,
                'conteo_estados'           => $stats['conteo_estados'] ?? [],
                'total_ventas_completadas' => $stats['total_ventas_completadas'] ?? 0,
                'total_monto_pendiente'    => $stats['total_monto_pendiente'] ?? 0,
                'por_canal'                => $stats['por_canal'] ?? [],
            ],
            'message' => $mensaje,
        ];
    }

    private function verificarTimeoutPedidos(array $args): array
    {
        $horas = (int) config('orders.timeout_hours', env('ORDER_TIMEOUT_HOURS', 12));
        $limite = Carbon::now()->subHours($horas);
        $vencidos = Pedido::where('PedidoEstado_pedido', 'P')
            ->where('PedidoFecha_pedido', '<=', $limite)
            ->where('PedidoEliminado', 'N')
            ->get();

        $cancelados = 0;
        foreach ($vencidos as $pedido) {
            try {
                $this->pedidoService->cancelarPedido($pedido->PedidoId);
                $cancelados++;
            } catch (Exception $e) {
                Log::warning("Timeout cancel {$pedido->PedidoId}: {$e->getMessage()}");
            }
        }

        return [
            'success' => true,
            'data'    => ['pedidos_vencidos' => $vencidos->count(), 'cancelados' => $cancelados],
            'message' => "Verificación completada. {$cancelados} pedido(s) cancelados por timeout (>{$horas}h).",
        ];
    }

    // =========================================================================
    // MODULO 2: CLIENTES
    // =========================================================================

    private function crearCliente(array $args, ?string $sessionId = null): array
    {
        // Si hay borrador pendiente en caché y no viene nombre, recuperarlo del draft
        $draftKey = $sessionId ? $this->draftCacheKey($sessionId) : null;
        $draft    = $draftKey ? Cache::get($draftKey) : null;

        // Extraer los 4 campos reales del formulario
        $documento = trim((string)($args['documento'] ?? $args['ClienteRuc'] ?? $args['numero_documento'] ?? $args['ruc'] ?? $args['dni'] ?? ''));
        $nombre    = trim((string)($args['nombre'] ?? $args['ClienteNombre'] ?? $args['razon_social'] ?? ''));
        $telefono  = trim((string)($args['telefono'] ?? $args['ClienteNumero'] ?? $args['celular'] ?? ''));
        $direccion = trim((string)($args['direccion'] ?? $args['ClienteDireccion'] ?? $args['direccion_fiscal'] ?? ''));

        // Recuperar nombre del cliente desde el borrador pendiente si no viene en args
        if (empty($nombre) && $draft && !empty($draft['cliente_nombre_buscado'])) {
            $nombre = $draft['cliente_nombre_buscado'];
        }

        $faltantes = [];
        if (empty($documento)) $faltantes[] = 'documento (RUC/DNI)';
        if (empty($nombre))    $faltantes[] = 'nombre o razón social';
        if (empty($telefono))  $faltantes[] = 'teléfono';
        if (empty($direccion)) $faltantes[] = 'dirección';

        if (!empty($faltantes)) {
            $conocidos = array_filter([
                'documento' => $documento ?: null,
                'nombre'    => $nombre ?: null,
                'telefono'  => $telefono ?: null,
                'direccion' => $direccion ?: null,
            ]);

            return [
                'success'          => false,
                'status'           => 'missing_fields',
                'operacion'        => 'crear_cliente',
                'campos_faltantes' => $faltantes,
                'datos_conocidos'  => $conocidos,
                'data'             => null,
                'message'          => 'Para completar el registro, necesito: ' . implode(', ', $faltantes) . '.',
            ];
        }

        // Validar coherencia textual con el cliente buscado (similar_text >= 80% o Levenshtein <= 2)
        if ($draft && !empty($draft['cliente_nombre_buscado'])) {
            $nombreBuscado = mb_strtolower(trim($draft['cliente_nombre_buscado']));
            $nombreArg     = mb_strtolower(trim($nombre));
            similar_text($nombreBuscado, $nombreArg, $similitud);
            $levenshtein = levenshtein($nombreBuscado, $nombreArg);

            if ($similitud < 80 && $levenshtein > 2) {
                Log::warning('Valencia AI: Incoherencia en nombre de cliente', [
                    'buscado'    => $nombreBuscado,
                    'nuevo'      => $nombreArg,
                    'similitud'  => $similitud,
                    'levenshtein'=> $levenshtein,
                ]);
                // Advertencia soft — no bloquear, solo loggear. El usuario pudo haber corregido el nombre.
            }
        }

        // Limpiar documento y aplicar placeholder si aplica
        $cleanDoc = preg_replace('/\D/', '', $documento);
        if (empty($cleanDoc) || strlen($cleanDoc) < 8) {
            $cleanDoc = '11110000';
        }

        $datosCliente = [
            'ClienteRuc'       => $cleanDoc,
            'ClienteNombre'    => $nombre,
            'ClienteNumero'    => $telefono,
            'ClienteDireccion' => $direccion,
            'ClienteEstado'    => 'A',
        ];

        $cliente = $this->clienteService->crearCliente($datosCliente);

        // Si hay borrador pendiente, vincularlo y retornar acción de apertura de formulario
        $uiAction = null;
        $extraMessage = '';

        if ($draft && $draftKey) {
            // Mover borrador a papelera (ya fue "consumido" al crear el cliente)
            $trashKey = $this->draftTrashCacheKey($sessionId);
            Cache::put($trashKey, $draft, now()->addMinutes(10));
            Cache::forget($draftKey);

            // Preparar prefill del pedido con el cliente recién creado
            $prefill = [
                'cliente'           => [
                    'id'        => $cliente->ClienteId,
                    'nombre'    => $cliente->ClienteNombre,
                    'dni_ruc'   => $cliente->ClienteRuc,
                    'telefono'  => $cliente->ClienteNumero,
                    'direccion' => $cliente->ClienteDireccion,
                ],
                'productos'         => $draft['productos'] ?? [],
                'acuerdo_comercial' => $draft['acuerdo_comercial'] ?? 'Contado Mostrador',
                'canal_id'          => $draft['canal_id'] ?? 'CNL-00001',
                'totales'           => $draft['totales'] ?? [],
            ];

            $uiAction    = ['type' => 'open_order_form', 'form' => 'pedido', 'prefill' => $prefill];
            $extraMessage = " Se abrirá el formulario de pedido con los productos pendientes para {$cliente->ClienteNombre}.";

            Log::info('Valencia AI: Borrador de pedido vinculado al nuevo cliente', [
                'session_id' => $sessionId,
                'draft_id'   => $draft['draft_id'] ?? null,
                'cliente_id' => $cliente->ClienteId,
            ]);
        }

        $response = [
            'success'    => true,
            'cliente_id' => $cliente->ClienteId,
            'data'       => [
                'cliente_id' => $cliente->ClienteId,
                'nombre'     => $cliente->ClienteNombre,
                'documento'  => $cliente->ClienteRuc,
                'telefono'   => $cliente->ClienteNumero,
                'direccion'  => $cliente->ClienteDireccion,
                'estado'     => 'Activo',
            ],
            'message'    => "Cliente '{$cliente->ClienteNombre}' registrado correctamente con ID {$cliente->ClienteId} y documento {$cliente->ClienteRuc}.{$extraMessage}",
        ];

        if ($uiAction !== null) {
            $response['ui_action'] = $uiAction;
        }

        return $response;
    }

    private function buscarCliente(array $args): array
    {
        if (empty($args['termino'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['termino'], 'data' => null, 'message' => 'Se requiere un término de búsqueda.'];
        }
        $resultados = $this->clienteService->buscarClientesRapido($args['termino'], $args['limite'] ?? 10);
        $count = count($resultados);

        $chips = [];
        if ($count === 1) {
            $cli = $resultados[0];
            $nom = $cli['ClienteNombre'] ?? 'Cliente';
            $chips = [
                ['label' => "🛒 Crear pedido para {$nom}", 'query' => "Prepara un pedido para {$nom}"],
                ['label' => "📋 Historial de {$nom}", 'query' => "Historial de pedidos de {$nom}"],
            ];
        }

        return [
            'success' => true,
            'data'    => $resultados,
            'chips'   => !empty($chips) ? $chips : null,
            'message' => "Se encontraron {$count} cliente(s) para '{$args['termino']}'."
        ];
    }

    private function obtenerCliente(array $args): array
    {
        if (empty($args['cliente_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['cliente_id'], 'data' => null, 'message' => 'Se requiere el cliente_id.'];
        }
        $cliente = $this->clienteService->obtenerClientePorId($args['cliente_id']);
        return ['success' => true, 'data' => $cliente, 'message' => "Detalle del cliente {$args['cliente_id']}."];
    }

    private function listarClientes(array $args): array
    {
        $filtros = array_filter(['search' => $args['search'] ?? null, 'estado' => $args['estado'] ?? null]);
        $clientes = $this->clienteService->listarClientes($filtros, $args['per_page'] ?? 15);
        $total = method_exists($clientes, 'total') ? $clientes->total() : count($clientes);
        $items = method_exists($clientes, 'items') ? $clientes->items() : $clientes;

        $resumenItems = collect($items)->map(function ($c) {
            $estado = is_array($c) ? ($c['ClienteEstado'] ?? '?') : ($c->ClienteEstado ?? '?');
            return [
                'cliente_id' => is_array($c) ? $c['ClienteId'] : $c->ClienteId,
                'nombre'     => is_array($c) ? $c['ClienteNombre'] : $c->ClienteNombre,
                'documento'  => is_array($c) ? ($c['ClienteNumDocumento'] ?? $c['ClienteRuc'] ?? '') : ($c->ClienteNumDocumento ?? $c->ClienteRuc ?? ''),
                'telefono'   => is_array($c) ? ($c['ClienteTelefono'] ?? '') : ($c->ClienteTelefono ?? ''),
                'estado'     => trim($estado) === 'A' ? 'Activo' : 'Inactivo',
            ];
        })->values()->all();

        if (!empty($args['estado']) && strtoupper(trim($args['estado'])) === 'A') {
            $desc = "Actualmente hay {$total} clientes activos registrados en el sistema.";
        } elseif (!empty($args['estado']) && strtoupper(trim($args['estado'])) === 'I') {
            $desc = "Actualmente hay {$total} clientes inactivos registrados en el sistema.";
        } else {
            $activos = collect($resumenItems)->filter(fn($c) => $c['estado'] === 'Activo')->count();
            $inactivos = collect($resumenItems)->filter(fn($c) => $c['estado'] === 'Inactivo')->count();
            $desc = "Se encontraron {$total} clientes registrados ({$activos} activos, {$inactivos} inactivos).";
        }

        return [
            'success'           => true,
            'total_encontrados' => $total,
            'data'              => [
                'total'    => $total,
                'clientes' => $resumenItems,
            ],
            'message'           => $desc,
        ];
    }

    private function actualizarCliente(array $args): array
    {
        if (empty($args['cliente_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['cliente_id'], 'data' => null, 'message' => 'Se requiere el cliente_id.'];
        }
        $id = $args['cliente_id'];
        unset($args['cliente_id']);
        $cliente = $this->clienteService->actualizarCliente($id, $args);
        return ['success' => true, 'data' => $cliente, 'message' => "Cliente {$id} actualizado."];
    }

    private function eliminarCliente(array $args): array
    {
        if (empty($args['cliente_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['cliente_id'], 'data' => null, 'message' => 'Se requiere el cliente_id.'];
        }
        $this->clienteService->eliminarCliente($args['cliente_id']);
        return ['success' => true, 'data' => ['cliente_id' => $args['cliente_id']], 'message' => "Cliente {$args['cliente_id']} eliminado (lógicamente)."];
    }

    private function obtenerHistorialCliente(array $args): array
    {
        if (empty($args['cliente_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['cliente_id'], 'data' => null, 'message' => 'Se requiere el cliente_id.'];
        }
        $historial = $this->clienteService->obtenerHistorialPedidos($args['cliente_id'], $args['per_page'] ?? 10);
        return ['success' => true, 'data' => $historial, 'message' => "Historial del cliente {$args['cliente_id']}."];
    }

    private function abrirFormularioCliente(array $args, ?string $sessionId = null): array
    {
        $doc = $args['documento'] ?? $args['numero_documento'] ?? $args['ruc'] ?? $args['dni'] ?? null;
        $nom = $args['nombre'] ?? $args['ClienteNombre'] ?? null;
        $tel = $args['telefono'] ?? $args['ClienteNumero'] ?? null;
        $dir = $args['direccion'] ?? $args['ClienteDireccion'] ?? null;

        // Si hay borrador pendiente y no vino nombre, usar el del draft
        $draft = $sessionId ? Cache::get($this->draftCacheKey($sessionId)) : null;
        if (!$nom && $draft && !empty($draft['cliente_nombre_buscado'])) {
            $nom = $draft['cliente_nombre_buscado'];
        }

        return [
            'success'   => true,
            'ui_action' => [
                'type'     => 'open_form',
                'form'     => 'cliente',
                'prefill'  => [
                    'documento' => $doc,
                    'nombre'    => $nom,
                    'telefono'  => $tel,
                    'direccion' => $dir,
                    'draft_id'  => $draft['draft_id'] ?? null,
                ],
            ],
            'data'    => null,
            'message' => 'Abriendo formulario de creación de cliente con los datos disponibles.',
        ];
    }

    // =========================================================================
    // MODULO 3: PRODUCTOS
    // =========================================================================

    private function crearProducto(array $args): array
    {
        $faltantes = [];
        if (empty($args['nombre']) && empty($args['ProductoNombre'])) $faltantes[] = 'nombre';
        if (empty($args['categoria_id']) && empty($args['ProductoCategoriaId'])) $faltantes[] = 'categoria_id';

        if (!empty($faltantes)) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => $faltantes, 'data' => null, 'message' => 'Faltan: ' . implode(', ', $faltantes)];
        }

        $detalles = $args['detalles'] ?? [
            [
                'unidades_medidaId' => $args['unidades_medida_id'] ?? 'UND-00001',
                'factor_conversion' => 1,
                'precio_compra'     => (float)($args['precio_compra'] ?? ($args['precio_unitario'] ?? 0)),
                'precio_venta'      => (float)($args['precio_unitario'] ?? 0),
            ]
        ];

        $datosProducto = [
            'ProductoNombre'                => $args['nombre'] ?? $args['ProductoNombre'],
            'Producto_Categoria_ProductoId' => $args['categoria_id'] ?? $args['ProductoCategoriaId'],
            'ProductoStockMinimo'           => $args['stock_minimo'] ?? 5,
            'producto_descripcion'          => $args['descripcion'] ?? '',
        ];

        $producto = $this->inventarioService->crearProducto($datosProducto, $detalles, $args['proveedor_ids'] ?? []);
        return ['success' => true, 'data' => ['producto_id' => $producto->ProductoId, 'nombre' => $producto->ProductoNombre], 'message' => "Producto '{$producto->ProductoNombre}' registrado con ID {$producto->ProductoId}."];
    }

    private function buscarProducto(array $args): array
    {
        if (empty($args['termino'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['termino'], 'data' => null, 'message' => 'Se requiere un término de búsqueda.'];
        }
        $termino = strtolower(trim((string)$args['termino']));
        $limite = (int)($args['limite'] ?? 10);
        $cacheKey = "ai_product_search:{$termino}:{$limite}";

        $resultados = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($termino, $limite) {
            return self::toPlainArray($this->inventarioService->buscarProductosRapido($termino, $limite));
        });

        $count = count($resultados);

        $chips = [];
        if ($count > 1 && $count <= 6) {
            foreach ($resultados as $p) {
                $pnom = $p['nombre'] ?? '';
                $pres = collect($p['unidades_medida'] ?? [])->first(fn($u) => (int)($u['factor_conversion'] ?? 0) > 1)
                    ?? ($p['unidades_medida'][0] ?? null);
                $presDesc = $pres ? " ({$pres['descripcion']})" : "";
                $chips[] = [
                    'label' => "{$pnom}{$presDesc}",
                    'query' => "Quiero {$pnom}{$presDesc}",
                ];
            }
        }

        return [
            'success' => true,
            'data'    => $resultados,
            'chips'   => !empty($chips) ? $chips : null,
            'message' => "{$count} producto(s) encontrado(s).",
        ];
    }

    private function obtenerProducto(array $args): array
    {
        if (empty($args['producto_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['producto_id'], 'data' => null, 'message' => 'Se requiere el producto_id.'];
        }
        $producto = $this->inventarioService->obtenerProductoPorId($args['producto_id']);
        return ['success' => true, 'data' => $producto, 'message' => "Detalle del producto {$args['producto_id']}."];
    }

    private function listarProductos(array $args): array
    {
        $filtros = array_filter([
            'search'      => $args['search'] ?? null,
            'categoriaId' => $args['categoria_id'] ?? null,
            'stockBajo'   => $args['stock_bajo'] ?? null,
        ]);
        $productos = $this->inventarioService->listarProductos($filtros, $args['per_page'] ?? 10);
        return ['success' => true, 'data' => $productos, 'message' => 'Catálogo de productos obtenido.'];
    }

    private function actualizarProducto(array $args): array
    {
        if (empty($args['producto_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['producto_id'], 'data' => null, 'message' => 'Se requiere el producto_id.'];
        }
        $id = $args['producto_id'];
        unset($args['producto_id']);
        $producto = $this->inventarioService->actualizarProducto($id, $args);
        return ['success' => true, 'data' => $producto, 'message' => "Producto {$id} actualizado."];
    }

    private function eliminarProducto(array $args): array
    {
        if (empty($args['producto_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['producto_id'], 'data' => null, 'message' => 'Se requiere el producto_id.'];
        }
        $this->inventarioService->eliminarProductoLogico($args['producto_id']);
        return ['success' => true, 'data' => ['producto_id' => $args['producto_id']], 'message' => "Producto {$args['producto_id']} eliminado."];
    }

    private function consultarStock(array $args): array
    {
        if (empty($args['producto_id']) && empty($args['termino'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['producto_id o termino'], 'data' => null, 'message' => 'Indica el producto_id o un término de búsqueda.'];
        }

        if (!empty($args['producto_id']) && str_starts_with($args['producto_id'], 'PROD-')) {
            $stock = $this->inventarioService->consultarStockProductoPorUnidades($args['producto_id']);
        } else {
            $termino = $args['producto_id'] ?? $args['termino'];
            $resProd = $this->resolverProductoOAmbiguedad($termino);
            if ($resProd['status'] === 'ambiguous') {
                return [
                    'success'                => false,
                    'status'                 => 'ambiguous_product',
                    'termino'                => $resProd['termino'],
                    'productos_coincidentes' => $resProd['candidatos'],
                    'chips'                  => $resProd['chips'],
                    'data'                   => [
                        'termino'    => $resProd['termino'],
                        'candidatos' => $resProd['candidatos'],
                        'chips'      => $resProd['chips'],
                    ],
                    'message'                => $resProd['message'],
                ];
            }
            if ($resProd['status'] === 'not_found') {
                return [
                    'success' => false,
                    'status'  => 'not_found',
                    'data'    => null,
                    'message' => $resProd['message'],
                ];
            }
            $pid = $resProd['producto']->ProductoId;
            $stock = $this->inventarioService->consultarStockProductoPorUnidades($pid);
        }

        return ['success' => true, 'data' => $stock, 'message' => 'Stock consultado correctamente.'];
    }

    private function listarProductosBajoStock(array $args): array
    {
        $productos = $this->inventarioService->obtenerProductosStockBajo($args['limite'] ?? 20);
        $count = count($productos);
        return ['success' => true, 'data' => $productos, 'message' => "{$count} producto(s) con stock bajo o agotado."];
    }

    private function ajustarStock(array $args): array
    {
        $faltantes = [];
        if (empty($args['producto_id'])) $faltantes[] = 'producto_id';
        if (empty($args['cantidad']))    $faltantes[] = 'cantidad';
        if (empty($args['tipo']))        $faltantes[] = 'tipo (E=entrada, S=salida)';
        if (empty($args['motivo']))      $faltantes[] = 'motivo';

        if (!empty($faltantes)) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => $faltantes, 'data' => null, 'message' => 'Para ajustar el stock faltan: ' . implode(', ', $faltantes)];
        }

        $movimiento = $this->inventarioService->registrarMovimiento([
            'productoId'           => $args['producto_id'],
            'unidadesMedidaId'     => $args['unidades_medida_id'] ?? 'UND-00001',
            'tipoMovimiento'       => strtoupper($args['tipo']),
            'cantidad'             => $args['cantidad'],
            'documentoOperacionId' => 'AJUSTE-IA',
            'precioUnitario'       => $args['precio_unitario'] ?? null,
        ]);

        return ['success' => true, 'data' => $movimiento, 'message' => "Stock de {$args['producto_id']} ajustado en {$args['cantidad']} unidades. Motivo: {$args['motivo']}."];
    }

    private function listarCategorias(array $args): array
    {
        $categorias = CategoriaProducto::where('Categoria_ProductoEliminado', 'N')
            ->orderBy('Categoria_ProductoDescripcion_categoria')
            ->get();

        $total = $categorias->count();
        $lista = $categorias->map(function ($c) {
            return [
                'categoria_id' => $c->Categoria_ProductoId,
                'nombre'       => $c->Categoria_ProductoDescripcion_categoria,
                'estado'       => trim($c->Categoria_ProductoEstado) === 'A' ? 'Activo' : 'Inactivo',
            ];
        })->values()->all();

        $nombres = $categorias->pluck('Categoria_ProductoDescripcion_categoria')->implode(', ');

        return [
            'success'           => true,
            'total_encontrados' => $total,
            'data'              => [
                'total'      => $total,
                'categorias' => $lista,
            ],
            'message'           => "Actualmente hay {$total} categorías de productos disponibles en el sistema: {$nombres}.",
        ];
    }

    // =========================================================================
    // MODULO 4: PROVEEDORES
    // =========================================================================

    private function crearProveedor(array $args, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: 'sess_fb_' . strtolower(Str::random(12));
        $faltantes = [];
        $razonSocial = trim((string)($args['razon_social'] ?? $args['ProveedorRazonSocial'] ?? ''));
        $ruc = trim((string)($args['ruc'] ?? $args['ProveedorRuc'] ?? ''));
        if (empty($razonSocial)) $faltantes[] = 'razon_social';
        if (empty($ruc)) $faltantes[] = 'ruc';

        if (!empty($faltantes)) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => $faltantes, 'data' => null, 'message' => 'Faltan: ' . implode(', ', $faltantes)];
        }

        $telefono = trim((string)($args['telefono'] ?? $args['ProveedorTelefono'] ?? '-'));
        $tipoContribuyente = trim((string)($args['tipo_contribuyente'] ?? $args['ProveedorTipoContribuyente'] ?? 'GENERAL'));
        $actividadEconomica = trim((string)($args['actividad_economica'] ?? $args['ProveedorActividadEconomica'] ?? '-'));

        $datosProveedor = [
            'ProveedorRazonSocial'        => $razonSocial,
            'ProveedorRuc'                => $ruc,
            'ProveedorTelefono'           => !empty($telefono) ? $telefono : '-',
            'ProveedorTipoContribuyente'  => !empty($tipoContribuyente) ? $tipoContribuyente : 'GENERAL',
            'ProveedorActividadEconomica' => !empty($actividadEconomica) ? $actividadEconomica : '-',
            'ProveedorEstado'             => 'A',
        ];

        $proveedor = $this->proveedorService->crearProveedor($datosProveedor);
        $provId = $proveedor->ProveedorId;
        $razonSocial = $proveedor->ProveedorRazonSocial;

        // Comprobar si existía un borrador pendiente de compra para esta sesión
        $draft = Cache::get(self::purchaseDraftCacheKey($sessionId)) ?? Cache::get('ai_pending_purchase_order_draft:latest');

        if ($draft && !empty($draft['productos'])) {
            $buscado = $draft['proveedor_nombre_buscado'] ?? '';
            $esCoherente = $this->sonRazonesSocialesCoherentes($buscado, $razonSocial);

            if ($esCoherente) {
                // Mover borrador a papelera y limpiar clave activa
                Cache::put(self::purchaseDraftTrashCacheKey($sessionId), $draft, now()->addMinutes(10));
                Cache::forget(self::purchaseDraftCacheKey($sessionId));
                Cache::forget('ai_pending_purchase_order_draft:latest');

                $proveedorData = [
                    'ProveedorId'          => $provId,
                    'ProveedorRazonSocial' => $razonSocial,
                    'ProveedorRuc'         => $proveedor->ProveedorRuc,
                    'ProveedorTelefono'    => $proveedor->ProveedorTelefono ?? '',
                    'ProveedorDireccion'   => $proveedor->ProveedorDireccion ?? '',
                ];

                $productos = $draft['productos'];
                $totales = $draft['totales'] ?? [
                    'subtotal' => array_sum(array_column($productos, 'subtotal')),
                    'igv'      => round(array_sum(array_column($productos, 'subtotal')) * 0.18, 2),
                    'total'    => round(array_sum(array_column($productos, 'subtotal')) * 1.18, 2),
                ];

                return [
                    'success'   => true,
                    'ui_action' => [
                        'type'    => 'open_purchase_order_form',
                        'form'    => 'orden_compra',
                        'prefill' => [
                            'proveedor'   => $proveedorData,
                            'productos'   => $productos,
                            'observacion' => $draft['observacion'] ?? 'Orden generada vía Valencia AI tras registrar proveedor',
                            'totales'     => $totales,
                        ],
                    ],
                    'data'      => [
                        'proveedor_id' => $provId,
                        'razon_social' => $razonSocial,
                        'orden_cargada'=> true,
                        'total'        => $totales['total'],
                        'items_count'  => count($productos),
                    ],
                    'message'   => "Proveedor **{$razonSocial}** registrado exitosamente. He cargado automáticamente los " . count($productos) . " producto(s) de tu orden de compra pendiente (Total: S/ " . number_format($totales['total'], 2) . ") en pantalla.",
                ];
            }
        }

        return ['success' => true, 'data' => ['proveedor_id' => $provId, 'razon_social' => $razonSocial], 'message' => "Proveedor '{$razonSocial}' registrado."];
    }

    private function buscarProveedor(array $args): array
    {
        if (empty($args['termino'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['termino'], 'data' => null, 'message' => 'Se requiere un término de búsqueda.'];
        }
        $resultados = $this->proveedorService->buscarProveedoresRapido($args['termino'], $args['limite'] ?? 10);
        $items = is_array($resultados) ? ($resultados['data'] ?? $resultados) : (method_exists($resultados, 'items') ? $resultados->items() : $resultados);
        $count = count($items);

        if ($count === 0) {
            return ['success' => true, 'data' => $resultados, 'message' => "No se encontraron proveedores con el término '{$args['termino']}'."];
        }

        $lineas = [];
        foreach ($items as $idx => $pr) {
            $nombre = is_array($pr) ? ($pr['ProveedorRazonSocial'] ?? '') : ($pr->ProveedorRazonSocial ?? '');
            $ruc = is_array($pr) ? ($pr['ProveedorRuc'] ?? '') : ($pr->ProveedorRuc ?? '');
            $tel = is_array($pr) ? ($pr['ProveedorTelefono'] ?? '') : ($pr->ProveedorTelefono ?? '');
            $line = ($idx + 1) . ". **{$nombre}** (RUC: {$ruc})";
            if (!empty($tel)) {
                $line .= " - Tel: {$tel}";
            }
            $lineas[] = $line;
        }

        $mensaje = "Se encontraron {$count} proveedor(es) con '{$args['termino']}':\n" . implode("\n", $lineas);
        return ['success' => true, 'data' => $resultados, 'message' => $mensaje];
    }

    private function obtenerProveedor(array $args): array
    {
        if (empty($args['proveedor_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['proveedor_id'], 'data' => null, 'message' => 'Se requiere el proveedor_id.'];
        }
        $proveedor = $this->proveedorService->obtenerProveedorPorId($args['proveedor_id']);
        return ['success' => true, 'data' => $proveedor, 'message' => "Detalle del proveedor {$args['proveedor_id']}."];
    }

    private function listarProveedores(array $args): array
    {
        $filtros = array_filter(['search' => $args['search'] ?? null, 'estado' => $args['estado'] ?? null]);
        $proveedores = $this->proveedorService->listarProveedores($filtros, $args['per_page'] ?? 15);
        $items = method_exists($proveedores, 'items') ? $proveedores->items() : (is_array($proveedores) ? ($proveedores['data'] ?? $proveedores) : $proveedores);
        $total = method_exists($proveedores, 'total') ? $proveedores->total() : count($items);

        if (count($items) === 0) {
            return ['success' => true, 'data' => $proveedores, 'message' => 'No se encontraron proveedores registrados.'];
        }

        $lineas = [];
        foreach ($items as $idx => $pr) {
            $nombre = is_array($pr) ? ($pr['ProveedorRazonSocial'] ?? '') : ($pr->ProveedorRazonSocial ?? '');
            $ruc = is_array($pr) ? ($pr['ProveedorRuc'] ?? '') : ($pr->ProveedorRuc ?? '');
            $tel = is_array($pr) ? ($pr['ProveedorTelefono'] ?? '') : ($pr->ProveedorTelefono ?? '');
            $line = ($idx + 1) . ". **{$nombre}** (RUC: {$ruc})";
            if (!empty($tel)) {
                $line .= " - Tel: {$tel}";
            }
            $lineas[] = $line;
        }

        $mensaje = "Se encontraron {$total} proveedor(es) registrados:\n" . implode("\n", $lineas);
        return ['success' => true, 'data' => $proveedores, 'message' => $mensaje];
    }

    private function actualizarProveedor(array $args): array
    {
        if (empty($args['proveedor_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['proveedor_id'], 'data' => null, 'message' => 'Se requiere el proveedor_id.'];
        }
        $id = $args['proveedor_id'];
        unset($args['proveedor_id']);
        $proveedor = $this->proveedorService->actualizarProveedor($id, $args);
        return ['success' => true, 'data' => $proveedor, 'message' => "Proveedor {$id} actualizado."];
    }

    private function eliminarProveedor(array $args): array
    {
        if (empty($args['proveedor_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['proveedor_id'], 'data' => null, 'message' => 'Se requiere el proveedor_id.'];
        }
        $this->proveedorService->eliminarProveedor($args['proveedor_id']);
        return ['success' => true, 'data' => ['proveedor_id' => $args['proveedor_id']], 'message' => "Proveedor {$args['proveedor_id']} eliminado."];
    }

    private function abrirFormularioProveedor(array $args, ?string $sessionId = null): array
    {
        $razonSocial = $args['razon_social'] ?? $args['ProveedorRazonSocial'] ?? $args['termino'] ?? '';
        $ruc = $args['ruc'] ?? $args['ProveedorRuc'] ?? '';
        $telefono = $args['telefono'] ?? $args['ProveedorTelefono'] ?? '';
        $direccion = $args['direccion'] ?? $args['ProveedorDireccion'] ?? '';

        return [
            'success'   => true,
            'ui_action' => [
                'type'    => 'open_form',
                'form'    => 'proveedor',
                'prefill' => [
                    'ProveedorRazonSocial' => $razonSocial,
                    'ProveedorRuc'         => $ruc,
                    'ProveedorTelefono'    => $telefono,
                    'ProveedorDireccion'   => $direccion,
                ],
            ],
            'data'      => [
                'razon_social' => $razonSocial,
                'ruc'          => $ruc,
            ],
            'message'   => "He abierto el formulario para registrar al nuevo proveedor" . ($razonSocial ? " '{$razonSocial}'" : "") . ".",
        ];
    }

    // =========================================================================
    // MODULO 5: ORDENES DE COMPRA
    // =========================================================================

    private function crearOrdenCompra(array $args, ?array $liveDraft = null, ?string $userMessage = null, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: 'sess_fb_' . strtolower(Str::random(12));
        $proveedorId = $args['proveedor_id'] ?? $args['proveedor'] ?? null;
        if (empty($proveedorId) && !empty($liveDraft['proveedor_id'])) {
            $proveedorId = $liveDraft['proveedor_id'];
        }

        $proveedor = null;
        if (!empty($proveedorId)) {
            $resProveedor = $this->resolverProveedorOAmbiguedad((string)$proveedorId);
            if ($resProveedor['status'] === 'exact') {
                $proveedor = $resProveedor['proveedor'];
                $proveedorId = $proveedor->ProveedorId;
            } elseif ($resProveedor['status'] === 'ambiguous') {
                return $this->abrirFormularioOrdenCompra($args, $sessionId);
            }
        }

        if (!$proveedor) {
            return $this->abrirFormularioOrdenCompra($args, $sessionId);
        }

        $detalles = $args['detalles'] ?? $args['productos'] ?? [];

        // Sincronización en vivo con la pantalla activa del usuario (liveDraft)
        if (!empty($liveDraft) && ($liveDraft['tipo'] ?? '') === 'orden_compra' && !empty($liveDraft['detalles'])) {
            if (empty($detalles)) {
                $detalles = $liveDraft['detalles'];
            } else {
                $liveMap = [];
                $liveMapByName = [];
                foreach ($liveDraft['detalles'] as $ld) {
                    $pid = $ld['producto_id'] ?? $ld['id'] ?? null;
                    $pnom = strtolower(trim($ld['nombre'] ?? $ld['producto_nombre'] ?? ''));
                    if ($pid) $liveMap[$pid] = $ld;
                    if ($pnom) $liveMapByName[$pnom] = $ld;
                }

                // Sincronizar cantidades y precios unitarios con la pantalla
                foreach ($detalles as &$d) {
                    $pid = $d['producto_id'] ?? $d['id'] ?? null;
                    $matchedLd = null;
                    if ($pid && isset($liveMap[$pid])) {
                        $matchedLd = $liveMap[$pid];
                    } else {
                        $term = strtolower(trim((string)($pid ?? $d['nombre'] ?? '')));
                        if ($term && isset($liveMapByName[$term])) {
                            $matchedLd = $liveMapByName[$term];
                        }
                    }

                    if ($matchedLd) {
                        if (!empty($matchedLd['cantidad'])) {
                            $d['cantidad'] = $matchedLd['cantidad'];
                        }
                        if (!empty($matchedLd['unidad_id']) && empty($d['unidad_id']) && empty($d['unidad_medida_id'])) {
                            $d['unidad_medida_id'] = $matchedLd['unidad_id'];
                        }
                        if (!empty($matchedLd['precio_unitario']) && empty($d['precio_unitario'])) {
                            $d['precio_unitario'] = $matchedLd['precio_unitario'];
                        }
                    }
                }
                unset($d);

                // Agregar productos presentes en pantalla que Gemini haya omitido (ej: Guarana agregada manualmente en UI)
                $existingKeys = [];
                foreach ($detalles as $d) {
                    $pid = $d['producto_id'] ?? $d['id'] ?? null;
                    if ($pid) $existingKeys[$pid] = true;
                    $pnom = strtolower(trim($d['nombre'] ?? ''));
                    if ($pnom) $existingKeys[$pnom] = true;
                }

                foreach ($liveDraft['detalles'] as $ld) {
                    $pid = $ld['producto_id'] ?? $ld['id'] ?? null;
                    $pnom = strtolower(trim($ld['nombre'] ?? $ld['producto_nombre'] ?? ''));
                    $found = ($pid && isset($existingKeys[$pid])) || ($pnom && isset($existingKeys[$pnom]));
                    if (!$found) {
                        $detalles[] = $ld;
                        if ($pid) $existingKeys[$pid] = true;
                    }
                }
            }
        }

        if (empty($args['observaciones']) && !empty($liveDraft['observacion'])) {
            $args['observaciones'] = $liveDraft['observacion'];
        }

        $faltantes = [];
        if (empty($proveedorId)) $faltantes[] = 'proveedor_id';
        if (empty($detalles) || !is_array($detalles)) $faltantes[] = 'detalles (productos a ordenar)';

        if (!empty($faltantes)) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => $faltantes, 'data' => null, 'message' => 'Faltan: ' . implode(', ', $faltantes)];
        }

        // VALIDACIÓN ESTRICTA DE CONFIRMACIÓN DEL USUARIO:
        // Evita crear órdenes de compra accidentales ante audios como "El pedido", dudas o errores.
        // Solo se persiste en BD si el argumento trae 'confirmado' => true o el mensaje del usuario expresa confirmación inequívoca.
        $estaConfirmado = ($args['confirmado'] ?? false) === true || $this->tieneConfirmacionExplicita($userMessage);
        if (!$estaConfirmado) {
            Log::info("Valencia AI: crear_orden_compra interceptado sin confirmación explícita. Mostrando borrador en pantalla.", [
                'userMessage' => $userMessage,
                'proveedorId' => $proveedorId,
            ]);

            $resApertura = $this->abrirFormularioOrdenCompra([
                'proveedor_id'  => $proveedorId,
                'detalles'      => $detalles,
                'observaciones' => $args['observaciones'] ?? null,
            ], $sessionId);

            $numProds = count($detalles);
            $resApertura['message'] = "He preparado el borrador de la orden de compra en pantalla con {$numProds} producto(s). Por favor, revísala y confírmame si deseas guardarla en el sistema (responde 'Sí' o 'Confirmo').";
            $resApertura['requiere_confirmacion'] = true;
            return $resApertura;
        }

        // Normalizar detalles
        $detallesNormalizados = [];
        foreach ($detalles as $item) {
            $prodId = trim((string)($item['producto_id'] ?? $item['termino'] ?? $item['nombre'] ?? ''));
            $cant = (float)($item['cantidad'] ?? 1);
            $unidadId = $item['unidad_id'] ?? $item['unidad_medida_id'] ?? null;
            $factor = !empty($item['factor_conversion']) ? (float)$item['factor_conversion'] : null;
            $precio = !empty($item['precio_unitario']) ? (float)$item['precio_unitario'] : (!empty($item['precio_compra']) ? (float)$item['precio_compra'] : null);

            $resProd = $this->resolverProductoOAmbiguedad($prodId, $unidadId, $factor, $precio, 'compra');
            if ($resProd['status'] === 'ambiguous') {
                return [
                    'success'                => false,
                    'status'                 => 'ambiguous_product',
                    'termino'                => $resProd['termino'],
                    'productos_coincidentes' => $resProd['candidatos'],
                    'chips'                  => $resProd['chips'],
                    'data'                   => [
                        'termino'    => $resProd['termino'],
                        'candidatos' => $resProd['candidatos'],
                        'chips'      => $resProd['chips'],
                    ],
                    'message'                => $resProd['message'],
                ];
            }
            if ($resProd['status'] === 'not_found') {
                return [
                    'success' => false,
                    'status'  => 'not_found',
                    'data'    => null,
                    'message' => $resProd['message'],
                ];
            }
            $producto = $resProd['producto'];
            $prodId = $producto->ProductoId;

            $resMedida = $this->resolverDetalleProductoMedida($prodId, $unidadId, $factor, $precio);
            $precioCompra = $resMedida['precio_compra'] > 0 ? $resMedida['precio_compra'] : ($resMedida['precio_unitario'] > 0 ? $resMedida['precio_unitario'] : 1.0);
            if ($precio !== null && $precio > 0) {
                $precioCompra = $precio;
            }

            $detallesNormalizados[] = [
                'producto_id'      => $prodId,
                'unidad_medida_id' => $resMedida['unidad_id'],
                'cantidad'         => $cant,
                'precio_unitario'  => $precioCompra,
            ];
        }

        $payload = array_merge($args, [
            'proveedor_id' => $proveedorId,
            'detalles'     => $detallesNormalizados,
        ]);

        $orden = $this->ordenCompraService->crearOrden($payload);
        $provNombre = $orden->proveedor?->ProveedorRazonSocial ?? $proveedorId;

        // Limpiar / mover borrador pendiente a papelera si existía
        $activeDraft = Cache::get(self::purchaseDraftCacheKey($sessionId));
        if ($activeDraft) {
            Cache::put(self::purchaseDraftTrashCacheKey($sessionId), $activeDraft, now()->addMinutes(10));
            Cache::forget(self::purchaseDraftCacheKey($sessionId));
            Cache::forget('ai_pending_purchase_order_draft:latest');
        }

        $ordenId = $orden->Orden_CompraId ?? $orden->Orden_compraId ?? $orden->getKey();
        $total = (float)($orden->Orden_CompraTotal ?? $orden->Orden_compraTotal ?? 0);
        $estado = $orden->Orden_CompraEstado ?? $orden->Orden_compraEstado ?? 'P';
        $subtotal = (float)($orden->Orden_CompraSubtotal ?? $orden->Orden_compraSubtotal ?? 0);
        $igv = (float)($orden->Orden_CompraIgv ?? $orden->Orden_compraIgv ?? 0);
        $fecha = $orden->Orden_CompraFecha ?? $orden->Orden_compraFecha ?? now();

        return [
            'success'   => true,
            'ui_action' => [
                'type'      => 'purchase_order_created',
                'orden_id'  => $ordenId,
                'total'     => $total,
            ],
            'data'      => [
                'orden_id'  => $ordenId,
                'estado'    => $estado,
                'proveedor' => $provNombre,
                'total'     => $total,
                'subtotal'  => $subtotal,
                'igv'       => $igv,
                'fecha'     => $fecha,
            ],
            'message'   => "Orden de compra {$ordenId} creada exitosamente para {$provNombre}. Total: S/ " . number_format($total, 2) . ".",
        ];
    }

    /**
     * Prepara y abre el formulario de Nueva Orden de Compra en la interfaz con el proveedor y productos precargados.
     * Esta es la herramienta Copiloto para Compras: NO guarda en base de datos.
     */
    private function abrirFormularioOrdenCompra(array $args, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: 'sess_fb_' . strtolower(Str::random(12));

        // 1. Resolver proveedor
        $proveedorId = $args['proveedor_id'] ?? $args['proveedor'] ?? null;
        $proveedorNombre = $args['proveedor_nombre'] ?? null;
        $terminoProveedor = trim((string)($proveedorId ?: $proveedorNombre ?: ''));
        $proveedor = null;

        if (!empty($terminoProveedor)) {
            if ($this->esProveedorGenerico($terminoProveedor)) {
                $genericoBD = \App\Models\Proveedor::where('ProveedorEliminado', 'N')
                    ->where('ProveedorEstado', 'A')
                    ->where(function ($q) {
                        $q->where('ProveedorRazonSocial', 'like', '%VARIOS%')
                          ->orWhere('ProveedorRazonSocial', 'like', '%GENERAL%')
                          ->orWhere('ProveedorRuc', '00000000000');
                    })->first();

                if ($genericoBD) {
                    $proveedor = $genericoBD;
                } else {
                    return [
                        'success' => false,
                        'status'  => 'generic_supplier_not_configured',
                        'chips'   => [
                            ['label' => 'Registrar PROVEEDOR VARIOS', 'action' => 'register_supplier', 'params' => ['razon_social' => 'PROVEEDOR VARIOS', 'ruc' => '00000000000']],
                            ['label' => 'Abrir Formulario de Proveedor', 'action' => 'open_supplier_form', 'params' => ['razon_social' => 'PROVEEDOR VARIOS']],
                        ],
                        'message' => "Para registrar compras a proveedores varios o menores, se requiere registrar al menos una vez al proveedor 'PROVEEDOR VARIOS' con RUC '00000000000'. ¿Deseas que lo registre ahora?",
                    ];
                }
            } else {
                $resProv = $this->resolverProveedorOAmbiguedad($terminoProveedor);
                if ($resProv['status'] === 'ambiguous') {
                    $candidatos = $resProv['candidatos'];
                    $chips = $candidatos->map(function ($c) {
                        return [
                            'label'  => "Usar {$c->ProveedorRazonSocial}",
                            'action' => 'select_supplier',
                            'params' => [
                                'proveedor_id'     => $c->ProveedorId,
                                'proveedor_nombre' => $c->ProveedorRazonSocial,
                            ],
                        ];
                    })->values()->all();

                    $nombres = $candidatos->pluck('ProveedorRazonSocial')->implode(', ');
                    return [
                        'success'                => false,
                        'status'                 => 'ambiguous_supplier',
                        'termino'                => $terminoProveedor,
                        'proveedores_candidatos' => $candidatos->map(fn($c) => [
                            'proveedor_id' => $c->ProveedorId,
                            'razon_social' => $c->ProveedorRazonSocial,
                            'ruc'          => $c->ProveedorRuc,
                        ])->values()->all(),
                        'chips'                  => $chips,
                        'data'                   => null,
                        'message'                => "Encontré más de un proveedor que coincide con '{$terminoProveedor}': {$nombres}. Por favor selecciona a cuál de ellos deseas comprarle.",
                    ];
                }

                $proveedor = ($resProv['status'] === 'exact') ? $resProv['proveedor'] : null;
            }
        }

        // Si el proveedor NO existe en BD: Flujo de Proveedor no encontrado y retención de orden de compra
        if (!$proveedor) {
            if (empty($terminoProveedor)) {
                return [
                    'success'          => false,
                    'status'           => 'missing_fields',
                    'campos_faltantes' => ['proveedor_id o nombre de proveedor'],
                    'data'             => null,
                    'message'          => 'Indica el nombre o ID del proveedor para preparar la orden de compra.',
                ];
            }

            $detallesRaw = $args['detalles'] ?? $args['productos'] ?? [];
            if (empty($detallesRaw) || !is_array($detallesRaw)) {
                return [
                    'success'          => false,
                    'status'           => 'missing_fields',
                    'campos_faltantes' => ['detalles (productos a ordenar)'],
                    'data'             => null,
                    'message'          => "El proveedor '{$terminoProveedor}' no se encuentra registrado. Para retener tu orden de compra, indica los productos que deseas comprar.",
                ];
            }

            $productosResueltos = [];
            foreach ($detallesRaw as $item) {
                $prodId = trim((string)($item['producto_id'] ?? $item['id'] ?? $item['nombre'] ?? $item['producto'] ?? $item['termino'] ?? ''));
                $cant = (float)($item['cantidad'] ?? 1);
                $unidadId = $item['unidad_id'] ?? null;
                $factor = !empty($item['factor_conversion']) ? (float)$item['factor_conversion'] : null;
                $precio = !empty($item['precio_unitario']) ? (float)$item['precio_unitario'] : (!empty($item['precio_compra']) ? (float)$item['precio_compra'] : null);

                if (empty($prodId)) continue;

                $resProd = $this->resolverProductoOAmbiguedad($prodId, $unidadId, $factor, $precio, 'compra');
                if ($resProd['status'] === 'ambiguous') {
                    return [
                        'success'                => false,
                        'status'                 => 'ambiguous_product',
                        'termino'                => $resProd['termino'],
                        'productos_coincidentes' => $resProd['candidatos'],
                        'chips'                  => $resProd['chips'],
                        'data'                   => [
                            'termino'    => $resProd['termino'],
                            'candidatos' => $resProd['candidatos'],
                            'chips'      => $resProd['chips'],
                        ],
                        'message'                => $resProd['message'],
                    ];
                }
                if ($resProd['status'] === 'not_found') {
                    return [
                        'success' => false,
                        'status'  => 'not_found',
                        'data'    => null,
                        'message' => $resProd['message'],
                    ];
                }

                $producto = $resProd['producto'];
                $producto->loadMissing(['categoria', 'detalleProductoMedidas.unidadMedida']);
                $resMedida = $this->resolverDetalleProductoMedida($producto->ProductoId, $unidadId, $factor, $precio);
                $factor = $resMedida['factor_conversion'];
                $precioCompra = $resMedida['precio_compra'] > 0 ? $resMedida['precio_compra'] : ($resMedida['precio_unitario'] > 0 ? $resMedida['precio_unitario'] : 1.0);
                if ($precio !== null && $precio > 0) {
                    $precioCompra = $precio;
                }
                $subtotal = round($cant * $precioCompra, 2);

                $unidadesDisponibles = $producto->detalleProductoMedidas->map(function ($det) {
                    return [
                        'unidades_medidaId' => $det->Detalle_Producto_medida_unidades_medidaId,
                        'descripcion'       => $det->unidadMedida?->unidades_medidaDescripcionUnidades ?? 'Unidad',
                        'abreviatura'       => $det->unidadMedida?->unidades_medidaAbreviatura ?? 'UND',
                        'factor_conversion' => (int) $det->Detalle_Producto_medida_factor_conversion,
                        'precio_compra'     => (float) $det->Detalle_Producto_medida_precio_compra,
                        'es_base'           => (int) $det->Detalle_Producto_medida_factor_conversion === 1,
                    ];
                })->values()->all();

                $productosResueltos[] = [
                    'tempId'              => "{$producto->ProductoId}_{$resMedida['unidad_id']}_" . (int)(microtime(true) * 1000) . '_' . mt_rand(100, 999),
                    'productoId'          => $producto->ProductoId,
                    'productoNombre'      => $producto->ProductoNombre,
                    'productoMarca'       => $producto->ProductoMarca ?: 'Genérico',
                    'unidadMedidaId'      => $resMedida['unidad_id'],
                    'unidadNombre'        => $resMedida['unidad_descripcion'] ?: 'Unidad',
                    'unidadAbreviatura'   => $resMedida['unidad_abreviatura'] ?: 'UND',
                    'cantidad'            => $cant,
                    'precioUnitario'      => $precioCompra,
                    'subtotal'            => $subtotal,
                    'unidadesDisponibles' => $unidadesDisponibles,
                ];
            }

            if (empty($productosResueltos)) {
                return [
                    'success' => false,
                    'data'    => null,
                    'message' => 'No se encontraron productos disponibles con los datos especificados.',
                ];
            }

            $subtotalGeneral = array_sum(array_column($productosResueltos, 'subtotal'));
            $igvGeneral = round($subtotalGeneral * 0.18, 2);
            $totalGeneral = round($subtotalGeneral + $igvGeneral, 2);

            // Idempotencia: Si ya existía un borrador pendiente idéntico, conservar su purchase_draft_id
            $prevDraft = Cache::get(self::purchaseDraftCacheKey($sessionId));
            $esMismoBorrador = false;
            if ($prevDraft && isset($prevDraft['proveedor_nombre_buscado'], $prevDraft['productos'])) {
                if ($this->sonRazonesSocialesCoherentes($prevDraft['proveedor_nombre_buscado'], $terminoProveedor)) {
                    $prodsPrev = array_map(fn($p) => ($p['productoId'] ?? '') . '_' . ($p['cantidad'] ?? 0), $prevDraft['productos']);
                    $prodsNow = array_map(fn($p) => ($p['productoId'] ?? '') . '_' . ($p['cantidad'] ?? 0), $productosResueltos);
                    sort($prodsPrev);
                    sort($prodsNow);
                    if ($prodsPrev === $prodsNow) {
                        $esMismoBorrador = true;
                    }
                }
            }

            if ($esMismoBorrador && !empty($prevDraft['purchase_draft_id'])) {
                $purchaseDraftId = $prevDraft['purchase_draft_id'];
                $createdAt = $prevDraft['created_at'] ?? now()->toIso8601String();
            } else {
                $purchaseDraftId = 'pord_' . (int)(microtime(true) * 1000) . '_' . strtolower(Str::random(6));
                $createdAt = now()->toIso8601String();
            }

            $draftData = [
                'purchase_draft_id'        => $purchaseDraftId,
                'proveedor_nombre_buscado' => $terminoProveedor,
                'productos'                => $productosResueltos,
                'observacion'              => $args['observaciones'] ?? $args['observacion'] ?? 'Orden generada vía Valencia AI',
                'totales'                  => [
                    'subtotal' => $subtotalGeneral,
                    'igv'      => $igvGeneral,
                    'total'    => $totalGeneral,
                ],
                'expira_en_minutos'        => 30,
                'created_at'               => $createdAt,
            ];

            Cache::put(self::purchaseDraftCacheKey($sessionId), $draftData, now()->addMinutes(30));
            Cache::put('ai_pending_purchase_order_draft:latest', $draftData, now()->addMinutes(30));

            $alternativos = $this->obtenerProveedoresAlternativos($terminoProveedor, 3);

            $chips = [
                [
                    'label'  => "Registrar a {$terminoProveedor}",
                    'action' => 'register_supplier',
                    'params' => ['razon_social' => $terminoProveedor],
                ],
                [
                    'label'  => 'Abrir Formulario de Proveedor',
                    'action' => 'open_supplier_form',
                    'params' => ['razon_social' => $terminoProveedor],
                ],
            ];

            foreach ($alternativos as $alt) {
                $chips[] = [
                    'label'  => "Comprar a {$alt['proveedor_razon_social']}",
                    'action' => 'reassign_purchase_draft',
                    'params' => [
                        'proveedor_id'      => $alt['proveedor_id'],
                        'proveedor_nombre'  => $alt['proveedor_razon_social'],
                        'purchase_draft_id' => $purchaseDraftId,
                    ],
                ];
            }

            $chips[] = [
                'label'  => 'Descartar orden de compra',
                'action' => 'discard_purchase_draft',
                'params' => ['purchase_draft_id' => $purchaseDraftId],
            ];

            return [
                'success'   => false,
                'status'    => 'supplier_not_found',
                'ui_action' => [
                    'type'                     => 'supplier_not_found',
                    'pending_purchase_order'   => $draftData,
                    'proveedores_alternativos' => $alternativos,
                ],
                'chips'     => $chips,
                'data'      => $draftData,
                'message'   => "El proveedor **{$terminoProveedor}** no se encuentra registrado en el sistema. He guardado temporalmente los " . count($productosResueltos) . " producto(s) de tu orden de compra (Total: S/ " . number_format($totalGeneral, 2) . ") por 30 minutos.\n\nPuedes registrar al nuevo proveedor para completar la orden automáticamente, o reasignarla a uno de nuestros proveedores habituales.",
            ];
        }

        // Proveedor SI existe en BD
        $proveedorData = [
            'ProveedorId'          => $proveedor->ProveedorId,
            'ProveedorRazonSocial' => $proveedor->ProveedorRazonSocial,
            'ProveedorRuc'         => $proveedor->ProveedorRuc,
            'ProveedorTelefono'    => $proveedor->ProveedorTelefono ?? '',
            'ProveedorDireccion'   => $proveedor->ProveedorDireccion ?? '',
        ];

        $detallesRaw = $args['detalles'] ?? $args['productos'] ?? [];
        $activeDraft = Cache::get(self::purchaseDraftCacheKey($sessionId)) ?? Cache::get('ai_pending_purchase_order_draft:latest');

        // Prevención de contaminación (Riesgo B): Si vienen productos nuevos explícitos y había borrador viejo:
        if (!empty($detallesRaw) && $activeDraft) {
            Cache::put(self::purchaseDraftTrashCacheKey($sessionId), $activeDraft, now()->addMinutes(10));
            Cache::forget(self::purchaseDraftCacheKey($sessionId));
            Cache::forget('ai_pending_purchase_order_draft:latest');
            $activeDraft = null;
        }

        // Si NO se pasaron productos nuevos pero hay un borrador activo en la sesión:
        if (empty($detallesRaw) && $activeDraft && !empty($activeDraft['productos'])) {
            $buscadoEnDraft = $activeDraft['proveedor_nombre_buscado'] ?? '';
            $esCoherente = $this->sonRazonesSocialesCoherentes($buscadoEnDraft, $proveedor->ProveedorRazonSocial);
            $confirmadoReasignar = ($args['confirmar_reasignacion'] ?? false) === true;

            if ($esCoherente || $confirmadoReasignar) {
                $productosResueltos = $activeDraft['productos'];
                $subtotalGeneral = $activeDraft['totales']['subtotal'] ?? array_sum(array_column($productosResueltos, 'subtotal'));
                $igvGeneral = $activeDraft['totales']['igv'] ?? round($subtotalGeneral * 0.18, 2);
                $totalGeneral = $activeDraft['totales']['total'] ?? round($subtotalGeneral + $igvGeneral, 2);

                Cache::put(self::purchaseDraftTrashCacheKey($sessionId), $activeDraft, now()->addMinutes(10));
                Cache::forget(self::purchaseDraftCacheKey($sessionId));
                Cache::forget('ai_pending_purchase_order_draft:latest');

                return [
                    'success'   => true,
                    'ui_action' => [
                        'type'    => 'open_purchase_order_form',
                        'form'    => 'orden_compra',
                        'prefill' => [
                            'proveedor'   => $proveedorData,
                            'productos'   => $productosResueltos,
                            'observacion' => $activeDraft['observacion'] ?? 'Orden generada vía Valencia AI',
                            'totales'     => [
                                'subtotal' => $subtotalGeneral,
                                'igv'      => $igvGeneral,
                                'total'    => $totalGeneral,
                            ],
                        ],
                    ],
                    'data'      => [
                        'proveedor'   => $proveedorData['ProveedorRazonSocial'],
                        'items_count' => count($productosResueltos),
                        'subtotal'    => $subtotalGeneral,
                        'igv'         => $igvGeneral,
                        'total'       => $totalGeneral,
                        'items'       => array_map(fn($p) => [
                            'producto'      => $p['productoNombre'],
                            'cantidad'      => $p['cantidad'],
                            'presentacion'  => $p['unidadNombre'] ?: $p['unidadAbreviatura'],
                            'precio_compra' => $p['precioUnitario'],
                            'subtotal'      => $p['subtotal'],
                        ], $productosResueltos),
                    ],
                    'message'   => "He asociado los " . count($productosResueltos) . " producto(s) del borrador pendiente al proveedor **{$proveedorData['ProveedorRazonSocial']}** (Total: S/ " . number_format($totalGeneral, 2) . "). La pantalla de Nueva Orden de Compra ha sido abierta para tu revisión.",
                ];
            } else {
                return [
                    'success' => true,
                    'status'  => 'reassign_confirmation_needed',
                    'chips'   => [
                        [
                            'label'  => "Sí, comprar a {$proveedor->ProveedorRazonSocial}",
                            'action' => 'confirm_reassign_purchase_draft',
                            'params' => [
                                'proveedor_id'           => $proveedor->ProveedorId,
                                'proveedor_nombre'       => $proveedor->ProveedorRazonSocial,
                                'confirmar_reasignacion' => true,
                            ],
                        ],
                        [
                            'label'  => "No, mantener para {$buscadoEnDraft}",
                            'action' => 'cancel_reassign',
                            'params' => [],
                        ],
                    ],
                    'message' => "Tienes una orden pendiente con " . count($activeDraft['productos']) . " producto(s) para **{$buscadoEnDraft}**. ¿Deseas transferir estos productos a **{$proveedor->ProveedorRazonSocial}**?",
                ];
            }
        }

        // 2. Resolver detalles de productos si se pasaron explícitamente
        if (empty($detallesRaw) || !is_array($detallesRaw)) {
            return [
                'success'          => false,
                'status'           => 'missing_fields',
                'campos_faltantes' => ['detalles (productos a ordenar)'],
                'data'             => null,
                'message'          => 'Indica al menos un producto y cantidad para preparar la orden de compra en pantalla.',
            ];
        }

        $productosResueltos = [];

        foreach ($detallesRaw as $item) {
            $prodId = trim((string)($item['producto_id'] ?? $item['id'] ?? $item['nombre'] ?? $item['producto'] ?? $item['termino'] ?? ''));
            $cant = (float)($item['cantidad'] ?? 1);
            $unidadId = $item['unidad_id'] ?? null;
            $factor = !empty($item['factor_conversion']) ? (float)$item['factor_conversion'] : null;
            $precio = !empty($item['precio_unitario']) ? (float)$item['precio_unitario'] : (!empty($item['precio_compra']) ? (float)$item['precio_compra'] : null);

            if (empty($prodId)) continue;

            $resProd = $this->resolverProductoOAmbiguedad($prodId, $unidadId, $factor, $precio, 'compra');
            if ($resProd['status'] === 'ambiguous') {
                return [
                    'success'                => false,
                    'status'                 => 'ambiguous_product',
                    'termino'                => $resProd['termino'],
                    'productos_coincidentes' => $resProd['candidatos'],
                    'chips'                  => $resProd['chips'],
                    'data'                   => [
                        'termino'    => $resProd['termino'],
                        'candidatos' => $resProd['candidatos'],
                        'chips'      => $resProd['chips'],
                    ],
                    'message'                => $resProd['message'],
                ];
            }
            if ($resProd['status'] === 'not_found') {
                return [
                    'success' => false,
                    'status'  => 'not_found',
                    'data'    => null,
                    'message' => $resProd['message'],
                ];
            }

            $producto = $resProd['producto'];
            $producto->loadMissing(['categoria', 'detalleProductoMedidas.unidadMedida']);

            $resMedida = $this->resolverDetalleProductoMedida($producto->ProductoId, $unidadId, $factor, $precio);
            $factor = $resMedida['factor_conversion'];
            $precioCompra = $resMedida['precio_compra'] > 0 ? $resMedida['precio_compra'] : ($resMedida['precio_unitario'] > 0 ? $resMedida['precio_unitario'] : 1.0);
            if ($precio !== null && $precio > 0) {
                $precioCompra = $precio;
            }
            $subtotal = round($cant * $precioCompra, 2);

            $unidadesDisponibles = $producto->detalleProductoMedidas->map(function ($det) {
                return [
                    'unidades_medidaId' => $det->Detalle_Producto_medida_unidades_medidaId,
                    'descripcion'       => $det->unidadMedida?->unidades_medidaDescripcionUnidades ?? 'Unidad',
                    'abreviatura'       => $det->unidadMedida?->unidades_medidaAbreviatura ?? 'UND',
                    'factor_conversion' => (int) $det->Detalle_Producto_medida_factor_conversion,
                    'precio_compra'     => (float) $det->Detalle_Producto_medida_precio_compra,
                    'es_base'           => (int) $det->Detalle_Producto_medida_factor_conversion === 1,
                ];
            })->values()->all();

            $productosResueltos[] = [
                'tempId'              => "{$producto->ProductoId}_{$resMedida['unidad_id']}_" . (int)(microtime(true) * 1000) . '_' . mt_rand(100, 999),
                'productoId'          => $producto->ProductoId,
                'productoNombre'      => $producto->ProductoNombre,
                'productoMarca'       => $producto->ProductoMarca ?: 'Genérico',
                'unidadMedidaId'      => $resMedida['unidad_id'],
                'unidadNombre'        => $resMedida['unidad_descripcion'] ?: 'Unidad',
                'unidadAbreviatura'   => $resMedida['unidad_abreviatura'] ?: 'UND',
                'cantidad'            => $cant,
                'precioUnitario'      => $precioCompra,
                'subtotal'            => $subtotal,
                'unidadesDisponibles' => $unidadesDisponibles,
            ];
        }

        if (empty($productosResueltos)) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'No se encontraron productos disponibles con los datos especificados.',
            ];
        }

        $subtotalGeneral = array_sum(array_column($productosResueltos, 'subtotal'));
        $igvGeneral = round($subtotalGeneral * 0.18, 2);
        $totalGeneral = round($subtotalGeneral + $igvGeneral, 2);

        return [
            'success'   => true,
            'ui_action' => [
                'type'    => 'open_purchase_order_form',
                'form'    => 'orden_compra',
                'prefill' => [
                    'proveedor'   => $proveedorData,
                    'productos'   => $productosResueltos,
                    'observacion' => $args['observacion'] ?? 'Orden generada vía Valencia AI',
                    'totales'     => [
                        'subtotal' => $subtotalGeneral,
                        'igv'      => $igvGeneral,
                        'total'    => $totalGeneral,
                    ],
                ],
            ],
            'data'      => [
                'proveedor'   => $proveedorData['ProveedorRazonSocial'],
                'items_count' => count($productosResueltos),
                'subtotal'    => $subtotalGeneral,
                'igv'         => $igvGeneral,
                'total'       => $totalGeneral,
                'items'       => array_map(fn($p) => [
                    'producto'      => $p['productoNombre'],
                    'cantidad'      => $p['cantidad'],
                    'presentacion'  => $p['unidadNombre'] ?: $p['unidadAbreviatura'],
                    'precio_compra' => $p['precioUnitario'],
                    'subtotal'      => $p['subtotal'],
                ], $productosResueltos),
            ],
            'message'   => "Se ha preparado la orden de compra para {$proveedorData['ProveedorRazonSocial']} con " . count($productosResueltos) . " producto(s). Total estimado: S/ " . number_format($totalGeneral, 2) . ". La pantalla de Nueva Orden de Compra ha sido abierta para tu revisión y guardado.",
        ];
    }

    private function limpiarFormularioOrdenCompra(array $args, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: 'sess_fb_' . strtolower(Str::random(12));
        $draft = Cache::get(self::purchaseDraftCacheKey($sessionId)) ?? Cache::get('ai_pending_purchase_order_draft:latest');
        if ($draft) {
            Cache::put(self::purchaseDraftTrashCacheKey($sessionId), $draft, now()->addMinutes(10));
            Cache::forget(self::purchaseDraftCacheKey($sessionId));
            Cache::forget('ai_pending_purchase_order_draft:latest');
        }

        return [
            'success'   => true,
            'ui_action' => [
                'type' => 'clear_purchase_order_form',
                'form' => 'orden_compra',
            ],
            'data'      => null,
            'message'   => 'El formulario de orden de compra en pantalla ha sido limpiado exitosamente.',
        ];
    }

    private function recuperarBorradorOrdenCompra(array $args, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: 'sess_fb_' . strtolower(Str::random(12));
        $trashDraft = Cache::get(self::purchaseDraftTrashCacheKey($sessionId));

        if (!$trashDraft) {
            return [
                'success' => false,
                'status'  => 'no_draft',
                'data'    => null,
                'message' => 'No hay ningún borrador de orden de compra en la papelera para recuperar o ya pasaron más de 10 minutos.',
            ];
        }

        // Restaurar a la clave activa
        Cache::put(self::purchaseDraftCacheKey($sessionId), $trashDraft, now()->addMinutes(30));
        Cache::put('ai_pending_purchase_order_draft:latest', $trashDraft, now()->addMinutes(30));
        Cache::forget(self::purchaseDraftTrashCacheKey($sessionId));

        $termino = $trashDraft['proveedor_nombre_buscado'] ?? 'el proveedor';
        $alternativos = $this->obtenerProveedoresAlternativos($termino, 3);
        $purchaseDraftId = $trashDraft['purchase_draft_id'];

        $chips = [
            ['label' => "Registrar a {$termino}", 'action' => 'register_supplier', 'params' => ['razon_social' => $termino]],
            ['label' => 'Abrir Formulario de Proveedor', 'action' => 'open_supplier_form', 'params' => ['razon_social' => $termino]],
        ];
        foreach ($alternativos as $alt) {
            $chips[] = [
                'label'  => "Comprar a {$alt['proveedor_razon_social']}",
                'action' => 'reassign_purchase_draft',
                'params' => [
                    'proveedor_id'      => $alt['proveedor_id'],
                    'proveedor_nombre'  => $alt['proveedor_razon_social'],
                    'purchase_draft_id' => $purchaseDraftId,
                ],
            ];
        }
        $chips[] = [
            'label'  => 'Descartar orden de compra',
            'action' => 'discard_purchase_draft',
            'params' => ['purchase_draft_id' => $purchaseDraftId],
        ];

        return [
            'success'   => true,
            'status'    => 'purchase_draft_restored',
            'ui_action' => [
                'type'                     => 'purchase_draft_restored',
                'pending_purchase_order'   => $trashDraft,
                'proveedores_alternativos' => $alternativos,
            ],
            'chips'     => $chips,
            'data'      => $trashDraft,
            'message'   => "Borrador de orden de compra para **{$termino}** restaurado exitosamente.",
        ];
    }

    private function listarOrdenesCompra(array $args): array
    {
        $filtros = array_filter(['estado' => $args['estado'] ?? null, 'proveedor_id' => $args['proveedor_id'] ?? null, 'search' => $args['search'] ?? null]);
        $ordenes = $this->ordenCompraService->listarOrdenes($filtros, $args['per_page'] ?? 10);
        return ['success' => true, 'data' => $ordenes, 'message' => 'Listado de órdenes de compra obtenido.'];
    }

    private function obtenerOrdenCompra(array $args): array
    {
        if (empty($args['orden_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['orden_id'], 'data' => null, 'message' => 'Se requiere el orden_id.'];
        }
        $orden = $this->ordenCompraService->obtenerOrdenPorId($args['orden_id']);
        return ['success' => true, 'data' => $orden, 'message' => "Detalle de la orden {$args['orden_id']}."];
    }

    private function completarOrdenCompra(array $args): array
    {
        if (empty($args['orden_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['orden_id'], 'data' => null, 'message' => 'Se requiere el orden_id.'];
        }
        $orden = $this->ordenCompraService->cambiarEstado($args['orden_id'], 'R');
        return ['success' => true, 'data' => $orden, 'message' => "Orden {$args['orden_id']} marcada como RECIBIDA."];
    }

    private function cancelarOrdenCompra(array $args): array
    {
        if (empty($args['orden_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['orden_id'], 'data' => null, 'message' => 'Se requiere el orden_id.'];
        }
        $orden = $this->ordenCompraService->cambiarEstado($args['orden_id'], 'A');
        return ['success' => true, 'data' => $orden, 'message' => "Orden {$args['orden_id']} CANCELADA."];
    }

    private function actualizarOrdenCompra(array $args): array
    {
        if (empty($args['orden_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['orden_id'], 'data' => null, 'message' => 'Se requiere el orden_id.'];
        }
        $id = $args['orden_id'];
        unset($args['orden_id']);
        $orden = $this->ordenCompraService->actualizarOrden($id, $args);
        return ['success' => true, 'data' => $orden, 'message' => "Orden de compra {$id} actualizada."];
    }

    private function obtenerEstadisticasCompras(array $args): array
    {
        $dias = $args['dias'] ?? 30;
        $desde = Carbon::now()->subDays($dias)->startOfDay();
        $ordenes = OrdenCompra::where('Orden_compraFecha_emision', '>=', $desde)->where('Orden_compraEliminado', 'N')->get();
        $totalInvertido = $ordenes->sum('Orden_compraTotal');
        $porEstado = $ordenes->groupBy('Orden_compraEstado')->map->count();

        return [
            'success' => true,
            'data'    => [
                'periodo_dias'    => $dias,
                'total_ordenes'   => $ordenes->count(),
                'total_invertido' => round($totalInvertido, 2),
                'por_estado'      => $porEstado,
            ],
            'message' => "KPIs compras últimos {$dias} días: {$ordenes->count()} órdenes, S/ " . number_format($totalInvertido, 2) . " invertido.",
        ];
    }

    // =========================================================================
    // MODULO 6: CANALES
    // =========================================================================

    private function listarCanales(array $args): array
    {
        $canales = $this->canalPedidoService->listarCanales([]);
        return ['success' => true, 'data' => $canales, 'message' => 'Canales de pedido disponibles.'];
    }

    private function crearCanal(array $args): array
    {
        if (empty($args['descripcion'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['descripcion'], 'data' => null, 'message' => 'Se requiere la descripción del canal.'];
        }
        $canal = $this->canalPedidoService->crearCanal($args);
        return ['success' => true, 'data' => $canal, 'message' => "Canal '{$args['descripcion']}' creado."];
    }

    private function actualizarCanal(array $args): array
    {
        if (empty($args['canal_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['canal_id'], 'data' => null, 'message' => 'Se requiere el canal_id.'];
        }
        $id = $args['canal_id'];
        unset($args['canal_id']);
        $canal = $this->canalPedidoService->actualizarCanal($id, $args);
        return ['success' => true, 'data' => $canal, 'message' => "Canal {$id} actualizado."];
    }

    // =========================================================================
    // MODULO 7: KARDEX
    // =========================================================================

    private function consultarKardex(array $args): array
    {
        if (empty($args['producto_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['producto_id'], 'data' => null, 'message' => 'Se requiere el producto_id para consultar el Kárdex.'];
        }
        $movimientos = $this->inventarioService->obtenerMovimientosPorProducto($args['producto_id'], $args['per_page'] ?? 20);
        return ['success' => true, 'data' => $movimientos, 'message' => "Kárdex del producto {$args['producto_id']} obtenido."];
    }

    private function listarMovimientos(array $args): array
    {
        $filtros = array_filter([
            'productoId'     => $args['producto_id'] ?? null,
            'tipoMovimiento' => $args['tipo'] ?? null,
            'fechaDesde'     => $args['fecha_desde'] ?? null,
            'fechaHasta'     => $args['fecha_hasta'] ?? null,
            'dias'           => $args['dias'] ?? null,
        ]);
        $movimientos = $this->inventarioService->listarMovimientos($filtros, $args['per_page'] ?? 15);
        return ['success' => true, 'data' => $movimientos, 'message' => 'Movimientos de inventario obtenidos.'];
    }

    private function obtenerResumenKardex(array $args): array
    {
        $dias = $args['dias'] ?? 30;
        $desde = Carbon::now()->subDays($dias)->startOfDay()->toDateString();
        $query = MovimientoProducto::where('Movimiento_productoFecha_Movimiento', '>=', $desde);

        $totalEntradas = (clone $query)->where('Movimiento_productoTipoMovimiento', 'E')
            ->selectRaw('SUM(TRY_CAST(Movimiento_productoCantidadEntrada AS DECIMAL(18,2))) as total')
            ->value('total') ?? 0;

        $totalSalidas = (clone $query)->where('Movimiento_productoTipoMovimiento', 'S')
            ->selectRaw('SUM(TRY_CAST(Movimiento_productoCantidadSalida AS DECIMAL(18,2))) as total')
            ->value('total') ?? 0;

        $resumen = [
            'periodo_dias'      => $dias,
            'total_entradas'    => (float) $totalEntradas,
            'total_salidas'     => (float) $totalSalidas,
            'total_movimientos' => $query->count(),
        ];
        return ['success' => true, 'data' => $resumen, 'message' => "Resumen del Kárdex de los últimos {$dias} días."];
    }

    // =========================================================================
    // MODULO 8: REPORTES
    // =========================================================================

    private function obtenerEstadisticasGenerales(array $args): array
    {
        $dias = $args['dias'] ?? 7;
        $stats = $this->dashboardService->obtenerIndicadores(['dias' => (string) $dias]);
        return ['success' => true, 'data' => $stats, 'message' => "Estadísticas generales de los últimos {$dias} días."];
    }

    private function obtenerVentasPorPeriodo(array $args): array
    {
        $dias = $args['dias'] ?? 30;
        $desde = Carbon::now()->subDays($dias)->startOfDay();
        $pedidos = Pedido::where('PedidoFecha_pedido', '>=', $desde)
            ->where('PedidoEliminado', 'N')
            ->where('PedidoEstado_pedido', 'C')
            ->selectRaw('CAST(PedidoFecha_pedido AS DATE) as fecha, COUNT(*) as cantidad, SUM(PedidoTotal) as total')
            ->groupByRaw('CAST(PedidoFecha_pedido AS DATE)')
            ->orderBy('fecha', 'desc')
            ->get();
        $totalGeneral = $pedidos->sum('total');
        return ['success' => true, 'data' => ['ventas_por_dia' => $pedidos, 'total_general' => round($totalGeneral, 2), 'dias' => $dias], 'message' => "Ventas últimos {$dias} días: S/ " . number_format($totalGeneral, 2) . "."];
    }

    private function obtenerTopProductos(array $args): array
    {
        $limite = $args['limite'] ?? 10;
        $dias = $args['dias'] ?? 30;
        $desde = Carbon::now()->subDays($dias)->startOfDay();
        $top = DB::table('Detalle_Pedido_Productos as dpp')
            ->join('Pedido as p', 'dpp.Detalle_Pedido_Productos_PedidoId', '=', 'p.PedidoId')
            ->join('Producto as prod', 'dpp.Detalle_Pedido_Productos_ProductoId', '=', 'prod.ProductoId')
            ->where('p.PedidoFecha_pedido', '>=', $desde)
            ->where('p.PedidoEliminado', 'N')
            ->whereIn('p.PedidoEstado_pedido', ['P', 'C'])
            ->select(
                'prod.ProductoId',
                'prod.ProductoNombre',
                DB::raw('SUM(dpp.Detalle_Pedido_Productos_cantidad) as total_vendido'),
                DB::raw('SUM(dpp.Detalle_Pedido_Productos_subtotal) as total_facturado'),
                DB::raw('COUNT(DISTINCT p.PedidoId) as en_pedidos')
            )
            ->groupBy('prod.ProductoId', 'prod.ProductoNombre')
            ->orderByDesc('total_vendido')
            ->limit($limite)
            ->get();
        return ['success' => true, 'data' => $top, 'message' => "Top {$limite} productos más vendidos en {$dias} días."];
    }

    private function obtenerTopClientes(array $args): array
    {
        $limite = $args['limite'] ?? 10;
        $dias = $args['dias'] ?? 30;
        $desde = Carbon::now()->subDays($dias)->startOfDay();
        $top = DB::table('Pedido as p')
            ->join('Cliente as c', 'p.Pedido_ClienteId', '=', 'c.ClienteId')
            ->where('p.PedidoFecha_pedido', '>=', $desde)
            ->where('p.PedidoEliminado', 'N')
            ->whereIn('p.PedidoEstado_pedido', ['P', 'C'])
            ->select(
                'c.ClienteId',
                'c.ClienteNombre',
                DB::raw('COUNT(p.PedidoId) as total_pedidos'),
                DB::raw('SUM(p.PedidoTotal) as total_comprado')
            )
            ->groupBy('c.ClienteId', 'c.ClienteNombre')
            ->orderByDesc('total_comprado')
            ->limit($limite)
            ->get();
        return ['success' => true, 'data' => $top, 'message' => "Top {$limite} clientes por compra en {$dias} días."];
    }

    private function obtenerReporteInventario(array $args): array
    {
        $productos = Producto::with(['detalleProductoMedidas' => function ($q) {
            $q->where('Detalle_Producto_medidaEliminado', 'N');
        }])
        ->where('ProductoEliminado', 'N')
        ->orderBy('ProductoNombre')
        ->get();

        $totalValorizado = 0;
        $resumenProductos = [];

        foreach ($productos as $p) {
            $stockActual = (float) ($p->ProductoStockActual ?? 0);
            $stockMinimo = (float) ($p->ProductoStockMinimo ?? 0);
            $medidaBase = $p->detalleProductoMedidas->first();
            $precioVenta = $medidaBase ? (float) $medidaBase->Detalle_Producto_medida_precio_venta : 0.0;
            $precioCompra = $medidaBase ? (float) $medidaBase->Detalle_Producto_medida_precio_compra : 0.0;

            $valorizado = $stockActual * ($precioCompra > 0 ? $precioCompra : $precioVenta);
            $totalValorizado += $valorizado;

            $resumenProductos[] = [
                'producto_id'   => $p->ProductoId,
                'nombre'        => $p->ProductoNombre,
                'marca'         => $p->ProductoMarca,
                'stock_actual'  => $stockActual,
                'stock_minimo'  => $stockMinimo,
                'precio_venta'  => $precioVenta,
                'stock_virtual' => (float) ($p->ProductoStockVirtual ?? 0),
                'estado_stock'  => $stockActual <= 0 ? 'Agotado' : ($stockActual <= $stockMinimo ? 'Bajo Stock' : 'Normal'),
            ];
        }

        $bajoStock = collect($resumenProductos)->filter(fn($p) => $p['stock_actual'] > 0 && $p['stock_actual'] <= $p['stock_minimo'])->count();
        $agotados  = collect($resumenProductos)->filter(fn($p) => $p['stock_actual'] <= 0)->count();
        $total     = count($resumenProductos);

        return [
            'success'           => true,
            'total_encontrados' => $total,
            'data'              => [
                'total_productos' => $total,
                'bajo_stock'      => $bajoStock,
                'agotados'        => $agotados,
                'valor_total_inv' => round($totalValorizado, 2),
                'productos'       => array_slice($resumenProductos, 0, 20),
            ],
            'message'           => "Inventario general: {$total} productos registrados, {$bajoStock} con stock bajo, {$agotados} agotados. Valorización estimada: S/ " . number_format($totalValorizado, 2) . ".",
        ];
    }

    // =========================================================================
    // MODULO 9: NOTIFICACIONES
    // =========================================================================

    private function listarAlertas(array $args): array
    {
        $filtros = array_filter(['tipo' => $args['tipo'] ?? null, 'solo_no_leidas' => $args['solo_no_leidas'] ?? null]);
        $resultado = $this->notificacionService->listar($filtros, $args['per_page'] ?? 10);
        return ['success' => true, 'data' => $resultado, 'message' => 'Alertas obtenidas.'];
    }

    private function marcarAlertaLeida(array $args): array
    {
        if (empty($args['notificacion_id'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['notificacion_id'], 'data' => null, 'message' => 'Se requiere el notificacion_id.'];
        }
        $ok = $this->notificacionService->marcarLeida($args['notificacion_id']);
        return ['success' => $ok, 'data' => null, 'message' => $ok ? "Notificación {$args['notificacion_id']} marcada como leída." : 'No se encontró la notificación.'];
    }

    private function notificarPedidosPorExpirar(array $args): array
    {
        $horas = (int) config('orders.timeout_hours', env('ORDER_TIMEOUT_HOURS', 12));
        $margen = $args['margen_horas'] ?? 2;
        $limite = Carbon::now()->subHours($horas - $margen);
        $limite2 = Carbon::now()->subHours($horas);
        $porExpirar = Pedido::where('PedidoEstado_pedido', 'P')
            ->where('PedidoFecha_pedido', '<=', $limite)
            ->where('PedidoFecha_pedido', '>', $limite2)
            ->where('PedidoEliminado', 'N')
            ->get();
        foreach ($porExpirar as $pedido) {
            $this->notificacionService->crear([
                'titulo'        => "Pedido {$pedido->PedidoId} por expirar",
                'mensaje'       => "El pedido {$pedido->PedidoId} expirará en menos de {$margen} hora(s).",
                'tipo'          => 'warning',
                'categoria'     => 'PEDIDOS',
                'referencia_id' => $pedido->PedidoId,
                'badge_texto'   => 'Por Expirar',
                'badge_tipo'    => 'warning',
            ]);
        }
        return ['success' => true, 'data' => ['notificados' => $porExpirar->count()], 'message' => "Se notificaron {$porExpirar->count()} pedido(s) próximos a expirar."];
    }

    // =========================================================================
    // MODULO 10: UTILIDADES
    // =========================================================================

    private function solicitarCampoFaltante(array $args): array
    {
        $campos = $args['campos'] ?? [];
        $contexto = $args['contexto'] ?? 'la operación';
        $pregunta = $args['pregunta'] ?? "Para completar {$contexto} necesito: " . implode(', ', $campos);
        return [
            'success'        => true,
            'status'         => 'awaiting_input',
            'campos_pedidos' => $campos,
            'data'           => ['pregunta' => $pregunta, 'campos' => $campos],
            'message'        => $pregunta,
        ];
    }

    private function confirmarAccion(array $args): array
    {
        $accion = $args['accion'] ?? 'la operación';
        $descripcion = $args['descripcion'] ?? '';
        $impacto = $args['impacto'] ?? 'Esta acción no se puede deshacer.';
        return [
            'success'   => true,
            'status'    => 'pending_confirmation',
            'ui_action' => [
                'type'        => 'confirm_dialog',
                'accion'      => $accion,
                'descripcion' => $descripcion,
                'impacto'     => $impacto,
                'tool_to_run' => $args['tool_siguiente'] ?? null,
                'args_to_pass' => $args['args_siguientes'] ?? [],
            ],
            'data'    => null,
            'message' => "Confirmación requerida: {$descripcion}. {$impacto}",
        ];
    }

    private function buscarGlobal(array $args): array
    {
        if (empty($args['termino'])) {
            return ['success' => false, 'status' => 'missing_fields', 'campos_faltantes' => ['termino'], 'data' => null, 'message' => 'Se requiere un término de búsqueda.'];
        }
        $t = $args['termino'];
        $productos = $this->inventarioService->buscarProductosRapido($t, 5);
        $clientes = $this->clienteService->buscarClientesRapido($t, 5);
        $proveedores = $this->proveedorService->buscarProveedoresRapido($t, 5);
        $pCount = count($productos);
        $cCount = count($clientes);
        $prvCount = count($proveedores);
        return [
            'success' => true,
            'data'    => ['productos' => $productos, 'clientes' => $clientes, 'proveedores' => $proveedores],
            'message' => "Búsqueda global '{$t}': {$pCount} producto(s), {$cCount} cliente(s), {$prvCount} proveedor(es).",
        ];
    }

    private function obtenerAyuda(array $args): array
    {
        $modulo = $args['modulo'] ?? null;
        $ayuda = [
            'pedidos'        => 'Crear, listar, completar, cancelar y eliminar pedidos. Ver estadísticas y pedidos del día.',
            'clientes'       => 'Registrar, buscar, actualizar clientes y ver su historial.',
            'productos'      => 'Consultar stock, buscar productos, registrar ajustes y listar bajo stock.',
            'proveedores'    => 'Crear, buscar, actualizar y listar proveedores.',
            'ordenes_compra' => 'Crear órdenes de compra, marcarlas como recibidas, cancelarlas y ver estadísticas.',
            'kardex'         => 'Consultar Kárdex de un producto y listar movimientos de inventario.',
            'reportes'       => 'Estadísticas de ventas, top productos, top clientes y reporte de inventario.',
            'alertas'        => 'Listar alertas, marcar como leídas y notificar pedidos por expirar.',
        ];
        $respuesta = $modulo && isset($ayuda[$modulo]) ? [$modulo => $ayuda[$modulo]] : $ayuda;
        return ['success' => true, 'data' => $respuesta, 'message' => 'Catálogo de capacidades de Valencia AI.'];
    }

    private function listarGraficosDisponibles(array $args): array
    {
        $graficos = [
            [
                'id'          => 'ventas_por_dia',
                'titulo'      => '📈 Evolución Diaria de Ventas',
                'descripcion' => 'Tendencia y monto total facturado por día en Soles (S/).',
                'query'       => 'Muestra el gráfico de ventas de los últimos 7 días',
            ],
            [
                'id'          => 'top_productos',
                'titulo'      => '🏆 Top Productos Más Vendidos',
                'descripcion' => 'Ranking de productos con mayor demanda y rotación.',
                'query'       => 'Muestra el gráfico de los top productos más vendidos',
            ],
            [
                'id'          => 'top_clientes',
                'titulo'      => '👥 Top Clientes',
                'descripcion' => 'Clientes con mayor volumen de compras y facturación.',
                'query'       => 'Muestra el gráfico de top clientes',
            ],
            [
                'id'          => 'pedidos_por_estado',
                'titulo'      => '📦 Pedidos por Estado',
                'descripcion' => 'Distribución porcentual de pedidos completados, pendientes y cancelados.',
                'query'       => 'Muestra el gráfico de pedidos por estado',
            ],
            [
                'id'          => 'pedidos_por_canal',
                'titulo'      => '🚚 Pedidos por Canal de Venta',
                'descripcion' => 'Comparación de ventas por WhatsApp, Tienda y Web.',
                'query'       => 'Muestra el gráfico de pedidos por canal',
            ],
            [
                'id'          => 'stock_critico',
                'titulo'      => '⚠️ Stock Crítico y Alertas',
                'descripcion' => 'Productos agotados o por debajo del stock mínimo recomendado.',
                'query'       => 'Muestra el gráfico de productos con stock crítico',
            ],
            [
                'id'          => 'kpis_gestion',
                'titulo'      => '🎯 KPIs de Gestión (PODE, PEOR, PRS)',
                'descripcion' => 'Indicadores oficiales de entrega a tiempo, errores y roturas.',
                'query'       => 'Consulta los KPIs del mes actual',
            ],
        ];

        $mensaje = "📊 **Centro de Estadísticas y Gráficos Disponibles**\n\n";
        $mensaje .= "Valencia AI puede generar los siguientes reportes y análisis visuales en tiempo real:\n\n";
        foreach ($graficos as $idx => $g) {
            $num = $idx + 1;
            $mensaje .= "{$num}. **{$g['titulo']}**: {$g['descripcion']}\n";
        }
        $mensaje .= "\n*Elige la opción que desees haciendo clic en los botones de abajo o escríbelo directamente:*";

        $chips = array_map(fn($g) => [
            'label' => $g['titulo'],
            'query' => $g['query'],
        ], $graficos);

        return [
            'success' => true,
            'status'  => 'awaiting_input',
            'data'    => $graficos,
            'chips'   => $chips,
            'message' => $mensaje,
        ];
    }
}