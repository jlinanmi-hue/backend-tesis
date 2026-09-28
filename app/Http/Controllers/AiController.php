<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiChatRequest;
use App\Http\Requests\AiGenerateRequest;
use App\Models\AiGenerationFeedback;
use App\Models\AiIntentLog;
use App\Models\AiToolLog;
use App\Services\GeminiService;
use App\Services\GeminiToolsService;
use App\Services\OpenAiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiController extends Controller
{
    /**
     * System Instruction: define el rol de Valencia AI.
     */
    private const SYSTEM_INSTRUCTION = <<<'PROMPT'
Eres "Valencia AI", el asistente inteligente y copiloto operativo de Comercial Valencia, conectado a la base de datos en tiempo real.

REGLAS GENERALES Y ESTILO:
1. Respuestas amables, concisas y profesionales en español. Comunica números y datos concretos, nunca frases técnicas genéricas.
2. Extrae parámetros del contexto antes de solicitar datos al usuario. Si falta un campo crítico, usa solicitar_campo_faltante.
3. Para acciones destructivas (cancelar pedido, eliminar cliente, ajustar stock), pide confirmación explícita previa.
4. MANEJO DE CONFIRMACIONES: Cuando el usuario confirme una acción previa (ej: "sí", "confirmo", "si confirmo", "hazlo", "procede", "cancélalos", "adelante"): DEBES invocar DE INMEDIATO la herramienta correspondiente (ej: cancelar_pedido con los IDs mencionados o consultados previamente, completar_pedido, crear_pedido_cliente, etc.). NUNCA digas que no tienes permisos ni afirmes que no tienes la herramienta disponible.

REGLAS DE DOMINIO Y HERRAMIENTAS:
1. BI & ANÁLISIS VISUAL:
   - Si el usuario solicita "estadísticas generales", pregunta qué gráficos o reportes están disponibles o quiere conocer las opciones: ejecuta OBLIGATORIAMENTE listar_graficos_disponibles(). NUNCA generes un gráfico aleatorio de golpe sin que el usuario elija primero de la lista.
   - Para gráficos o métricas visuales específicas: ejecuta generar_grafico_dashboard(tipo_grafico, metrica). Elige el gráfico adecuado (comparación→bar/horizontal_bar, proporciones→pie/donut, evolución→line/area).
   - Para KPIs oficiales (PODE, PEOR, PRS, TBPP, TSA): ejecuta consultar_kpi(indicador).
   - Para tablas de datos: ejecuta renderizar_tabla(entidad, filtros).
   - Política obligatoria: acompaña siempre el gráfico con un breve resumen ejecutivo en texto.
2. COPILOTO DE PEDIDOS (NUEVO PEDIDO):
   - Flujo estándar OBLIGATORIO: Cuando el usuario exprese la intención de crear, preparar o armar un pedido (ej: "crear pedido de 2 guarana paquete x 15 y 10 pepsi x 6", "haz un pedido para Don Juan", "quiero pedir...", etc.), DEBES invocar DIRECTAMENTE abrir_formulario_pedido.
   - NUNCA uses buscar_global ni buscar_producto para crear o armar pedidos: abrir_formulario_pedido se encarga internamente de resolver los nombres, códigos y presentaciones en el backend.
   - Extrae del historial de la conversación el cliente reciente si ya fue identificado o mencionado (ej: si antes se habló de Don Juan / CLI-00015, pásalo como cliente_id: 'CLI-00015' y cliente_nombre: 'Don Juan').
   - Pasa los productos solicitados en el array 'detalles' con:
     - producto_id: el nombre o ID del producto tal como lo dijo el usuario (ej: 'guarana', 'pepsi')
     - cantidad: la cantidad numérica solicitada (ej: 2, 10)
     - factor_conversion: el factor o tamaño si fue indicado (ej: 15 para "paquete x 15", 6 para "x 6")
     - unidad_id: la presentación si se indicó (ej: "paquete", "six-pack", etc.)
   - Guardado directo: SOLO ejecuta crear_pedido_cliente ante confirmación 100% explícita del usuario ("confirmo", "guarda el pedido", "procede").
   - Limpieza: si el usuario pide vaciar o limpiar el borrador, ejecuta limpiar_formulario_pedido.
3. COPILOTO DE ÓRDENES DE COMPRA (PROVEEDORES):
   - Flujo estándar: usa abrir_formulario_orden_compra para precargar en pantalla proveedor y productos sugeridos.
   - PROVEEDOR NO REGISTRADO CON ORDEN EN CURSO: Si el usuario intenta armar una orden de compra para un proveedor no registrado en el sistema (ej. "haz una orden de compra a Distribuidora Los Andes de 20 cajas de aceite"), abrir_formulario_orden_compra retendrá automáticamente los productos en un borrador temporal y notificará que el proveedor no fue encontrado. En ese caso:
     a) Comunica al usuario que el proveedor no está registrado pero que sus productos están guardados temporalmente en el borrador por 30 minutos.
     b) Si el usuario proporciona los datos del proveedor o confirma registrarlo, invoca crear_proveedor o abrir_formulario_proveedor.
     c) Al registrarse el proveedor o seleccionarse un proveedor alternativo, el sistema cargará automáticamente la orden pendiente asociada.
   - Guardado directo: SOLO ejecuta crear_orden_compra ante confirmación 100% explícita del usuario ("confirmo", "guarda la orden", "procede").
   - Limpieza y descarte: si el usuario pide vaciar o descartar el borrador, usa limpiar_formulario_orden_compra.
   - Recuperación de borrador de compra: si el usuario descartó la orden por error ("deshacer", "recupera el borrador de compra", "restaura la orden"), ejecuta recuperar_borrador_orden_compra.
   - DESAMBIGUACIÓN DE PROVEEDORES: Si existen 2 o más proveedores similares o que comparten nombre core (ej. "Gloria S.A." vs "Distribuidora Gloria S.A.C."), PREGUNTA al usuario cuál desea antes de asignar la orden.
4. REGISTRO DE CLIENTES Y RETENCIÓN DE PEDIDOS:
   - Requiere ÚNICAMENTE 4 campos: documento (DNI 8 dígitos o RUC 11 dígitos, comodín '11110000'), nombre o razón social, teléfono y dirección. No pedir correo ni tipo de documento.
   - CLIENTES NO REGISTRADOS CON PEDIDO EN CURSO: Si el usuario intenta armar un pedido para un cliente que no existe en el sistema (ej. "hazle un pedido a Josue de 10 paquetes de pepsi"), abrir_formulario_pedido retendrá automáticamente los productos en un borrador pendiente y notificará que el cliente no fue encontrado. En ese caso:
     a) Comunica al usuario que el cliente no está registrado pero que sus productos ya están guardados en el borrador temporal.
     b) Si el usuario proporciona los datos del cliente o confirma registrarlo, invoca crear_cliente o abrir_formulario_cliente.
     c) Al registrarse el cliente, el sistema cargará automáticamente el pedido pendiente asociado con los productos seleccionados.
   - RECUPERACIÓN DE BORRADOR: Si el usuario descartó un pedido por error y pide restaurarlo ("deshacer", "recupera el borrador", "restaura el pedido"), ejecuta recuperar_borrador_pedido.
5. DESAMBIGUACIÓN OBLIGATORIA:
   - Si existen 2 o más productos que coinciden (ej. presentaciones de 1.5L vs 2L), PREGUNTA al usuario cuál desea antes de abrir formulario o crear el pedido.
6. CONSULTAS FRECUENTES:
   - Conteo o resumen de pedidos → obtener_estadisticas_pedidos.
   - Pedidos de hoy → obtener_pedidos_del_dia.
   - Stock o inventario → consultar_stock o listar_productos_bajo_stock.
7. POLÍTICA ESTRICTA DE VOCABULARIO (ENFOQUE EXCLUSIVO EN ÓRDENES):
   - NUNCA uses la palabra "ventas" en tus respuestas, explicaciones ni mensajes dirigidos al usuario.
   - Todo el modelo operativo de Comercial Valencia se expresa en ÓRDENES: "órdenes de clientes", "órdenes de pedido", "órdenes facturadas", "órdenes de compra", "órdenes de movimiento de stock" y "órdenes de ajuste".
   - Si consultas datos internos de herramientas o APIs con campos llamados "ventas" (ej: total_ventas, ventas_del_dia, ventas_por_dia), preséntalos SIEMPRE al usuario como "total de órdenes", "órdenes facturadas" u "órdenes del período".
8. SUGERENCIAS DE REABASTECIMIENTO (PREDICCIONES DE COMPRA):
   - Si el usuario pregunta qué debe comprar, cuánto reponer, qué falta en stock o cuál es la demanda semanal de un producto, usa obtener_predicciones_compra.
   - FLUJO OBLIGATORIO cuando el usuario menciona un producto por nombre:
     a) Ejecuta PRIMERO buscar_producto con el nombre mencionado (ej. "inca kola").
     b) Si buscar_producto devuelve EXACTAMENTE 1 producto: usa su ProductoId para invocar obtener_predicciones_compra(producto_id: 'PROD-XXXXX').
     c) Si devuelve MÚLTIPLES coincidencias: NO elijas por tu cuenta. Presenta los productos encontrados como chips de selección para que el usuario elija cuál desea consultar. NUNCA inventes ni supongas un ProductoId.
     d) Si devuelve 0 resultados: informa que el producto no fue encontrado y pide que el usuario revise el nombre.
   - FLUJO para consultas generales (sin producto específico):
     a) Si el usuario pide el resumen general: ejecuta obtener_predicciones_compra sin filtros (mostrará los N más urgentes).
     b) Si el usuario pide por categoría: ejecuta obtener_predicciones_compra(categoria_id: 'CAT-XXXXX'). Si no tienes el ID, primero ejecuta listar_categorias para encontrarlo.
   - Presenta los resultados de forma clara: nombre del producto, cantidad a pedir (empaque y unidad base), precio referencia y total estimado. Resalta los productos en quiebre o bajo mínimo.
   - REGLA DE TOTAL S/ 0.00 O STOCK CUBIERTO:
     Si la herramienta indica que ningún producto requiere reposición o la inversión estimada es S/ 0.00, explica claramente que el inventario actual y las órdenes en tránsito cubren con holgura la demanda proyectada semanal (1.5 semanas), y que Comercial Valencia NO requiere generar órdenes de compra esta semana. NUNCA afirmes que "hay productos para reponer" si la cantidad sugerida es 0.
PROMPT;

    /**
     * Maximo de reintentos de comunicacion con Gemini.
     */
    private const MAX_RETRIES = 1;

    public function __construct(
        protected GeminiService      $geminiService,
        protected OpenAiService      $openAiService,
        protected GeminiToolsService $toolsService
    ) {}

    /**
     * Obtiene el motor de IA activo (OpenAI GPT-5.5 o Google Gemini).
     */
    protected function getAiService(): GeminiService|OpenAiService
    {
        $provider = config('services.ai.provider', env('AI_PROVIDER', 'openai'));
        if ($provider === 'openai' && !empty(config('services.openai.api_key'))) {
            return $this->openAiService;
        }
        return $this->geminiService;
    }

    /**
     * Chat con Function Calling completo (ciclo optimizado de baja latencia).
     * POST /api/ai/chat
     */
    public function chat(Request $request): JsonResponse
    {
        set_time_limit(90);
        $turnStartTime = microtime(true);

        try {
            $message          = (string) ($request->input('message') ?? $request->input('prompt', ''));
            $rawHistory       = $request->input('history', []);
            $enableTools      = $request->boolean('enable_tools', true);
            $readOnlyMode     = $request->boolean('read_only', false);
            $systemInstruction = $this->buildSystemInstruction($request->input('system_instruction'));

            // Timeout de sesion conversacional (30 min de inactividad)
            $sessionId    = (string) ($request->input('session_id') ?: Str::uuid());
            $sessionKey   = "ai_session_activity:{$sessionId}";
            $lastActivity = Cache::get($sessionKey);

            // Limite de tokens de contexto: solo ultimos 8 mensajes para maxima velocidad
            $history = array_slice($rawHistory, -8);

            if ($lastActivity && Carbon::parse($lastActivity)->diffInMinutes(now()) > 30) {
                $history = [];
                Log::info("Valencia AI: Sesion {$sessionId} expirada por inactividad (>30 min). Reiniciando contexto.");
            }
            Cache::put($sessionKey, now()->toIso8601String(), now()->addMinutes(30));

            if (empty(trim($message))) {
                return response()->json(['success' => false, 'data' => null, 'message' => 'El mensaje no puede estar vacio.'], 422);
            }

            // Sincronización en vivo con la pantalla activa del usuario (borrador de orden/pedido)
            $liveDraft = $request->input('live_draft');

            // --- CAPA 1+2: Construir contents y primera llamada a Gemini ---
            $contents = $this->buildContentsFromHistory($history);

            if (!empty($liveDraft) && is_array($liveDraft) && !empty($liveDraft['detalles']) && is_array($liveDraft['detalles'])) {
                $tipoDoc = ($liveDraft['tipo'] ?? '') === 'orden_compra' ? 'orden de compra' : 'pedido de cliente';
                $entidad = ($liveDraft['tipo'] ?? '') === 'orden_compra'
                    ? "Proveedor: " . ($liveDraft['proveedor_nombre'] ?? $liveDraft['proveedor_id'] ?? 'No especificado')
                    : "Cliente: " . ($liveDraft['cliente_nombre'] ?? $liveDraft['cliente_id'] ?? 'No especificado');

                $lineasProductos = [];
                $totalDraft = 0;
                foreach ($liveDraft['detalles'] as $idx => $item) {
                    $nom = $item['nombre'] ?? $item['producto_nombre'] ?? $item['producto_id'] ?? ("Producto " . ($idx + 1));
                    $cant = (float)($item['cantidad'] ?? 1);
                    $pu = (float)($item['precio_unitario'] ?? 0);
                    $sub = (float)($item['subtotal'] ?? ($cant * $pu));
                    $totalDraft += $sub;
                    $lineasProductos[] = "- [{$item['producto_id']}] {$nom} | Cantidad: {$cant} | P.U: S/ " . number_format($pu, 2) . " | Subtotal: S/ " . number_format($sub, 2);
                }
                $txtProds = implode("\n", $lineasProductos);

                $liveScreenContext = "[ESTADO EN TIEMPO REAL EN LA PANTALLA DEL USUARIO]:\n" .
                    "El usuario tiene actualmente abierto en su pantalla el formulario de {$tipoDoc}.\n" .
                    "{$entidad}\n" .
                    "Productos actuales en la tabla (" . count($liveDraft['detalles']) . "):\n" .
                    "{$txtProds}\n" .
                    "Total en pantalla: S/ " . number_format($totalDraft, 2) . "\n\n" .
                    "REGLAS CRÍTICAS DE CONFIRMACIÓN Y PANTALLA:\n" .
                    "1. La lista anterior refleja exactamente lo que el usuario tiene en pantalla.\n" .
                    "2. VALIDACIÓN ESTRICTA: NUNCA llames a '" . (($liveDraft['tipo'] ?? '') === 'orden_compra' ? 'crear_orden_compra' : 'crear_pedido_cliente') . "' ante frases ambiguas como 'el pedido', 'la orden', silencios o preguntas.\n" .
                    "3. SOLO si el usuario te da una confirmación 100% explícita e inequívoca ('sí', 'confirmo', 'guarda el pedido', 'procede'), llama a la herramienta correspondiente incluyendo OBLIGATORIAMENTE TODOS los productos y cantidades de su pantalla.\n" .
                    "4. Si el mensaje es ambiguo, pregunta cordialmente si desea confirmar el borrador mostrado en pantalla.";

                $contents[] = [
                    'role'  => 'user',
                    'parts' => [['text' => $liveScreenContext]],
                ];
                $contents[] = [
                    'role'  => 'model',
                    'parts' => [['text' => "Entendido. Tengo registrado el estado actual de la pantalla ({$tipoDoc}) con sus " . count($liveDraft['detalles']) . " productos. Esperaré confirmación explícita antes de guardar en base de datos."]],
                ];
            }

            $contents[] = [
                'role'  => 'user',
                'parts' => [['text' => $message]],
            ];

            $contextQuery = $this->buildContextQuery($message, $rawHistory);
            $tools = $enableTools ? $this->getAiService()->getRegisteredTools($readOnlyMode, $contextQuery) : [];
            $filterMeta = $this->getAiService()->getLastFilterMetadata();
            $intent = $filterMeta['intent'] ?? 'general';
            $toolsSent = $filterMeta['tools_sent'] ?? (isset($tools[0]['functionDeclarations']) ? count($tools[0]['functionDeclarations']) : count($tools));
            $complexity = $this->getAiService()->detectarComplejidad($contextQuery, $intent);
            $customTimeout = $complexity['timeout_seconds'];
            $maxTurnSeconds = (float) $customTimeout;
            $maxTokensLimit = 100000;
            $totalAccumulatedTokens = 0;
            $deadline = $turnStartTime + $maxTurnSeconds;

            // --- MEJORA #7: Caché Semántico / Normalizado de consultas (TTL 60s) ---
            $isCacheCandidate = empty($history) && empty($liveDraft) && $this->esCacheable($message, $intent);
            $epoch = (int) Cache::get('ai_cache_epoch', 1);
            $normQuery = $this->normalizarQuery($message);
            $cacheKey = "ai_query_cache:v{$epoch}:" . md5($normQuery);

            if ($isCacheCandidate && Cache::has($cacheKey)) {
                $cachedPayload = Cache::get($cacheKey);
                if (is_array($cachedPayload) && !empty($cachedPayload['data'])) {
                    $totalTurnMs = round((microtime(true) - $turnStartTime) * 1000, 2);

                    Log::info("AI Chat Performance", [
                        'intencion'      => $intent,
                        'complexity'     => $complexity['nivel'],
                        'payload_kb'     => 0,
                        'tools_enviadas' => 0,
                        'gemini_ms'      => 0,
                        'tool_ms'        => 0,
                        'total_ms'       => (int) $totalTurnMs,
                        'cache_hit'      => true,
                    ]);

                    $this->recordIntentLog([
                        'session_id'    => $sessionId,
                        'intent'        => $intent,
                        'query'         => $message,
                        'complexity'    => $complexity['nivel'],
                        'model_used'    => $cachedPayload['data']['model'] ?? 'gemini-flash-lite-latest',
                        'tool_used'     => $cachedPayload['data']['tool_used'] ?? null,
                        'tools_sent'    => 0,
                        'tokens_used'   => 0,
                        'gemini_ms'     => 0,
                        'tool_ms'       => 0,
                        'total_ms'      => (int) $totalTurnMs,
                        'cache_hit'     => true,
                        'success'       => true,
                    ]);

                    $cachedPayload['data']['total_duration_ms'] = $totalTurnMs;
                    $cachedPayload['data']['cache_hit'] = true;
                    $cachedPayload['data']['session_id'] = $sessionId;

                    return response()->json($cachedPayload, 200);
                }
            }

            $geminiTotalMs = 0;
            $toolTotalMs = 0;

            $callStart = microtime(true);
            $currentResponse = $this->callGeminiWithRetry(function () use ($contents, $systemInstruction, $tools, $customTimeout) {
                return $this->getAiService()->generateContent($contents, $systemInstruction, $tools, [], $customTimeout);
            }, 1, $deadline);
            $firstCallMs = round((microtime(true) - $callStart) * 1000);
            $geminiTotalMs += $firstCallMs;

            $totalAccumulatedTokens += ($currentResponse['usage']['totalTokenCount'] ?? 0);

            // --- CAPA 3 & 4: Ciclo de Function Calling multi-herramienta (máximo 3 iteraciones) ---
            $iteration = 0;
            $maxIterations = 3;
            $toolsExecuted = [];
            $executedSignatures = [];
            $lastToolName = null;
            $lastToolResult = null;
            $primaryToolName = null;
            $primaryToolResult = null;
            $capturedUiAction = null;
            $lastToolLogId = null;
            $capturedChips = null;

            while (!empty($currentResponse['has_function_calls']) && !empty($currentResponse['function_calls']) && $iteration < $maxIterations) {
                $iteration++;
                $iterStartTime = microtime(true);
                $allCalls = $currentResponse['function_calls'];
                $executedInThisTurn = [];

                // FIX 1.5: Iterar sobre TODOS los functionCalls del turno (no solo el primero)
                foreach ($allCalls as $fc) {
                    $toolName = $fc['name'];
                    $toolArgs = $fc['args'] ?? [];

                    // Control contra loops infinitos (firma tool + args)
                    $signature = $toolName . ':' . md5(json_encode($toolArgs));
                    if (in_array($signature, $executedSignatures, true)) {
                        Log::warning("Valencia AI: Loop de tool detectado ({$toolName}). Saltando.");
                        continue;
                    }
                    $executedSignatures[] = $signature;

                    // Ejecutar la tool en el backend
                    $tToolStart = microtime(true);
                    $toolResult = $this->toolsService->dispatch($toolName, $toolArgs, $readOnlyMode, $liveDraft, $message, $sessionId);
                    $iterDurationMs = round((microtime(true) - $tToolStart) * 1000, 2);
                    $toolTotalMs += $iterDurationMs;

                    // MEJORA #1: Si la tool no devuelve message ni ui_action -> sintetizar con Gemini
                    if (empty($toolResult['message']) && empty($toolResult['ui_action'])) {
                        $toolResult['message'] = $this->sintetizarConGemini($toolResult, $message, $deadline);
                    }

                    $toolsExecuted[] = [
                        'tool'        => $toolName,
                        'args'        => $toolArgs,
                        'success'     => $toolResult['success'] ?? false,
                        'status'      => $toolResult['status'] ?? 'executed',
                        'duration_ms' => $iterDurationMs,
                    ];

                    $lastToolName = $toolName;
                    $lastToolResult = $toolResult;

                    // Auditoria de Tesis: Registrar ejecucion en ai_tool_logs
                    try {
                        $logPedidoId = $toolArgs['pedido_id'] ?? ($toolResult['data']['pedido_id'] ?? ($toolResult['data']['PedidoId'] ?? null));
                        $promptTokens = $currentResponse['usage']['promptTokenCount'] ?? 0;
                        $candidatesTokens = $currentResponse['usage']['candidatesTokenCount'] ?? 0;
                        $isOp = config('services.ai.provider') === 'openai';
                        $promptPrice = $isOp ? config('services.openai.pricing.prompt_per_million', 1.50) : config('services.gemini.pricing.prompt_per_million', 0.075);
                        $candidatesPrice = $isOp ? config('services.openai.pricing.candidates_per_million', 6.00) : config('services.gemini.pricing.candidates_per_million', 0.30);
                        $costUsd = round(($promptTokens * $promptPrice / 1_000_000) + ($candidatesTokens * $candidatesPrice / 1_000_000), 6);

                        $createdAiLog = AiToolLog::create([
                            'user_id'           => auth()->id() ?? null,
                            'session_id'        => $sessionId,
                            'tool_name'         => $toolName,
                            'pedido_id'         => $logPedidoId,
                            'args'              => $toolArgs,
                            'result'            => $toolResult,
                            'status'            => ($toolResult['success'] ?? false) ? 'success' : (($toolResult['status'] ?? '') === 'missing_fields' ? 'missing_fields' : 'error'),
                            'duration_ms'       => (int) $iterDurationMs,
                            'prompt_tokens'     => $promptTokens,
                            'candidates_tokens' => $candidatesTokens,
                            'total_tokens'      => $currentResponse['usage']['totalTokenCount'] ?? 0,
                            'cost_usd'          => $costUsd,
                            'error_message'     => ($toolResult['success'] ?? false) ? null : substr($toolResult['message'] ?? 'Error desconocido', 0, 500),
                        ]);
                        $lastToolLogId = $createdAiLog->id;
                    } catch (\Throwable $eLog) {
                        Log::warning("Valencia AI: Error al registrar ai_tool_logs", ['err' => $eLog->getMessage()]);
                    }

                    if (!empty($toolResult['ui_action'])) {
                        $capturedUiAction = $toolResult['ui_action'];
                    }

                    if (!empty($toolResult['chips'])) {
                        $capturedChips = $toolResult['chips'];
                    }

                    if (in_array($toolResult['status'] ?? '', ['ambiguous_product', 'ambiguous_supplier', 'reassign_confirmation_needed'], true)) {
                        $primaryToolName = $toolName;
                        $primaryToolResult = $toolResult;
                        $capturedUiAction = null;
                        $capturedChips = $toolResult['chips'] ?? $capturedChips;
                    } elseif (in_array($toolName, [
                        'abrir_formulario_pedido', 'crear_pedido_cliente', 'limpiar_formulario_pedido', 'actualizar_pedido',
                        'abrir_formulario_orden_compra', 'crear_orden_compra', 'limpiar_formulario_orden_compra', 'recuperar_borrador_orden_compra',
                        'crear_cliente', 'abrir_formulario_cliente', 'crear_proveedor', 'abrir_formulario_proveedor'
                    ], true) || empty($primaryToolName)) {
                        $primaryToolName = $toolName;
                        $primaryToolResult = $toolResult;
                    }

                    $executedInThisTurn[] = [
                        'name'   => $toolName,
                        'result' => $toolResult,
                    ];
                }

                if (empty($executedInThisTurn)) {
                    Log::warning("Valencia AI: Ninguna tool nueva ejecutada en iter {$iteration}. Deteniendo loop.");
                    break;
                }

                // Parada ante situaciones que requieren interacción directa del usuario o modal UI
                $requiresUserStop = false;
                foreach ($executedInThisTurn as $exec) {
                    $st = $exec['result']['status'] ?? '';
                    if (in_array($st, ['missing_fields', 'pending_confirmation', 'awaiting_input', 'ambiguous_product', 'ambiguous_supplier', 'supplier_not_found', 'reassign_confirmation_needed', 'generic_supplier_not_configured'], true) || !empty($exec['result']['ui_action'])) {
                        $requiresUserStop = true;
                        break;
                    }
                }

                // Si la tool requiere acción del usuario o modal UI, finalizar el turno inmediatamente con su mensaje
                if ($requiresUserStop) {
                    $lastExec = end($executedInThisTurn);
                    $lastRes = $lastExec['result'] ?? [];
                    $currentResponse['text'] = $lastRes['message'] ?? 'Operación completada con éxito.';
                    $currentResponse['has_function_calls'] = false;
                    $currentResponse['function_calls'] = [];
                    break;
                }

                // Incorporar el turno con TODOS los functionResponses correspondientes
                $contents = $this->getAiService()->appendMultipleFunctionTurns(
                    $contents,
                    $currentResponse['raw_candidate'] ?? [],
                    $executedInThisTurn
                );

                // Verificar condiciones de guarda: límite de tiempo o tokens
                $elapsedSeconds = microtime(true) - $turnStartTime;
                $timeExceeded = $elapsedSeconds >= $maxTurnSeconds;
                $tokensExceeded = $totalAccumulatedTokens >= $maxTokensLimit;

                if ($requiresUserStop || $timeExceeded || $tokensExceeded) {
                    if ($timeExceeded) {
                        Log::warning("Valencia AI: Timeout global del turno alcanzado ({$elapsedSeconds}s). Deteniendo encadenamiento.");
                    }
                    break;
                }

                $availableToolsForNextTurn = ($iteration >= $maxIterations) ? [] : $tools;

                try {
                    $secStart = microtime(true);
                    $currentResponse = $this->callGeminiWithRetry(function () use ($contents, $systemInstruction, $availableToolsForNextTurn, $customTimeout) {
                        return $this->getAiService()->generateContent($contents, $systemInstruction, $availableToolsForNextTurn, [], $customTimeout);
                    }, 1, $deadline);
                    $geminiTotalMs += round((microtime(true) - $secStart) * 1000);
                    $totalAccumulatedTokens += ($currentResponse['usage']['totalTokenCount'] ?? 0);
                } catch (\Throwable $eSec) {
                    Log::warning("Valencia AI: Síntesis en chat omitida ({$eSec->getMessage()}). Usando mensaje de tool.");
                    $currentResponse = ['text' => $toolResult['message'] ?? 'Operación completada con éxito.'];
                    break;
                }
            }

            // Si se agotaron las iteraciones y Gemini aún quería llamar tools, forzar síntesis en texto final
            if ($iteration >= $maxIterations && !empty($currentResponse['has_function_calls'])) {
                Log::warning("Valencia AI: Límite de {$maxIterations} iteraciones alcanzado con tool pendiente. Forzando síntesis de texto.");
                $secStart = microtime(true);
                $currentResponse = $this->callGeminiWithRetry(function () use ($contents, $systemInstruction, $customTimeout) {
                    return $this->getAiService()->generateContent($contents, $systemInstruction, [], [], $customTimeout);
                }, 1, $deadline);
                $geminiTotalMs += round((microtime(true) - $secStart) * 1000);
                $totalAccumulatedTokens += ($currentResponse['usage']['totalTokenCount'] ?? 0);
            }

            $finalToolName = $primaryToolName ?? $lastToolName;
            $finalToolResult = $primaryToolResult ?? $lastToolResult;

            // Extraer respuesta en lenguaje natural sintetizada por Gemini
            $reply = trim($currentResponse['text'] ?? '');
            if (($finalToolResult['status'] ?? '') === 'ambiguous_product') {
                if (empty($reply) || (!str_contains($reply, '?') && !str_contains($reply, '¿'))) {
                    $reply = $finalToolResult['message'] ?? 'Se encontraron varios productos coincidentes. Por favor indica cuál deseas.';
                }
            } elseif (empty($reply)) {
                $reply = $primaryToolResult['message'] ?? $lastToolResult['message'] ?? 'Operación completada.';
            }

            // Fallback extremo si reply sigue vacío
            if (empty($reply) && !empty($finalToolResult)) {
                $reply = $this->sintetizarConGemini($finalToolResult, $message, $deadline);
            }

            $totalTurnMs = round((microtime(true) - $turnStartTime) * 1000, 2);
            $modelUsed = $currentResponse['model_version'] ?? config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest'));

            // MEJORA #4: Log estructurado de rendimiento
            Log::info("AI Chat Performance", [
                'intencion'      => $intent,
                'complexity'     => $complexity['nivel'],
                'payload_kb'     => round(strlen(json_encode($contents)) / 1024, 2),
                'tools_enviadas' => $toolsSent,
                'gemini_ms'      => (int) $geminiTotalMs,
                'tool_ms'        => (int) $toolTotalMs,
                'total_ms'       => (int) $totalTurnMs,
                'cache_hit'      => false,
            ]);

            // MEJORA #5: Registrar en ai_intent_logs
            $this->recordIntentLog([
                'session_id'    => $sessionId,
                'intent'        => $intent,
                'query'         => $message,
                'complexity'    => $complexity['nivel'],
                'model_used'    => $modelUsed,
                'tool_used'     => $finalToolName,
                'tools_sent'    => $toolsSent,
                'tokens_used'   => $totalAccumulatedTokens,
                'gemini_ms'     => (int) $geminiTotalMs,
                'tool_ms'       => (int) $toolTotalMs,
                'total_ms'      => (int) $totalTurnMs,
                'cache_hit'     => false,
                'success'       => true,
            ]);

            $responsePayload = [
                'success' => true,
                'data'    => [
                    'reply'            => $reply,
                    'response'         => $reply,
                    'tool_used'        => $finalToolName,
                    'tool_data'        => $finalToolResult['data'] ?? null,
                    'tool_success'     => $finalToolResult['success'] ?? true,
                    'tool_status'      => $finalToolResult['status'] ?? 'executed',
                    'log_id'           => $lastToolLogId,
                    'tool_log_id'      => $lastToolLogId,
                    'ui_action'        => $capturedUiAction ?? ($finalToolResult['ui_action'] ?? null),
                    'chips'            => $capturedChips ?? ($finalToolResult['chips'] ?? ($primaryToolResult['chips'] ?? null)),
                    'campos_faltantes' => $finalToolResult['campos_faltantes'] ?? null,
                    'tools_executed'   => $toolsExecuted,
                    'session_id'       => $sessionId,
                    'total_duration_ms'=> $totalTurnMs,
                    'usage'            => array_merge($currentResponse['usage'] ?? [], ['accumulated_tokens' => $totalAccumulatedTokens]),
                    'model'            => $modelUsed,
                ],
                'message' => !empty($toolsExecuted)
                    ? 'Respuesta generada con Function Calling (' . count($toolsExecuted) . ' herramientas ejecutadas en ' . round($totalTurnMs / 1000, 1) . 's).'
                    : 'Respuesta generada exitosamente en ' . round($totalTurnMs / 1000, 1) . 's.',
            ];

            // MEJORA #7: Almacenar en caché si es consulta histórica elegible y no falló
            if ($isCacheCandidate && ($finalToolResult['success'] ?? true)) {
                Cache::put($cacheKey, $responsePayload, now()->addSeconds(60));
            }

            return response()->json($responsePayload, 200);

        } catch (Exception $e) {
            Log::error('AiController::chat error', ['msg' => $e->getMessage()]);

            $totalTurnMs = round((microtime(true) - $turnStartTime) * 1000, 2);

            // MEJORA #5: Registrar error en ai_intent_logs
            $this->recordIntentLog([
                'session_id'    => $sessionId ?? Str::uuid(),
                'intent'        => $intent ?? 'general',
                'query'         => $message ?? '',
                'complexity'    => $complexity['nivel'] ?? 'media',
                'model_used'    => config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest')),
                'tool_used'     => $lastToolName ?? null,
                'tools_sent'    => $toolsSent ?? 0,
                'tokens_used'   => $totalAccumulatedTokens ?? 0,
                'gemini_ms'     => (int) ($geminiTotalMs ?? 0),
                'tool_ms'       => (int) ($toolTotalMs ?? 0),
                'total_ms'      => (int) $totalTurnMs,
                'cache_hit'     => false,
                'success'       => false,
                'error_message' => $e->getMessage(),
            ]);

            // RECOVERY TOTAL: Si cualquier herramienta ya se ejecutó con éxito en este turno
            // antes de que fallara cualquier síntesis o conexión posterior, responder con éxito al usuario
            // utilizando los datos y ui_action ya calculados por el sistema.
            if (!empty($lastToolName) && !empty($lastToolResult['success'])) {
                $fallbackReply = $lastToolResult['message'] ?? 'La operación fue registrada con éxito en el sistema.';

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'reply'             => $fallbackReply,
                        'response'          => $fallbackReply,
                        'tool_used'         => $lastToolName,
                        'tool_data'         => $lastToolResult['data'] ?? null,
                        'tool_success'      => true,
                        'tool_status'       => 'executed',
                        'ui_action'         => $capturedUiAction ?? ($lastToolResult['ui_action'] ?? null),
                        'chips'             => $capturedChips ?? ($lastToolResult['chips'] ?? null),
                        'tools_executed'    => $toolsExecuted ?? [],
                        'session_id'        => $sessionId ?? Str::uuid(),
                        'total_duration_ms' => $totalTurnMs,
                    ],
                    'message' => 'Operación completada exitosamente en el sistema.',
                ], 200);
            }

            $statusCode = str_contains($e->getMessage(), '429') ? 429 : 500;
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => $this->friendlyErrorMessage($e),
            ], $statusCode);
        }
    }

    /**
     * Chat con streaming SSE (Server-Sent Events) en tiempo real.
     * El usuario ve el progreso y el texto aparecer en 2-3s.
     * POST o GET /api/ai/chat-stream
     */
    public function chatStream(Request $request)
    {
        set_time_limit(90);

        $message           = (string) ($request->input('message') ?? $request->input('prompt', ''));
        $rawHistory        = $request->input('history', []);
        $enableTools       = $request->boolean('enable_tools', true);
        $readOnlyMode      = $request->boolean('read_only', false);
        $systemInstruction = $this->buildSystemInstruction($request->input('system_instruction'));
        $liveDraft         = $request->input('live_draft');
        $sessionId         = (string) ($request->input('session_id') ?: Str::uuid());

        // Manejar payload si viene como JSON string (ej: en peticiones GET o URLSearchParams)
        if (is_string($rawHistory)) {
            $rawHistory = json_decode($rawHistory, true) ?: [];
        }
        if (is_string($liveDraft)) {
            $liveDraft = json_decode($liveDraft, true) ?: null;
        }

        return response()->stream(function () use ($message, $rawHistory, $enableTools, $readOnlyMode, $systemInstruction, $liveDraft, $sessionId) {
            $sendEvent = function (string $event, array $data) {
                echo "event: {$event}\n";
                echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            if (empty(trim($message))) {
                $sendEvent('error', ['message' => 'El mensaje no puede estar vacío.']);
                return;
            }

            $turnStartTime = microtime(true);
            $history = array_slice($rawHistory, -8);
            $contextQuery = $this->buildContextQuery($message, $rawHistory);
            $tools = $enableTools ? $this->getAiService()->getRegisteredTools($readOnlyMode, $contextQuery) : [];
            $filterMeta = $this->getAiService()->getLastFilterMetadata();
            $intent = $filterMeta['intent'] ?? 'general';
            $toolsSent = $filterMeta['tools_sent'] ?? (isset($tools[0]['functionDeclarations']) ? count($tools[0]['functionDeclarations']) : count($tools));
            $complexity = $this->getAiService()->detectarComplejidad($contextQuery, $intent);
            $customTimeout = $complexity['timeout_seconds'];
            $maxTurnSeconds = (float) $customTimeout;
            $maxIterations = 3;
            $deadline = $turnStartTime + $maxTurnSeconds;

            // --- MEJORA #7: Caché Semántico / Normalizado de consultas en Stream (TTL 60s) ---
            $isCacheCandidate = empty($history) && empty($liveDraft) && $this->esCacheable($message, $intent);
            $epoch = (int) Cache::get('ai_cache_epoch', 1);
            $normQuery = $this->normalizarQuery($message);
            $cacheKey = "ai_query_cache:v{$epoch}:" . md5($normQuery);

            if ($isCacheCandidate && Cache::has($cacheKey)) {
                $cachedPayload = Cache::get($cacheKey);
                if (is_array($cachedPayload) && !empty($cachedPayload['data'])) {
                    $totalTurnMs = round((microtime(true) - $turnStartTime) * 1000, 2);
                    $cachedData = $cachedPayload['data'];
                    $cachedReply = $cachedData['reply'] ?? '';

                    $tokens = preg_split('/(\s+)/u', $cachedReply, -1, PREG_SPLIT_DELIM_CAPTURE);
                    foreach ($tokens as $t) {
                        if ($t !== '') {
                            $sendEvent('token', ['token' => $t]);
                            usleep(4_000);
                        }
                    }

                    $cachedData['total_duration_ms'] = $totalTurnMs;
                    $cachedData['cache_hit'] = true;
                    $cachedData['session_id'] = $sessionId;

                    $sendEvent('done', $cachedData);

                    Log::info("AI Chat Performance", [
                        'intencion'      => $intent,
                        'complexity'     => $complexity['nivel'],
                        'payload_kb'     => 0,
                        'tools_enviadas' => 0,
                        'gemini_ms'      => 0,
                        'tool_ms'        => 0,
                        'total_ms'       => (int) $totalTurnMs,
                        'cache_hit'      => true,
                    ]);

                    $this->recordIntentLog([
                        'session_id'    => $sessionId,
                        'intent'        => $intent,
                        'query'         => $message,
                        'complexity'    => $complexity['nivel'],
                        'model_used'    => $cachedData['model'] ?? 'gemini-flash-lite-latest',
                        'tool_used'     => $cachedData['tool_used'] ?? null,
                        'tools_sent'    => 0,
                        'tokens_used'   => 0,
                        'gemini_ms'     => 0,
                        'tool_ms'       => 0,
                        'total_ms'      => (int) $totalTurnMs,
                        'cache_hit'     => true,
                        'success'       => true,
                    ]);

                    return;
                }
            }

            $sendEvent('status', ['status' => 'thinking', 'message' => 'Analizando tu consulta...']);

            try {
                $contents = $this->buildContentsFromHistory($history);

                if (!empty($liveDraft) && is_array($liveDraft) && !empty($liveDraft['detalles']) && is_array($liveDraft['detalles'])) {
                    $tipoDoc = ($liveDraft['tipo'] ?? '') === 'orden_compra' ? 'orden de compra' : 'pedido de cliente';
                    $entidad = ($liveDraft['tipo'] ?? '') === 'orden_compra'
                        ? "Proveedor: " . ($liveDraft['proveedor_nombre'] ?? $liveDraft['proveedor_id'] ?? 'No especificado')
                        : "Cliente: " . ($liveDraft['cliente_nombre'] ?? $liveDraft['cliente_id'] ?? 'No especificado');

                    $lineasProductos = [];
                    $totalDraft = 0;
                    foreach ($liveDraft['detalles'] as $idx => $item) {
                        $nom = $item['nombre'] ?? $item['producto_nombre'] ?? $item['producto_id'] ?? ("Producto " . ($idx + 1));
                        $cant = (float)($item['cantidad'] ?? 1);
                        $pu = (float)($item['precio_unitario'] ?? 0);
                        $sub = (float)($item['subtotal'] ?? ($cant * $pu));
                        $totalDraft += $sub;
                        $lineasProductos[] = "- [{$item['producto_id']}] {$nom} | Cantidad: {$cant} | P.U: S/ " . number_format($pu, 2) . " | Subtotal: S/ " . number_format($sub, 2);
                    }
                    $txtProds = implode("\n", $lineasProductos);
                    $liveScreenContext = "[ESTADO EN TIEMPO REAL EN LA PANTALLA]:\nFormulario abierto: {$tipoDoc}\n{$entidad}\nProductos (" . count($liveDraft['detalles']) . "):\n{$txtProds}\nTotal: S/ " . number_format($totalDraft, 2) . "\n\n" .
                        "REGLAS CRÍTICAS DE CONFIRMACIÓN Y PANTALLA:\n" .
                        "1. VALIDACIÓN ESTRICTA: NUNCA llames a '" . (($liveDraft['tipo'] ?? '') === 'orden_compra' ? 'crear_orden_compra' : 'crear_pedido_cliente') . "' ante frases ambiguas como 'el pedido', 'la orden', silencios o preguntas.\n" .
                        "2. SOLO si el usuario te da una confirmación 100% explícita e inequívoca ('sí', 'confirmo', 'guarda el pedido', 'procede'), llama a la herramienta correspondiente.\n" .
                        "3. Si el mensaje es ambiguo, pregunta cordialmente si desea confirmar el borrador mostrado en pantalla.";

                    $contents[] = ['role' => 'user', 'parts' => [['text' => $liveScreenContext]]];
                    $contents[] = ['role' => 'model', 'parts' => [['text' => "Entendido. Tengo registrado el estado actual de la pantalla con sus " . count($liveDraft['detalles']) . " productos. Esperaré confirmación explícita antes de guardar en base de datos."]]];
                }

                $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

                $geminiTotalMs = 0;
                $toolTotalMs = 0;

                $callStart = microtime(true);
                $currentResponse = $this->callGeminiWithRetry(function () use ($contents, $systemInstruction, $tools, $customTimeout) {
                    return $this->getAiService()->generateContent($contents, $systemInstruction, $tools, [], $customTimeout);
                }, 1, $deadline);
                $firstCallMs = round((microtime(true) - $callStart) * 1000);
                $geminiTotalMs += $firstCallMs;

                $iteration = 0;
                $toolsExecuted = [];
                $executedSignatures = [];
                $lastToolName = null;
                $lastToolResult = null;
                $primaryToolName = null;
                $primaryToolResult = null;
                $capturedUiAction = null;
                $capturedChips = null;
                $lastStreamToolLogId = null;

                while (!empty($currentResponse['has_function_calls']) && !empty($currentResponse['function_calls']) && $iteration < $maxIterations) {
                    $iteration++;
                    $allCalls = $currentResponse['function_calls'];
                    $executedInTurn = [];

                    foreach ($allCalls as $fc) {
                        $toolName = $fc['name'];
                        $toolArgs = $fc['args'] ?? [];

                        $signature = $toolName . ':' . md5(json_encode($toolArgs));
                        if (in_array($signature, $executedSignatures, true)) {
                            continue;
                        }
                        $executedSignatures[] = $signature;

                        $sendEvent('status', [
                            'status'  => 'executing_tool',
                            'tool'    => $toolName,
                            'message' => 'Consultando ' . str_replace('_', ' ', $toolName) . '...',
                        ]);

                        $toolStart = microtime(true);
                        $toolResult = $this->toolsService->dispatch($toolName, $toolArgs, $readOnlyMode, $liveDraft, $message, $sessionId);
                        $toolDur = round((microtime(true) - $toolStart) * 1000, 2);
                        $toolTotalMs += $toolDur;

                        // MEJORA #1: Si la tool no devuelve message ni ui_action -> sintetizar con Gemini
                        if (empty($toolResult['message']) && empty($toolResult['ui_action'])) {
                            $toolResult['message'] = $this->sintetizarConGemini($toolResult, $message, $deadline);
                        }

                        $toolsExecuted[] = [
                            'tool'        => $toolName,
                            'args'        => $toolArgs,
                            'success'     => $toolResult['success'] ?? false,
                            'status'      => $toolResult['status'] ?? 'executed',
                            'duration_ms' => $toolDur,
                        ];

                        $lastToolName = $toolName;
                        $lastToolResult = $toolResult;

                        // Auditoria de Tesis: Registrar ejecucion en ai_tool_logs
                        try {
                            $logPedidoId = $toolArgs['pedido_id'] ?? ($toolResult['data']['pedido_id'] ?? ($toolResult['data']['PedidoId'] ?? null));
                            $promptTokens = $currentResponse['usage']['promptTokenCount'] ?? 0;
                            $candidatesTokens = $currentResponse['usage']['candidatesTokenCount'] ?? 0;
                            $isOp = config('services.ai.provider') === 'openai';
                            $promptPrice = $isOp ? config('services.openai.pricing.prompt_per_million', 1.50) : config('services.gemini.pricing.prompt_per_million', 0.075);
                            $candidatesPrice = $isOp ? config('services.openai.pricing.candidates_per_million', 6.00) : config('services.gemini.pricing.candidates_per_million', 0.30);
                            $costUsd = round(($promptTokens * $promptPrice / 1_000_000) + ($candidatesTokens * $candidatesPrice / 1_000_000), 6);

                            $createdStreamLog = AiToolLog::create([
                                'user_id'           => auth()->id() ?? null,
                                'session_id'        => $sessionId,
                                'tool_name'         => $toolName,
                                'pedido_id'         => $logPedidoId,
                                'args'              => $toolArgs,
                                'result'            => $toolResult,
                                'status'            => ($toolResult['success'] ?? false) ? 'success' : (($toolResult['status'] ?? '') === 'missing_fields' ? 'missing_fields' : 'error'),
                                'duration_ms'       => (int) $toolDur,
                                'prompt_tokens'     => $promptTokens,
                                'candidates_tokens' => $candidatesTokens,
                                'total_tokens'      => $currentResponse['usage']['totalTokenCount'] ?? 0,
                                'cost_usd'          => $costUsd,
                                'error_message'     => ($toolResult['success'] ?? false) ? null : substr($toolResult['message'] ?? 'Error desconocido', 0, 500),
                            ]);
                            $lastStreamToolLogId = $createdStreamLog->id;
                        } catch (\Throwable $eLog) {
                            Log::warning("Valencia AI: Error al registrar ai_tool_logs en stream", ['err' => $eLog->getMessage()]);
                        }

                        if (!empty($toolResult['ui_action'])) $capturedUiAction = $toolResult['ui_action'];
                        if (!empty($toolResult['chips'])) $capturedChips = $toolResult['chips'];

                        if (($toolResult['status'] ?? '') === 'ambiguous_product') {
                            $primaryToolName = $toolName;
                            $primaryToolResult = $toolResult;
                            $capturedUiAction = null;
                            $capturedChips = $toolResult['chips'] ?? $capturedChips;
                        } elseif (empty($primaryToolName)) {
                            $primaryToolName = $toolName;
                            $primaryToolResult = $toolResult;
                        }

                        $executedInTurn[] = ['name' => $toolName, 'result' => $toolResult];
                    }

                    if (empty($executedInTurn)) break;

                    $requiresUserStop = false;
                    foreach ($executedInTurn as $exec) {
                        $st = $exec['result']['status'] ?? '';
                        if (in_array($st, ['missing_fields', 'pending_confirmation', 'awaiting_input', 'ambiguous_product'], true) || !empty($exec['result']['ui_action'])) {
                            $requiresUserStop = true;
                            break;
                        }
                    }

                    // Si la tool requiere acción del usuario o modal UI, finalizar el turno inmediatamente con su mensaje
                    if ($requiresUserStop) {
                        $lastExec = end($executedInTurn);
                        $lastRes = $lastExec['result'] ?? [];
                        $currentResponse['text'] = $lastRes['message'] ?? 'Operación completada con éxito.';
                        $currentResponse['has_function_calls'] = false;
                        $currentResponse['function_calls'] = [];
                        break;
                    }

                    $contents = $this->getAiService()->appendMultipleFunctionTurns($contents, $currentResponse['raw_candidate'] ?? [], $executedInTurn);

                    $elapsed = microtime(true) - $turnStartTime;
                    $timeExceeded = $elapsed >= $maxTurnSeconds;

                    if ($requiresUserStop || $timeExceeded) break;

                    $availableToolsForNextTurn = ($iteration >= $maxIterations) ? [] : $tools;

                    try {
                        $secStart = microtime(true);
                        $currentResponse = $this->callGeminiWithRetry(function () use ($contents, $systemInstruction, $availableToolsForNextTurn, $customTimeout) {
                            return $this->getAiService()->generateContent($contents, $systemInstruction, $availableToolsForNextTurn, [], $customTimeout);
                        }, 1, $deadline);
                        $geminiTotalMs += round((microtime(true) - $secStart) * 1000);
                    } catch (\Throwable $eSec) {
                        Log::warning("Valencia AI: Síntesis de Gemini omitida ({$eSec->getMessage()}). Usando mensaje de tool.");
                        $currentResponse = ['text' => $toolResult['message'] ?? 'Operación completada con éxito.'];
                        break;
                    }
                }

                $sendEvent('status', ['status' => 'synthesizing', 'message' => 'Generando respuesta...']);

                $finalToolName = $primaryToolName ?? $lastToolName;
                $finalToolResult = $primaryToolResult ?? $lastToolResult;

                $reply = trim($currentResponse['text'] ?? '');
                if (($finalToolResult['status'] ?? '') === 'ambiguous_product') {
                    if (empty($reply) || (!str_contains($reply, '?') && !str_contains($reply, '¿'))) {
                        $reply = $finalToolResult['message'] ?? 'Se encontraron varios productos. Por favor indica cuál deseas.';
                    }
                } elseif (empty($reply)) {
                    $reply = $primaryToolResult['message'] ?? $lastToolResult['message'] ?? 'Operación completada.';
                }

                // Fallback extremo si reply sigue vacío
                if (empty($reply) && !empty($finalToolResult)) {
                    $reply = $this->sintetizarConGemini($finalToolResult, $message, $deadline);
                }

                // Enviar tokens de la respuesta de forma incremental
                $tokens = preg_split('/(\s+)/u', $reply, -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($tokens as $t) {
                    if ($t !== '') {
                        $sendEvent('token', ['token' => $t]);
                        usleep(12_000); // 12ms para fluidez visual rápida y natural
                    }
                }

                $totalTurnMs = round((microtime(true) - $turnStartTime) * 1000, 2);
                $modelUsed = $currentResponse['model_version'] ?? config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest'));

                $streamDoneData = [
                    'reply'            => $reply,
                    'response'         => $reply,
                    'tool_used'        => $finalToolName,
                    'tool_data'        => $finalToolResult['data'] ?? null,
                    'tool_success'     => $finalToolResult['success'] ?? true,
                    'tool_status'      => $finalToolResult['status'] ?? 'executed',
                    'log_id'           => $lastStreamToolLogId,
                    'tool_log_id'      => $lastStreamToolLogId,
                    'ui_action'        => $capturedUiAction ?? ($finalToolResult['ui_action'] ?? null),
                    'chips'            => $capturedChips ?? ($finalToolResult['chips'] ?? ($primaryToolResult['chips'] ?? null)),
                    'campos_faltantes' => $finalToolResult['campos_faltantes'] ?? null,
                    'tools_executed'   => $toolsExecuted,
                    'session_id'       => $sessionId,
                    'total_duration_ms'=> $totalTurnMs,
                    'model'            => $modelUsed,
                ];

                $sendEvent('done', $streamDoneData);

                // MEJORA #4: Log estructurado de rendimiento
                Log::info("AI Chat Performance", [
                    'intencion'      => $intent,
                    'complexity'     => $complexity['nivel'],
                    'payload_kb'     => round(strlen(json_encode($contents)) / 1024, 2),
                    'tools_enviadas' => $toolsSent,
                    'gemini_ms'      => (int) $geminiTotalMs,
                    'tool_ms'        => (int) $toolTotalMs,
                    'total_ms'       => (int) $totalTurnMs,
                    'cache_hit'      => false,
                ]);

                // MEJORA #5: Registrar en ai_intent_logs
                $this->recordIntentLog([
                    'session_id'    => $sessionId,
                    'intent'        => $intent,
                    'query'         => $message,
                    'complexity'    => $complexity['nivel'],
                    'model_used'    => $modelUsed,
                    'tool_used'     => $finalToolName,
                    'tools_sent'    => $toolsSent,
                    'tokens_used'   => ($currentResponse['usage']['totalTokenCount'] ?? 0),
                    'gemini_ms'     => (int) $geminiTotalMs,
                    'tool_ms'       => (int) $toolTotalMs,
                    'total_ms'      => (int) $totalTurnMs,
                    'cache_hit'     => false,
                    'success'       => true,
                ]);

                // MEJORA #7: Almacenar en caché si es consulta histórica elegible
                if ($isCacheCandidate && ($finalToolResult['success'] ?? true)) {
                    Cache::put($cacheKey, [
                        'success' => true,
                        'data'    => $streamDoneData,
                        'message' => 'Respuesta generada (en caché).',
                    ], now()->addSeconds(60));
                }

            } catch (\Throwable $e) {
                Log::error('AiController::chatStream error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

                $totalTurnMs = round((microtime(true) - $turnStartTime) * 1000, 2);

                // MEJORA #5: Registrar error en ai_intent_logs
                $this->recordIntentLog([
                    'session_id'    => $sessionId ?? Str::uuid(),
                    'intent'        => $intent ?? 'general',
                    'query'         => $message ?? '',
                    'complexity'    => $complexity['nivel'] ?? 'media',
                    'model_used'    => config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest')),
                    'tool_used'     => $lastToolName ?? null,
                    'tools_sent'    => $toolsSent ?? 0,
                    'tokens_used'   => 0,
                    'gemini_ms'     => (int) ($geminiTotalMs ?? 0),
                    'tool_ms'       => (int) ($toolTotalMs ?? 0),
                    'total_ms'      => (int) $totalTurnMs,
                    'cache_hit'     => false,
                    'success'       => false,
                    'error_message' => $e->getMessage(),
                ]);

                // RECOVERY TOTAL: Si cualquier herramienta ya se ejecutó con éxito en este turno
                // antes de que fallara cualquier síntesis o conexión posterior, responder con éxito al usuario
                // utilizando los datos y ui_action ya calculados por el sistema.
                if (!empty($lastToolName) && !empty($lastToolResult['success'])) {
                    $fallbackReply = $lastToolResult['message'] ?? 'La operación fue registrada con éxito en el sistema.';

                    $sendEvent('token', ['token' => $fallbackReply]);
                    $sendEvent('done', [
                        'reply'             => $fallbackReply,
                        'response'          => $fallbackReply,
                        'tool_used'         => $lastToolName,
                        'tool_data'         => $lastToolResult['data'] ?? null,
                        'tool_success'      => true,
                        'tool_status'       => 'executed',
                        'ui_action'         => $capturedUiAction ?? ($lastToolResult['ui_action'] ?? null),
                        'chips'             => $capturedChips ?? ($lastToolResult['chips'] ?? null),
                        'campos_faltantes'  => null,
                        'tools_executed'    => $toolsExecuted ?? [],
                        'session_id'        => $sessionId,
                        'total_duration_ms' => $totalTurnMs,
                    ]);
                    return;
                }

                // Primero desbloquear la UI (clear_loading), luego emitir error y finalizar
                $sendEvent('ui_action', ['type' => 'clear_loading']);
                $sendEvent('error', [
                    'message' => $this->friendlyErrorMessage($e),
                ]);
            } finally {
                // Garantizar que el frontend siempre reciba el estado idle para desbloquear la UI
                try {
                    $sendEvent('status', ['status' => 'idle']);
                } catch (\Throwable $_) {
                    // Stream posiblemente ya cerrado — ignorar
                }
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=UTF-8',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Generar contenido simple (sin historial, sin ciclo de tools).
     * POST /api/ai/generate
     */
    public function generate(AiGenerateRequest $request): JsonResponse
    {
        try {
            $prompt           = $request->input('prompt');
            $systemInstruction = $request->input('system_instruction', self::SYSTEM_INSTRUCTION);
            $enableTools      = $request->boolean('enable_tools', false);

            $generationConfig = [];
            if ($request->filled('temperature')) $generationConfig['temperature'] = (float) $request->input('temperature');
            if ($request->filled('max_tokens'))  $generationConfig['maxOutputTokens'] = (int) $request->input('max_tokens');

            $tools  = $enableTools ? $this->getAiService()->getRegisteredTools() : [];
            $result = $this->getAiService()->generateContent($prompt, $systemInstruction, $tools, $generationConfig);

            return response()->json(['success' => true, 'data' => $result, 'message' => 'Contenido generado.'], 200);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Error de validacion.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), '429') ? 429 : 500;
            return response()->json(['success' => false, 'data' => null, 'message' => $this->friendlyErrorMessage($e)], $statusCode);
        }
    }

    /**
     * Ejecutar una tool directamente sin pasar por Gemini (endpoint de debug/admin).
     * POST /api/ai/tool
     */
    public function executeTool(Request $request): JsonResponse
    {
        $toolName     = $request->input('tool');
        $args         = $request->input('args', []);
        $readOnlyMode = $request->boolean('read_only', false);

        if (empty($toolName)) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Se requiere el campo tool.'], 422);
        }

        try {
            $result = $this->toolsService->dispatch($toolName, $args, $readOnlyMode);
            return response()->json(['success' => true, 'data' => $result, 'message' => "Tool {$toolName} ejecutada."], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verificar estado de salud y conectividad de la API de IA.
     * GET /api/ai/status
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->getAiService()->checkConnection();
            return response()->json([
                'success' => (bool) ($status['online'] ?? false),
                'data'    => $status,
                'message' => ($status['online'] ?? false) ? 'Conexion activa y operativa.' : 'No se pudo conectar con el modelo de IA.',
            ], ($status['online'] ?? false) ? 200 : 503);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => $this->friendlyErrorMessage($e)], 500);
        }
    }

    /**
     * Listar catalogo de Tools registradas.
     * GET /api/ai/tools
     */
    public function tools(): JsonResponse
    {
        try {
            $tools = $this->geminiService->getRegisteredTools();
            $declarations = $tools[0]['functionDeclarations'] ?? [];
            return response()->json([
                'success' => true,
                'data'    => ['tools' => $declarations, 'total' => count($declarations)],
                'message' => count($declarations) . ' tools registradas en Valencia AI.',
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint de salud y telemetría de Valencia AI.
     * GET /api/ai/health (con throttle:10,1)
     */
    public function health(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $overallStatus = 'healthy';

        // 1. Check AI Provider (OpenAI o Gemini)
        $aiStart = microtime(true);
        $aiOnline = false;
        $aiError = null;
        $activeProvider = config('services.ai.provider', env('AI_PROVIDER', 'openai'));
        $aiModel = ($activeProvider === 'openai')
            ? config('services.openai.model', env('OPENAI_MODEL', 'gpt-5.5'))
            : config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest'));
        try {
            $aiCheck = $this->getAiService()->checkConnection();
            $aiOnline = !empty($aiCheck['online']);
            if (!$aiOnline) {
                $aiError = $aiCheck['error'] ?? 'API inalcanzable';
                $overallStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $aiError = $e->getMessage();
            $overallStatus = 'degraded';
        }
        $aiMs = round((microtime(true) - $aiStart) * 1000, 2);

        // 2. Check Database (SQL Server)
        $dbStart = microtime(true);
        $dbOnline = false;
        $dbError = null;
        try {
            DB::select('SELECT 1');
            $dbOnline = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
            $overallStatus = 'unhealthy';
        }
        $dbMs = round((microtime(true) - $dbStart) * 1000, 2);

        // 3. Check Cache & Epoch
        $cacheStart = microtime(true);
        $cacheOnline = false;
        $epoch = (int) Cache::get('ai_cache_epoch', 1);
        try {
            Cache::put('ai_health_ping', 1, now()->addSeconds(10));
            $cacheOnline = (Cache::get('ai_health_ping') === 1);
        } catch (\Throwable $e) {
            $cacheOnline = false;
        }
        $cacheMs = round((microtime(true) - $cacheStart) * 1000, 2);

        // 4. Métricas de los últimos 5 minutos desde ai_intent_logs
        $recentLogs = AiIntentLog::where('created_at', '>=', now()->subMinutes(5))->get();
        $totalRecent = $recentLogs->count();
        $successCount = $recentLogs->where('success', true)->count();
        $cacheHitCount = $recentLogs->where('cache_hit', true)->count();
        $avgTotalMs = $totalRecent > 0 ? round($recentLogs->avg('total_ms'), 1) : 0;
        $successRate = $totalRecent > 0 ? round(($successCount / $totalRecent) * 100, 1) : 100.0;
        $cacheHitRate = $totalRecent > 0 ? round(($cacheHitCount / $totalRecent) * 100, 1) : 0.0;
        $intentsCount = $recentLogs->groupBy('intent')->map->count()->toArray();
        $modelsCount = $recentLogs->groupBy('model_used')->map->count()->toArray();

        $totalHealthMs = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status'          => $overallStatus,
            'provider'        => $activeProvider,
            'timestamp'       => now()->toIso8601String(),
            'duration_ms'     => $totalHealthMs,
            'services'        => [
                'ai'       => [
                    'provider'   => $activeProvider,
                    'status'     => $aiOnline ? 'ok' : 'unreachable',
                    'latency_ms' => $aiMs,
                    'model'      => $aiModel,
                    'error'      => $aiError,
                ],
                'gemini'   => [
                    'status'     => $aiOnline ? 'ok' : 'unreachable',
                    'latency_ms' => $aiMs,
                    'model'      => $aiModel,
                    'error'      => $aiError,
                ],
                'database' => [
                    'status'     => $dbOnline ? 'ok' : 'unreachable',
                    'latency_ms' => $dbMs,
                    'driver'     => config('database.default'),
                    'error'      => $dbError,
                ],
                'cache'    => [
                    'status'     => $cacheOnline ? 'ok' : 'error',
                    'latency_ms' => $cacheMs,
                    'driver'     => config('cache.default'),
                    'epoch'      => $epoch,
                ],
            ],
            'metrics_last_5m' => [
                'total_requests' => $totalRecent,
                'avg_latency_ms' => $avgTotalMs,
                'success_rate'   => $successRate,
                'cache_hit_rate' => $cacheHitRate,
                'intents'        => $intentsCount,
                'models'         => $modelsCount,
            ],
        ], $overallStatus === 'unhealthy' ? 503 : 200);
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVADOS
    // -------------------------------------------------------------------------

    /**
     * MEJORA #1: Sintetiza una respuesta en lenguaje natural con Gemini
     * cuando una herramienta ejecutada no produjo texto ni ui_action.
     */
    private function sintetizarConGemini(array $toolResult, string $userMessage, ?float $deadline = null): string
    {
        try {
            $dataSnippet = json_encode($toolResult['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (mb_strlen($dataSnippet) > 2000) {
                $dataSnippet = mb_substr($dataSnippet, 0, 2000) . "\n... (datos resumidos)";
            }
            $prompt = "El usuario consultó: \"{$userMessage}\"\n" .
                "Se ejecutó una consulta y se obtuvieron los siguientes datos:\n{$dataSnippet}\n" .
                "Por favor sintetiza una respuesta breve, concisa y profesional en español comunicando los hallazgos principales.";

            $res = $this->getAiService()->generateContent(
                $prompt,
                "Eres Valencia AI, asistente operativo de Comercial Valencia. Comunica datos concretos en español.",
                [],
                ['temperature' => 0.2, 'maxOutputTokens' => 300],
                10
            );
            $txt = trim($res['text'] ?? '');
            if (!empty($txt)) {
                return $txt;
            }
        } catch (\Throwable $e) {
            Log::warning("Valencia AI: Error en sintetizarConGemini", ['error' => $e->getMessage()]);
        }

        if (!empty($toolResult['data'])) {
            return "Se completó la consulta con éxito y se obtuvieron los registros solicitados.";
        }
        return $toolResult['message'] ?? 'Operación procesada en el sistema.';
    }

    /**
     * MEJORA #7: Normaliza la consulta eliminando acentos, puntuaciones y espacios redundantes
     * para maximizar el hit-ratio del caché de consultas.
     */
    private function normalizarQuery(string $query): string
    {
        $q = mb_strtolower(trim($query));
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ];
        $q = strtr($q, $replacements);
        $q = preg_replace('/[?¿!¡.,;:_"\'-]/u', ' ', $q);
        return preg_replace('/\s+/u', ' ', trim($q));
    }

    /**
     * MEJORA #7: Determina si una consulta es elegible para caché semántico (TTL 60s).
     * NUNCA cachea consultas en tiempo real o del día en curso.
     */
    private function esCacheable(string $query, string $intent): bool
    {
        $q = mb_strtolower($query);
        if (preg_match('/hoy|ahora|momento|actual|del d[ií]a|en vivo/u', $q)) {
            return false;
        }
        return in_array($intent, ['BI', 'stock', 'reportes'], true);
    }

    /**
     * MEJORA #5: Registra telemetría estructurada en ai_intent_logs de forma segura.
     */
    private function recordIntentLog(array $params): void
    {
        try {
            AiIntentLog::create([
                'session_id'    => $params['session_id'] ?? null,
                'user_id'       => $params['user_id'] ?? (auth()->id() ?? null),
                'intent'        => $params['intent'] ?? 'general',
                'query'         => $params['query'] ?? '',
                'complexity'    => $params['complexity'] ?? 'media',
                'model_used'    => $params['model_used'] ?? null,
                'tool_used'     => $params['tool_used'] ?? null,
                'tools_sent'    => (int) ($params['tools_sent'] ?? 0),
                'tokens_used'   => (int) ($params['tokens_used'] ?? 0),
                'gemini_ms'     => (int) ($params['gemini_ms'] ?? 0),
                'tool_ms'       => (int) ($params['tool_ms'] ?? 0),
                'total_ms'      => (int) ($params['total_ms'] ?? 0),
                'cache_hit'     => (bool) ($params['cache_hit'] ?? false),
                'success'       => (bool) ($params['success'] ?? true),
                'error_message' => isset($params['error_message']) ? mb_substr((string)$params['error_message'], 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Valencia AI: Error al registrar ai_intent_logs", ['err' => $e->getMessage()]);
        }
    }

    /**
     * Construye una consulta contextual para el filtrado de tools, incorporando
     * el mensaje del usuario y el contexto del turno anterior (especialmente ante confirmaciones breves como "confirmo", "sí", "procede").
     */
    private function buildContextQuery(string $message, array $rawHistory): string
    {
        $contextQuery = trim($message);

        if (!empty($rawHistory)) {
            for ($i = count($rawHistory) - 1; $i >= 0; $i--) {
                $entry = $rawHistory[$i];
                if (!is_array($entry)) continue;

                $role = $entry['role'] ?? '';
                if (in_array($role, ['model', 'assistant', 'bot'], true)) {
                    $prevText = $entry['text'] ?? ($entry['content'] ?? ($entry['parts'][0]['text'] ?? ''));
                    if (!empty($prevText) && is_string($prevText)) {
                        $contextQuery .= ' ' . mb_substr(trim($prevText), 0, 250);
                    }
                    break;
                }
            }
        }

        return $contextQuery;
    }

    /**
     * Reconstruir el array de contents de Gemini a partir del historial del frontend.
     * Formato frontend: [{ role: 'user'|'bot'|'model', text: '...' }]
     */
    private function buildContentsFromHistory(array $history): array
    {
        $contents = [];
        foreach ($history as $msg) {
            $role = match ($msg['role'] ?? 'user') {
                'bot', 'model', 'assistant' => 'model',
                default                     => 'user',
            };
            $text = $msg['text'] ?? $msg['content'] ?? '';
            if (!empty(trim($text))) {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }
        return $contents;
    }

    /**
     * Llama a Gemini con reintentos en caso de error de red o rate limit, con guarda de tiempo total.
     */
    private function callGeminiWithRetry(callable $fn, int $maxRetries = self::MAX_RETRIES, float $deadlineTimestamp = 0.0): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            if ($attempt > 0 && $deadlineTimestamp > 0 && microtime(true) >= $deadlineTimestamp) {
                Log::warning("Valencia AI: Deadline de tiempo alcanzado antes del reintento {$attempt}.");
                break;
            }

            try {
                return $fn();
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                // No reintentar en errores de autenticacion o validacion
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, '401') || str_contains($msg, '403') || str_contains($msg, 'api_key')) {
                    throw $e;
                }

                if ($attempt <= $maxRetries && ($deadlineTimestamp <= 0 || microtime(true) + 0.4 < $deadlineTimestamp)) {
                    Log::warning("Gemini retry {$attempt}/{$maxRetries}: " . $e->getMessage());
                    usleep(300_000); // 300ms backoff rápido
                } else {
                    break;
                }
            }
        }

        throw $lastException ?? new Exception('Error de comunicación con Gemini tras reintentos.');
    }

    /**
     * Convierte excepciones tecnicas en mensajes amigables para el usuario.
     */
    private function friendlyErrorMessage(Exception $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, '429') || str_contains(strtolower($msg), 'rate limit') || str_contains(strtolower($msg), 'quota')) {
            return 'El servicio de IA esta ocupado en este momento. Por favor, intenta en unos segundos.';
        }
        if (str_contains($msg, '503') || str_contains(strtolower($msg), 'unavailable') || str_contains(strtolower($msg), 'overloaded')) {
            return 'El modelo de IA no esta disponible temporalmente. Intenta en unos momentos.';
        }
        if (str_contains($msg, '401') || str_contains($msg, '403') || str_contains(strtolower($msg), 'api_key')) {
            return 'Error de autenticacion con el servicio de IA. Contacta al administrador del sistema.';
        }
        if (str_contains(strtolower($msg), 'timeout') || str_contains(strtolower($msg), 'curl')) {
            return 'La conexion con el servicio de IA tardo demasiado. Verifica tu conexion e intenta de nuevo.';
        }

        return 'Ocurrio un error inesperado en el asistente. Por favor, intenta de nuevo.';
    }

    /**
     * Transcribe una nota de voz enviada por el usuario utilizando Gemini Multimodal.
     * POST /api/ai/transcribe-audio
     */
    public function transcribeAudio(Request $request): JsonResponse
    {
        set_time_limit(120);

        if (!$request->hasFile('audio')) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'No se recibió ningún archivo de audio para transcribir.',
            ], 422);
        }

        $file = $request->file('audio');

        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'El archivo de audio subido no es válido o está corrupto.',
            ], 422);
        }

        $size = $file->getSize();

        // Descartar audios menores a 2KB (~0.2s) para filtrar clics accidentales
        if ($size < 2048) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'El audio es demasiado corto o no contiene voz perceptible. Mantén presionado o habla con claridad.',
            ], 422);
        }

        // Máximo 15MB
        if ($size > 15 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'El archivo de audio excede el límite máximo permitido de 15 MB.',
            ], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'webm');
        if (!in_array($extension, ['webm', 'wav', 'mp3', 'ogg', 'm4a', 'mp4', 'aac'], true)) {
            $extension = 'webm';
        }

        $voiceNoteId = (string) Str::uuid();
        $fileName = "{$voiceNoteId}.{$extension}";

        // Almacenar en directorio privado storage/app/voice_notes
        $storageDir = storage_path('app/voice_notes');
        if (!file_exists($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }

        $file->move($storageDir, $fileName);
        $fullPath = "{$storageDir}/{$fileName}";

        $clientMime = $request->input('mime_type') ?: ($file->getClientMimeType() ?: 'audio/webm');

        try {
            $result = $this->getAiService()->transcribeAudio($fullPath, $clientMime);

            return response()->json([
                'success' => true,
                'data'    => [
                    'voice_note_id' => $voiceNoteId,
                    'text'          => $result['text'] ?? '',
                    'model'         => $result['model'] ?? '',
                    'duration_ms'   => $result['duration_ms'] ?? 0,
                    'size_kb'       => $result['size_kb'] ?? round($size / 1024, 2),
                    'cost_usd'      => $result['cost_usd'] ?? null,
                    'audio_url'     => url("/api/ai/voice-notes/{$voiceNoteId}"),
                ],
                'message' => empty($result['text'])
                    ? 'No se detectaron palabras en el audio grabado.'
                    : 'Audio transcrito correctamente.',
            ], 200);

        } catch (Exception $e) {
            Log::channel('voice_transcription')->error("AiController::transcribeAudio error", ['error' => $e->getMessage()]);

            $errLower = strtolower($e->getMessage());
            $isQuota = str_contains($e->getMessage(), '429') 
                || str_contains($errLower, 'quota') 
                || str_contains($e->getMessage(), '503') 
                || str_contains($errLower, 'high demand')
                || str_contains($errLower, 'resource exhausted')
                || str_contains($errLower, 'timed out');

            if ($isQuota) {
                Cache::put('ai_voice_saturation', [
                    'saturated'    => true,
                    'reason'       => 'high_demand_or_quota',
                    'message'      => 'Por ahora el micrófono está desactivado debido a que la API de audio ha alcanzado su límite de cuota o está saturada.',
                    'retry_after'  => 60,
                    'saturated_at' => now()->toIso8601String(),
                ], now()->addSeconds(60));
            }

            $statusCode = $isQuota ? 429 : 500;
            $userMsg = $isQuota
                ? 'El servicio de transcripción de voz está momentáneamente saturado o ha llegado a su límite de cuota. Por favor intenta en unos momentos o escribe tu consulta en texto.'
                : 'No fue posible transcribir la nota de voz. Por favor intenta nuevamente o escribe tu consulta.';

            return response()->json([
                'success' => false,
                'data'    => [
                    'voice_note_id'            => $voiceNoteId,
                    'audio_url'                => url("/api/ai/voice-notes/{$voiceNoteId}"),
                    'voice_service_saturated'  => $isQuota,
                ],
                'message' => $userMsg,
            ], $statusCode);
        }
    }

    /**
     * Consulta el estado de disponibilidad del servicio de audio/voz.
     * GET /api/ai/voice-status
     */
    public function voiceStatus(Request $request): JsonResponse
    {
        if ($request->has('reset')) {
            Cache::forget('ai_voice_saturation');
        }

        if ($request->query('simulate') === 'saturated') {
            Cache::put('ai_voice_saturation', [
                'saturated'    => true,
                'reason'       => 'simulation',
                'message'      => 'Por ahora el micrófono está desactivado debido a que la API de audio ha alcanzado su límite de cuota o está saturada.',
                'retry_after'  => 60,
                'saturated_at' => now()->toIso8601String(),
            ], now()->addSeconds(60));
        }

        $saturation = Cache::get('ai_voice_saturation');

        if ($saturation && !empty($saturation['saturated'])) {
            return response()->json([
                'success'     => true,
                'available'   => false,
                'status'      => 'saturated',
                'reason'      => $saturation['reason'] ?? 'high_demand_or_quota',
                'message'     => $saturation['message'] ?? 'Por ahora el micrófono está desactivado debido a que la API de audio está saturada o en límite de cuota.',
                'retry_after' => $saturation['retry_after'] ?? 60,
            ], 200);
        }

        return response()->json([
            'success'     => true,
            'available'   => true,
            'status'      => 'ready',
            'message'     => 'Servicio de notas de voz disponible.',
            'retry_after' => 0,
        ], 200);
    }

    /**
     * Sirve de forma segura el archivo de audio de una nota de voz mediante streaming.
     * GET /api/ai/voice-notes/{uuid}
     */
    public function getVoiceNote(string $uuid)
    {
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $uuid)) {
            abort(404, 'ID de nota de voz inválido.');
        }

        $storageDir = storage_path('app/voice_notes');
        $matches = glob("{$storageDir}/{$uuid}.*");

        if (empty($matches) || !file_exists($matches[0])) {
            abort(404, 'Nota de voz no encontrada.');
        }

        $filePath = $matches[0];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimes = [
            'webm' => 'audio/webm',
            'wav'  => 'audio/wav',
            'mp3'  => 'audio/mpeg',
            'ogg'  => 'audio/ogg',
            'm4a'  => 'audio/mp4',
            'mp4'  => 'audio/mp4',
            'aac'  => 'audio/aac',
        ];
        $contentType = $mimes[$ext] ?? 'audio/webm';

        return response()->file($filePath, [
            'Content-Type'   => $contentType,
            'Accept-Ranges'  => 'bytes',
            'Cache-Control'  => 'private, max-age=86400',
        ]);
    }

    /**
     * Enriquece el system instruction con la fecha actual del sistema y reglas de resolución temporal.
     */
    private function buildSystemInstruction(?string $customInstruction = null): string
    {
        $base = $customInstruction ?: self::SYSTEM_INSTRUCTION;
        $hoy = now()->format('Y-m-d');
        $ayer = now()->subDay()->format('Y-m-d');
        $hace7Dias = now()->subDays(6)->format('Y-m-d');
        $inicioMes = now()->startOfMonth()->format('Y-m-d');

        $contextoTemporal = "\n\nCONTEXTO TEMPORAL ACTUAL EN TIEMPO REAL:\n"
            . "- Fecha actual del sistema (HOY): {$hoy}\n"
            . "- Ayer fue: {$ayer}\n"
            . "- Últimos 7 días abarcan: del {$hace7Dias} al {$hoy} (dias = 7)\n"
            . "- Mes en curso abarca: del {$inicioMes} al {$hoy}\n"
            . "REGLA OBLIGATORIA DE FECHAS: Para cualquier consulta temporal ('hoy', 'ayer', 'últimos N días', 'esta semana', 'este mes'):\n"
            . "1. Si usas generar_grafico_dashboard, pasa siempre el parámetro 'dias' (ej: 7 para últimos 7 días) o 'fecha_desde' y 'fecha_hasta'. Para tendencias de ventas de varios días, usa metrica='ventas_por_dia' con dias=7.\n"
            . "2. NUNCA asumas que un rango de varios días es solo la fecha de hoy. Si el usuario pide 'últimos 7 días', el rango abarca del {$hace7Dias} al {$hoy}.\n"
            . "3. Si el usuario pregunta por 'ayer', consulta la fecha {$ayer}.";

        return $base . $contextoTemporal;
    }

    /**
     * Registra feedback del usuario (like/dislike) para respuestas u operaciones de Valencia AI.
     */
    public function recordFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id'     => 'required|string',
            'message_id'     => 'required|string',
            'tool_log_id'    => 'nullable|integer',
            'tool_name'      => 'nullable|string',
            'pedido_id'      => 'nullable|string',
            'tipo_operacion' => 'nullable|string',
            'rating'         => 'required|in:like,dislike',
            'motivo_dislike' => 'nullable|string',
            'comentario'     => 'nullable|string|max:500',
        ]);

        $esCorrecto = ($validated['rating'] === 'like');
        $motivoDislike = $esCorrecto ? null : ($validated['motivo_dislike'] ?? null);

        // Deduplicación / toggle de voto por session_id + message_id
        $feedback = AiGenerationFeedback::updateOrCreate(
            [
                'session_id' => $validated['session_id'],
                'message_id' => $validated['message_id'],
            ],
            [
                'tool_log_id'    => $validated['tool_log_id'] ?? null,
                'tool_name'      => $validated['tool_name'] ?? null,
                'pedido_id'      => $validated['pedido_id'] ?? null,
                'tipo_operacion' => $validated['tipo_operacion'] ?? null,
                'rating'         => $validated['rating'],
                'es_correcto'    => $esCorrecto,
                'motivo_dislike' => $motivoDislike,
                'comentario'     => $validated['comentario'] ?? null,
                'user_id'        => auth()->id() ?? null,
            ]
        );

        // Si vino tool_log_id, sincronizar en ai_tool_logs
        if (!empty($validated['tool_log_id'])) {
            try {
                AiToolLog::where('id', $validated['tool_log_id'])->update([
                    'feedback'       => $validated['rating'],
                    'motivo_dislike' => $motivoDislike,
                    'feedback_at'    => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Error actualizando ai_tool_logs con feedback: ' . $e->getMessage());
            }
        }

        // Invalidar caché del dashboard para actualización en tiempo real
        try {
            Cache::forget('dashboard:indicadores_globales');
            Cache::forget('dashboard:resumen');
            Cache::forget('dashboard:pedidos_stats');
            $mesActual = now()->format('Y-m');
            Cache::forget("dashboard:peor:{$mesActual}");
            Cache::forget("dashboard:peor:all");
            Cache::forget("dashboard:detail:2:{$mesActual}");
            Cache::forget("dashboard:detail:2:all");
        } catch (\Throwable $e) {
            Log::warning('Error invalidando cache tras feedback: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Feedback registrado exitosamente.',
            'data'    => $feedback,
        ]);
    }

    /**
     * Retorna estadísticas consolidadas del feedback de Valencia AI.
     */
    public function feedbackStats(Request $request): JsonResponse
    {
        $mes = $request->query('mes'); // YYYY-MM opcional
        $query = AiGenerationFeedback::query();

        if ($mes) {
            $inicio = Carbon::parse($mes . '-01')->startOfMonth()->toDateTimeString();
            $fin = Carbon::parse($mes . '-01')->endOfMonth()->toDateTimeString();
            $query->whereBetween('created_at', [$inicio, $fin]);
        }

        $totalFeedback = (clone $query)->count();
        $likes = (clone $query)->where('rating', 'like')->count();
        $dislikes = (clone $query)->where('rating', 'dislike')->count();

        $tasaAcierto = $totalFeedback > 0 ? round(($likes / $totalFeedback) * 100, 1) : 100.0;
        $tasaError = $totalFeedback > 0 ? round(($dislikes / $totalFeedback) * 100, 1) : 0.0;

        // Desglose por motivo de dislike
        $porMotivo = (clone $query)
            ->where('rating', 'dislike')
            ->whereNotNull('motivo_dislike')
            ->select('motivo_dislike', DB::raw('count(*) as total'))
            ->groupBy('motivo_dislike')
            ->orderByDesc('total')
            ->get();

        // Desglose por tool_name
        $porTool = (clone $query)
            ->whereNotNull('tool_name')
            ->select(
                'tool_name',
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN rating = 'like' THEN 1 ELSE 0 END) as likes"),
                DB::raw("SUM(CASE WHEN rating = 'dislike' THEN 1 ELSE 0 END) as dislikes")
            )
            ->groupBy('tool_name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $rowTot = (int) $row->total;
                $rowLikes = (int) $row->likes;
                $row->tasa_acierto = $rowTot > 0 ? round(($rowLikes / $rowTot) * 100, 1) : 100.0;
                return $row;
            });

        // Tendencia temporal (últimos 30 días con datos)
        $tendencia = (clone $query)
            ->select(
                DB::raw("CAST(created_at AS DATE) as fecha"),
                DB::raw("SUM(CASE WHEN rating = 'like' THEN 1 ELSE 0 END) as likes"),
                DB::raw("SUM(CASE WHEN rating = 'dislike' THEN 1 ELSE 0 END) as dislikes"),
                DB::raw('count(*) as total')
            )
            ->groupBy(DB::raw("CAST(created_at AS DATE)"))
            ->orderBy('fecha', 'asc')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_feedback' => $totalFeedback,
                'likes'          => $likes,
                'dislikes'       => $dislikes,
                'tasa_acierto'   => $tasaAcierto,
                'tasa_error'     => $tasaError,
                'por_motivo'     => $porMotivo,
                'por_tool'       => $porTool,
                'tendencia'      => $tendencia,
            ],
        ]);
    }
}