<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $transcribeApiKey;
    protected string $model;
    protected string $transcribeModel;
    protected string $baseUrl;
    protected array $lastFilterMetadata = [];

    public function __construct()
    {
        $this->apiKey           = (string) Config::get('services.gemini.api_key', env('GEMINI_API_KEY', ''));
        $this->transcribeApiKey = (string) Config::get('services.gemini.transcribe_key', env('GEMINI_TRANSCRIBE_KEY', $this->apiKey));
        $this->model            = (string) Config::get('services.gemini.model', env('GEMINI_MODEL', 'gemini-3.6-flash'));
        $this->transcribeModel  = (string) Config::get('services.gemini.transcribe_model', env('GEMINI_TRANSCRIBE_MODEL', 'gemini-3.5-transcribe'));
        $this->baseUrl          = (string) Config::get('services.gemini.base_url', env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'));
    }

    /**
     * Retorna la metadata de la última poda de herramientas realizada.
     */
    public function getLastFilterMetadata(): array
    {
        return $this->lastFilterMetadata;
    }

    /**
     * Clasifica la complejidad de la consulta para aplicar timeout adaptativo:
     * - 'compleja' (40s): Comparativas entre períodos, reportes consolidados mensuales/anuales, balances
     * - 'media'    (25s): Gráficos de dashboard, pedidos, clientes, consultas de stock
     * - 'simple'   (15s): Saludos, preguntas conceptuales, catálogo de ayuda
     */
    public function detectarComplejidad(string $query, string $intent = 'general'): array
    {
        $q = mb_strtolower($query);

        if (
            preg_match('/compar|versus|\bvs\b|tendencia|periodo|semanal|mensual|anual|trimestr|balance|kardex.*detallad/u', $q) ||
            str_contains($q, 'comparar_periodos') ||
            str_contains($q, 'generar_reporte_periodo')
        ) {
            return ['nivel' => 'compleja', 'timeout_seconds' => 40];
        }

        if (
            preg_match('/^(hola|buenos d[ií]as|buenas tardes|buenas noches|qui[eé]n eres|qu[eé] puedes hacer|ayuda|gracias|chao|adi[oó]s)\b/u', trim($q)) ||
            $intent === 'general'
        ) {
            return ['nivel' => 'simple', 'timeout_seconds' => 15];
        }

        return ['nivel' => 'media', 'timeout_seconds' => 25];
    }

    /**
     * Generar contenido usando Google Gemini API (con soporte de System Instruction y Tools).
     *
     * @param string|array $contents Texto simple o array estructurado de contents
     * @param string|null $systemInstruction Instrucción de sistema para definir el rol/comportamiento de la IA
     * @param array $tools Array de herramientas / functionDeclarations
     * @param array $generationConfig Configuración de temperatura, tokens, etc.
     * @param int|null $customTimeout Timeout adaptativo en segundos
     * @return array
     * @throws Exception
     */
    public function generateContent(
        string|array $contents,
        ?string $systemInstruction = null,
        array $tools = [],
        array $generationConfig = [],
        ?int $customTimeout = null
    ): array {
        if (empty($this->apiKey)) {
            throw new Exception('No se ha configurado la clave de API de Gemini (GEMINI_API_KEY).');
        }

        $candidateModels = [
            'gemini-flash-lite-latest',
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash',
        ];

        // Formatear contents si se pasa como string simple
        $formattedContents = is_string($contents)
            ? [
                [
                    'role' => 'user',
                    'parts' => [['text' => $contents]],
                ],
            ]
            : $contents;

        // Construir cuerpo de la petición
        $payload = [
            'contents' => $formattedContents,
        ];

        // Instrucción del Sistema
        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        // Integración de Herramientas (Tools / Function Calling)
        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        // Configuración de Generación
        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        $lastException = null;
        $backupKey = config('services.gemini.transcribe_key');
        $primaryKey = $this->apiKey;

        foreach ($candidateModels as $modelToTry) {
            $currentKey = $primaryKey;
            $attemptWithBackup = false;

            while (true) {
                $url = "{$this->baseUrl}/models/{$modelToTry}:generateContent";
                $urlWithKey = str_contains($url, '?') ? "{$url}&key={$currentKey}" : "{$url}?key={$currentKey}";

                try {
                    $response = Http::withOptions([
                        'force_ip_resolve' => 'v4',
                        'timeout'          => $customTimeout ?? 25,
                        'connect_timeout'  => 3,
                    ])->withHeaders([
                        'x-goog-api-key' => $currentKey,
                        'Content-Type'   => 'application/json',
                    ])->post($urlWithKey, $payload);

                    if (!$response->successful()) {
                        $errorData = $response->json();
                        $status = $response->status();
                        $errorMessage = $errorData['error']['message'] ?? $response->body();

                        // Si la clave primaria agotó cuota (429) y existe clave de respaldo, intentar una vez con ella
                        if ($status === 429 && !$attemptWithBackup && !empty($backupKey) && $backupKey !== $primaryKey) {
                            Log::warning("Valencia AI: Cuota 429 en clave primaria para {$modelToTry}. Probando clave secundaria...");
                            $currentKey = $backupKey;
                            $attemptWithBackup = true;
                            continue;
                        }

                        if (in_array($status, [429, 503, 404, 500], true)) {
                            Log::warning("Valencia AI: Fallback por error HTTP {$status} en {$modelToTry}. Probando modelo alternativo...", [
                                'error' => $errorMessage
                            ]);
                            $lastException = new Exception("Error en Gemini API ({$status}): {$errorMessage}");
                            break; // Siguiente modelo
                        }

                        Log::error('Error en Gemini API:', ['status' => $status, 'response' => $errorData]);
                        throw new Exception("Error en Gemini API ({$status}): {$errorMessage}");
                    }

                    $responseData = $response->json();
                    $usage = $responseData['usageMetadata'] ?? [];
                    Log::info('Gemini API Token Usage', [
                        'model'             => $modelToTry,
                        'prompt_tokens'     => $usage['promptTokenCount'] ?? 0,
                        'candidates_tokens' => $usage['candidatesTokenCount'] ?? 0,
                        'total_tokens'      => $usage['totalTokenCount'] ?? 0,
                    ]);

                    $parsed = $this->parseResponse($responseData);
                    $parsed['model_version'] = $modelToTry;
                    return $parsed;
                } catch (Exception $e) {
                    $lastException = $e;
                    $msg = $e->getMessage();
                    if (
                        str_contains($msg, '429') ||
                        str_contains($msg, '503') ||
                        str_contains($msg, '404') ||
                        str_contains($msg, 'timed out') ||
                        str_contains($msg, 'Operation timed out') ||
                        str_contains($msg, 'cURL error 28')
                    ) {
                        Log::warning("Valencia AI: Timeout o error de red en {$modelToTry} ({$msg}). Probando modelo alternativo...");
                        break; // Salir al siguiente modelo
                    }
                    Log::error('Excepción al conectar con Gemini:', ['mensaje' => $msg]);
                    throw $e;
                }
            }
        }

        throw $lastException ?? new Exception('No se pudo conectar con los modelos de Gemini disponibles.');
    }

    /**
     * Conversación continua (Chat) manteniendo el historial de contexto.
     *
     * @param string $message Mensaje actual del usuario
     * @param array $history Historial previo [{ 'role': 'user'|'model', 'text': '...' }]
     * @param string|null $systemInstruction Instrucción del sistema
     * @param array $tools Herramientas a habilitar
     * @return array
     */
    public function chat(
        string $message,
        array $history = [],
        ?string $systemInstruction = null,
        array $tools = []
    ): array {
        $contents = [];

        // Reconstruir historial al formato de Gemini
        foreach ($history as $msg) {
            $role = ($msg['role'] ?? 'user') === 'model' ? 'model' : 'user';
            $text = $msg['text'] ?? $msg['content'] ?? '';

            if (!empty($text)) {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }

        // Agregar mensaje actual del usuario
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        return $this->generateContent($contents, $systemInstruction, $tools);
    }

    /**
     * Verificar salud y conectividad con la API de Gemini.
     */
    public function checkConnection(): array
    {
        try {
            $result = $this->generateContent(
                'Responde estrictamente con la palabra OK.',
                'Eres un comprobador de estado del sistema.',
                [],
                ['maxOutputTokens' => 10, 'temperature' => 0.1]
            );

            return [
                'online' => true,
                'model' => $this->model,
                'response' => trim($result['text'] ?? 'OK'),
                'latency_ms' => $result['usage']['totalTokenCount'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'online' => false,
                'model' => $this->model,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Catálogo de declaraciones de Tools de Valencia AI (con soporte para modo solo lectura y filtrado inteligente).
     */
    public function getRegisteredTools(bool $readOnly = false, ?string $userQuery = null): array
    {
        $tools = [
            [
                'functionDeclarations' => [
                    ['name' => 'crear_pedido_cliente', 'description' => 'Crea directamente una orden de pedido en la base de datos restando stock. USAR SOLO cuando el usuario confirme explícitamente ("guárdalo directamente", "crea la orden ya sin abrir formulario"). Para el flujo habitual, usa abrir_formulario_pedido.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['cliente_id' => ['type' => 'STRING', 'description' => 'ID del cliente (ej: CLI-00014). Extraer del contexto reciente si fue mencionado o registrado previamente.'], 'detalles' => ['type' => 'ARRAY', 'description' => 'Lista de productos a pedir con su cantidad y presentación.', 'items' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID del producto (ej: PROD-00005)'], 'cantidad' => ['type' => 'NUMBER', 'description' => 'Cantidad solicitada de la unidad o presentación (ej: 10)'], 'unidad_id' => ['type' => 'STRING', 'description' => 'ID de la presentación o unidad de medida (ej: UND-00016 para paquetes/cajas, UND-00001 para unidad simple)'], 'factor_conversion' => ['type' => 'INTEGER', 'description' => 'Factor de conversión de la presentación (ej: 12 para paquete o caja de 12 unidades)'], 'precio_unitario' => ['type' => 'NUMBER', 'description' => 'Precio unitario de venta de la presentación o unidad']], 'required' => ['producto_id', 'cantidad']]], 'canal_id' => ['type' => 'STRING', 'description' => 'Canal de pedido (ej: CNL-00001). Por defecto: tienda presencial.'], 'observaciones' => ['type' => 'STRING', 'description' => 'Observaciones opcionales del pedido']], 'required' => ['cliente_id', 'detalles']]],
                    ['name' => 'abrir_formulario_pedido', 'description' => 'Abre la interfaz de usuario de "Nuevo Pedido" con el cliente y productos precargados para que el usuario humano revise visualmente, ajuste cantidades y haga clic en Guardar Pedido. USAR ESTA HERRAMIENTA POR DEFECTO cuando el usuario exprese la intención de crear un pedido o comprar productos.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['cliente_id' => ['type' => 'STRING', 'description' => 'ID del cliente (ej: CLI-00014) o nombre/término para resolverlo.'], 'cliente_nombre' => ['type' => 'STRING', 'description' => 'Nombre o razón social del cliente.'], 'detalles' => ['type' => 'ARRAY', 'description' => 'Lista de productos a precargar en el formulario.', 'items' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID o nombre del producto (ej: PROD-00005, Aceite Chef)'], 'cantidad' => ['type' => 'NUMBER', 'description' => 'Cantidad a ordenar (ej: 10)'], 'unidad_id' => ['type' => 'STRING', 'description' => 'ID o nombre de la unidad/presentación (ej: UND-00016, paquete, caja)'], 'factor_conversion' => ['type' => 'INTEGER', 'description' => 'Factor de conversión de la presentación (ej: 12)'], 'precio_unitario' => ['type' => 'NUMBER', 'description' => 'Precio unitario de venta (opcional)']], 'required' => ['producto_id', 'cantidad']]], 'acuerdo_comercial' => ['type' => 'STRING', 'description' => 'Acuerdo comercial (ej: Contado Mostrador).'], 'canal_id' => ['type' => 'STRING', 'description' => 'ID del canal de venta (ej: CNL-00001).']], 'required' => ['detalles']]],
                    ['name' => 'limpiar_formulario_pedido', 'description' => 'Limpia y vacía el formulario de "Nuevo Pedido" en pantalla, descartando los productos y cliente precargados. Usar cuando el usuario solicite limpiar la pantalla, vaciar el formulario o descartar el borrador de pedido actual.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    ['name' => 'listar_pedidos', 'description' => 'Lista pedidos de clientes con filtros opcionales por estado, cliente, fecha y canal. Usar para consultas como "¿cuáles son los pedidos de...?", "muéstrame las órdenes canceladas", o "lista los pedidos pendientes".', 'parameters' => ['type' => 'OBJECT', 'properties' => ['estado' => ['type' => 'STRING', 'description' => 'P=Pendiente, C=Completado, A=Anulado'], 'cliente_id' => ['type' => 'STRING', 'description' => 'Filtrar por cliente'], 'fecha_desde' => ['type' => 'STRING', 'description' => 'Fecha inicio (YYYY-MM-DD)'], 'fecha_hasta' => ['type' => 'STRING', 'description' => 'Fecha fin (YYYY-MM-DD)'], 'canal_id' => ['type' => 'STRING', 'description' => 'Filtrar por canal'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina (defecto 15)']]]],
                    ['name' => 'obtener_pedido', 'description' => 'Obtiene el detalle completo de un pedido especifico incluyendo productos y estado.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['pedido_id' => ['type' => 'STRING', 'description' => 'ID del pedido (ej: PED-00001)']], 'required' => ['pedido_id']]],
                    ['name' => 'completar_pedido', 'description' => 'Marca uno o varios pedidos pendientes como COMPLETADOS (P -> C). Puede recibir un ID ("PED-00001"), varios IDs separados por coma ("PED-00020, PED-00021"), o una lista en pedido_ids.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['pedido_id' => ['type' => 'STRING', 'description' => 'ID del pedido o IDs separados por coma (ej: "PED-00020, PED-00021")'], 'pedido_ids' => ['type' => 'ARRAY', 'description' => 'Lista de IDs de pedidos a completar', 'items' => ['type' => 'STRING']]], 'required' => ['pedido_id']]],
                    ['name' => 'cancelar_pedido', 'description' => 'Cancela uno o varios pedidos pendientes y devuelve el stock al inventario (P -> A). USAR SIEMPRE para cancelar pedidos. Puede recibir un ID ("PED-00001"), varios IDs separados por coma ("PED-00020, PED-00021"), o una lista en pedido_ids. NUNCA canceles solo uno si el usuario pide cancelar dos o más.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['pedido_id' => ['type' => 'STRING', 'description' => 'ID del pedido o IDs separados por coma (ej: "PED-00020, PED-00021")'], 'pedido_ids' => ['type' => 'ARRAY', 'description' => 'Lista de IDs de pedidos a cancelar (ej: ["PED-00020", "PED-00021"])', 'items' => ['type' => 'STRING']]], 'required' => ['pedido_id']]],
                    ['name' => 'actualizar_pedido', 'description' => 'Modifica un pedido existente en estado PENDIENTE (P). Permite agregar productos (accion="agregar"), retirar productos (accion="eliminar") o actualizar observaciones/canal. Descuenta o devuelve stock y registra movimientos en Kárdex.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['pedido_id' => ['type' => 'STRING', 'description' => 'ID del pedido pendiente a modificar (ej: PED-00001)'], 'accion' => ['type' => 'STRING', 'description' => 'Tipo de acción sobre productos: "agregar" para añadir producto o "eliminar" para retirar producto.'], 'producto_id' => ['type' => 'STRING', 'description' => 'ID o nombre del producto a agregar o retirar.'], 'cantidad' => ['type' => 'NUMBER', 'description' => 'Cantidad a agregar.'], 'unidad_id' => ['type' => 'STRING', 'description' => 'ID o nombre de la unidad/presentación (ej: UND-00016 para paquetes/cajas).'], 'factor_conversion' => ['type' => 'INTEGER', 'description' => 'Factor de conversión de la presentación (ej: 12, 50).'], 'precio_unitario' => ['type' => 'NUMBER', 'description' => 'Precio unitario de venta (opcional).'], 'observaciones' => ['type' => 'STRING', 'description' => 'Nuevas observaciones.'], 'canal_id' => ['type' => 'STRING', 'description' => 'Nuevo canal.']], 'required' => ['pedido_id']]],
                    ['name' => 'eliminar_pedido', 'description' => 'Eliminacion logica de uno o varios pedidos pendientes. Devuelve el stock al inventario. Puede recibir un ID o lista en pedido_ids.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['pedido_id' => ['type' => 'STRING', 'description' => 'ID del pedido o IDs separados por coma'], 'pedido_ids' => ['type' => 'ARRAY', 'description' => 'Lista de IDs de pedidos a eliminar', 'items' => ['type' => 'STRING']]], 'required' => ['pedido_id']]],
                    ['name' => 'obtener_pedidos_del_dia', 'description' => 'Lista todos los pedidos registrados en el dia de hoy con resumen por estado.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    ['name' => 'obtener_estadisticas_pedidos', 'description' => 'Obtiene métricas y conteos exactos de pedidos (total general, pedidos por estado: canceladas, pendientes, completadas). Usar SIEMPRE que el usuario pregunte "¿cuántos pedidos hay?", "¿cuántas órdenes canceladas hay?", "¿cuántos pedidos pendientes existen?" o pida un resumen de órdenes.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['estado' => ['type' => 'STRING', 'description' => 'Filtrar por estado: A=Anuladas/Canceladas, P=Pendientes, C=Completadas'], 'dias' => ['type' => 'INTEGER', 'description' => 'Dias a analizar (opcional, por defecto todo el historial)']]]],
                    ['name' => 'verificar_timeout_pedidos', 'description' => 'Verifica y cancela automaticamente los pedidos pendientes con mas de 12 horas sin completarse.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    // === MODULO 2: CLIENTES ===
                    ['name' => 'crear_cliente', 'description' => 'Registra un nuevo cliente. SOLO requiere 4 campos: documento (RUC/DNI), nombre o razon social, telefono y direccion. NO pedir correo ni tipo de documento.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'documento' => [
                                'type' => 'STRING',
                                'description' => 'RUC (11 digitos) o DNI (8 digitos). Si el cliente no lo conoce o no tiene, usar "11110000" como comodin.',
                            ],
                            'nombre' => [
                                'type' => 'STRING',
                                'description' => 'Nombre completo o razon social del cliente (OBLIGATORIO)',
                            ],
                            'telefono' => [
                                'type' => 'STRING',
                                'description' => 'Numero de telefono o celular del cliente (OBLIGATORIO)',
                            ],
                            'direccion' => [
                                'type' => 'STRING',
                                'description' => 'Direccion fiscal o comercial fisica del cliente (OBLIGATORIA)',
                            ],
                        ],
                        'required' => ['documento', 'nombre', 'telefono', 'direccion'],
                    ]],
                    ['name' => 'buscar_cliente', 'description' => 'Busca clientes por nombre, DNI, RUC o telefono.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['termino' => ['type' => 'STRING', 'description' => 'Termino de busqueda: nombre, DNI, RUC o telefono'], 'limite' => ['type' => 'INTEGER', 'description' => 'Maximo de resultados (defecto 10)']], 'required' => ['termino']]],
                    ['name' => 'obtener_cliente', 'description' => 'Obtiene el detalle completo de un cliente por su ID.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['cliente_id' => ['type' => 'STRING', 'description' => 'ID del cliente (ej: CLI-00001)']], 'required' => ['cliente_id']]],
                    ['name' => 'listar_clientes', 'description' => 'Lista y cuenta clientes del sistema con filtros por búsqueda o estado (A=Activo, I=Inactivo). Usar SIEMPRE que el usuario pregunte cuántos clientes activos tenemos, cuáles son los clientes, lista de clientes, etc.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['search' => ['type' => 'STRING', 'description' => 'Termino de busqueda'], 'estado' => ['type' => 'STRING', 'description' => 'Filtrar por estado: A=Activos, I=Inactivos'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina']]]],
                    ['name' => 'actualizar_cliente', 'description' => 'Actualiza los datos de un cliente existente.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['cliente_id' => ['type' => 'STRING', 'description' => 'ID del cliente'], 'nombre' => ['type' => 'STRING', 'description' => 'Nuevo nombre'], 'telefono' => ['type' => 'STRING', 'description' => 'Nuevo telefono'], 'email' => ['type' => 'STRING', 'description' => 'Nuevo email'], 'direccion' => ['type' => 'STRING', 'description' => 'Nueva direccion']], 'required' => ['cliente_id']]],
                    ['name' => 'eliminar_cliente', 'description' => 'Eliminacion logica de un cliente del sistema.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['cliente_id' => ['type' => 'STRING', 'description' => 'ID del cliente a eliminar']], 'required' => ['cliente_id']]],
                    ['name' => 'obtener_historial_cliente', 'description' => 'Obtiene el historial completo de pedidos de un cliente especifico.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['cliente_id' => ['type' => 'STRING', 'description' => 'ID del cliente'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina']], 'required' => ['cliente_id']]],
                    ['name' => 'abrir_formulario_cliente', 'description' => 'Abre el modal o formulario UI de creacion rapida de cliente con datos precargados del usuario.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'documento' => ['type' => 'STRING', 'description' => 'RUC o DNI a precargar'],
                            'nombre'    => ['type' => 'STRING', 'description' => 'Nombre o razon social a precargar'],
                            'telefono'  => ['type' => 'STRING', 'description' => 'Telefono o celular a precargar'],
                            'direccion' => ['type' => 'STRING', 'description' => 'Direccion fiscal o comercial a precargar'],
                        ],
                    ]],
                    // === MODULO 3: PRODUCTOS ===
                    ['name' => 'crear_producto', 'description' => 'Registra un nuevo producto en el catalogo. Requiere nombre y categoria_id.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['nombre' => ['type' => 'STRING', 'description' => 'Nombre del producto'], 'categoria_id' => ['type' => 'STRING', 'description' => 'ID de la categoria'], 'precio_unitario' => ['type' => 'NUMBER', 'description' => 'Precio unitario de venta'], 'stock_minimo' => ['type' => 'NUMBER', 'description' => 'Stock minimo de alerta'], 'descripcion' => ['type' => 'STRING', 'description' => 'Descripcion del producto']], 'required' => ['nombre', 'categoria_id']]],
                    ['name' => 'buscar_producto', 'description' => 'Busca productos por nombre, codigo o categoria.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['termino' => ['type' => 'STRING', 'description' => 'Termino de busqueda'], 'limite' => ['type' => 'INTEGER', 'description' => 'Maximo de resultados']], 'required' => ['termino']]],
                    ['name' => 'obtener_producto', 'description' => 'Obtiene el detalle completo de un producto incluyendo stock y presentaciones.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID del producto (ej: PROD-00001)']], 'required' => ['producto_id']]],
                    ['name' => 'listar_productos', 'description' => 'Lista el catalogo de productos con filtros por categoria, stock y busqueda.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['search' => ['type' => 'STRING', 'description' => 'Termino de busqueda'], 'categoria_id' => ['type' => 'STRING', 'description' => 'Filtrar por categoria'], 'stock_bajo' => ['type' => 'BOOLEAN', 'description' => 'Solo productos con stock bajo'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina']]]],
                    ['name' => 'actualizar_producto', 'description' => 'Actualiza los datos de un producto existente.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID del producto'], 'nombre' => ['type' => 'STRING', 'description' => 'Nuevo nombre'], 'precio_unitario' => ['type' => 'NUMBER', 'description' => 'Nuevo precio'], 'stock_minimo' => ['type' => 'NUMBER', 'description' => 'Nuevo stock minimo']], 'required' => ['producto_id']]],
                    ['name' => 'eliminar_producto', 'description' => 'Eliminacion logica de un producto del catalogo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID del producto a eliminar']], 'required' => ['producto_id']]],
                    ['name' => 'consultar_stock', 'description' => 'Consulta el stock físico y disponible de un producto. Acepta nombre o ID.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID del producto'], 'termino' => ['type' => 'STRING', 'description' => 'Nombre del producto para busqueda']]]],
                    ['name' => 'listar_productos_bajo_stock', 'description' => 'Lista todos los productos con stock por debajo del minimo configurado o agotados.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['limite' => ['type' => 'INTEGER', 'description' => 'Maximo de resultados (defecto 20)']]]],
                    ['name' => 'ajustar_stock', 'description' => 'Ajusta manualmente el stock de un producto. Requiere: producto_id, cantidad, tipo (E/S) y motivo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID del producto'], 'cantidad' => ['type' => 'NUMBER', 'description' => 'Cantidad a ajustar'], 'tipo' => ['type' => 'STRING', 'description' => 'E=entrada (aumentar), S=salida (reducir)'], 'motivo' => ['type' => 'STRING', 'description' => 'Razon del ajuste (merma, correccion, donacion, etc.)'], 'unidades_medida_id' => ['type' => 'STRING', 'description' => 'ID de unidad de medida (defecto: UND-00001)']], 'required' => ['producto_id', 'cantidad', 'tipo', 'motivo']]],
                    ['name' => 'listar_categorias', 'description' => 'Lista todas las categorías de productos disponibles en el sistema con su estado. Usar SIEMPRE que el usuario pregunte cuántas categorías hay, cuáles son las categorías disponibles, muéstrame las categorías, etc.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    // === MODULO 4: PROVEEDORES ===
                    ['name' => 'crear_proveedor', 'description' => 'Registra un nuevo proveedor. Requiere razon_social y ruc.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['razon_social' => ['type' => 'STRING', 'description' => 'Razon social del proveedor'], 'ruc' => ['type' => 'STRING', 'description' => 'RUC del proveedor (11 digitos)'], 'telefono' => ['type' => 'STRING', 'description' => 'Telefono de contacto'], 'email' => ['type' => 'STRING', 'description' => 'Correo electronico'], 'direccion' => ['type' => 'STRING', 'description' => 'Direccion del proveedor']], 'required' => ['razon_social', 'ruc']]],
                    ['name' => 'buscar_proveedor', 'description' => 'Busca proveedores por razon social o RUC.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['termino' => ['type' => 'STRING', 'description' => 'Razon social o RUC a buscar'], 'limite' => ['type' => 'INTEGER', 'description' => 'Maximo de resultados']], 'required' => ['termino']]],
                    ['name' => 'obtener_proveedor', 'description' => 'Obtiene el detalle completo de un proveedor por su ID.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['proveedor_id' => ['type' => 'STRING', 'description' => 'ID del proveedor']], 'required' => ['proveedor_id']]],
                    ['name' => 'listar_proveedores', 'description' => 'Lista todos los proveedores activos con filtros opcionales.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['search' => ['type' => 'STRING', 'description' => 'Termino de busqueda'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina']]]],
                    ['name' => 'actualizar_proveedor', 'description' => 'Actualiza los datos de un proveedor existente.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['proveedor_id' => ['type' => 'STRING', 'description' => 'ID del proveedor'], 'razon_social' => ['type' => 'STRING', 'description' => 'Nueva razon social'], 'telefono' => ['type' => 'STRING', 'description' => 'Nuevo telefono'], 'email' => ['type' => 'STRING', 'description' => 'Nuevo email']], 'required' => ['proveedor_id']]],
                    ['name' => 'eliminar_proveedor', 'description' => 'Eliminacion logica de un proveedor del sistema.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['proveedor_id' => ['type' => 'STRING', 'description' => 'ID del proveedor a eliminar']], 'required' => ['proveedor_id']]],
                    ['name' => 'abrir_formulario_proveedor', 'description' => 'Abre el modal o formulario UI de creación de proveedor con datos precargados.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'razon_social' => ['type' => 'STRING', 'description' => 'Razon social o nombre del proveedor'],
                            'ruc'          => ['type' => 'STRING', 'description' => 'RUC del proveedor (11 digitos)'],
                            'telefono'     => ['type' => 'STRING', 'description' => 'Telefono de contacto'],
                            'direccion'    => ['type' => 'STRING', 'description' => 'Direccion fiscal o comercial'],
                        ],
                    ]],
                    // === MODULO 5: ORDENES DE COMPRA ===
                    ['name' => 'abrir_formulario_orden_compra', 'description' => 'Prepara y abre la pantalla de Nueva Orden de Compra con el proveedor seleccionado y los productos en la lista con sus precios de compra sugeridos. Usar SIEMPRE que el usuario quiera hacer, generar o preparar una orden de compra.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'proveedor_id'      => ['type' => 'STRING', 'description' => 'ID del proveedor (ej: PRV-00005) o termino de busqueda (ej: "valencia")'],
                            'proveedor_nombre'  => ['type' => 'STRING', 'description' => 'Razon social del proveedor si se conoce'],
                            'detalles'          => [
                                'type' => 'ARRAY',
                                'description' => 'Lista de productos a comprar: [{producto_id o nombre, cantidad, unidad_id o factor_conversion, precio_unitario}]',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'producto_id'       => ['type' => 'STRING', 'description' => 'ID del producto (ej: PROD-00004) si se conoce'],
                                        'nombre'            => ['type' => 'STRING', 'description' => 'Nombre del producto (ej: "pepsi", "bonle", "aceite")'],
                                        'cantidad'          => ['type' => 'NUMBER', 'description' => 'Cantidad solicitada'],
                                        'unidad_id'         => ['type' => 'STRING', 'description' => 'ID de la presentacion (ej: UND-00015, o "paquete", "caja")'],
                                        'factor_conversion' => ['type' => 'NUMBER', 'description' => 'Factor de conversion de la presentacion'],
                                        'precio_unitario'   => ['type' => 'NUMBER', 'description' => 'Precio unitario de compra sugerido'],
                                    ],
                                    'required' => ['cantidad'],
                                ],
                            ],
                            'observacion'       => ['type' => 'STRING', 'description' => 'Observaciones adicionales de la orden'],
                        ],
                        'required' => ['proveedor_id', 'detalles'],
                    ]],
                    ['name' => 'limpiar_formulario_orden_compra', 'description' => 'Limpia o vacia la pantalla de Nueva Orden de Compra cuando el usuario solicita descartar el borrador.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    ['name' => 'recuperar_borrador_orden_compra', 'description' => 'Restaura o recupera un borrador de orden de compra recientemente descartado desde la papelera.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    ['name' => 'crear_orden_compra', 'description' => 'Crea una orden de compra a un proveedor en base de datos. Requiere confirmacion del usuario.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['proveedor_id' => ['type' => 'STRING', 'description' => 'ID del proveedor'], 'detalles' => ['type' => 'ARRAY', 'description' => 'Productos a ordenar: [{producto_id, cantidad, precio_unitario}]', 'items' => ['type' => 'OBJECT']], 'observaciones' => ['type' => 'STRING', 'description' => 'Observaciones de la orden']], 'required' => ['proveedor_id', 'detalles']]],
                    ['name' => 'listar_ordenes_compra', 'description' => 'Lista las ordenes de compra con filtros por estado, proveedor y busqueda.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['estado' => ['type' => 'STRING', 'description' => 'P=Pendiente, R=Recibida, A=Anulada'], 'proveedor_id' => ['type' => 'STRING', 'description' => 'Filtrar por proveedor'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina']]]],
                    ['name' => 'obtener_orden_compra', 'description' => 'Obtiene el detalle de una orden de compra especifica.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['orden_id' => ['type' => 'STRING', 'description' => 'ID de la orden de compra']], 'required' => ['orden_id']]],
                    ['name' => 'completar_orden_compra', 'description' => 'Marca una orden de compra como recibida y actualiza el stock del inventario.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['orden_id' => ['type' => 'STRING', 'description' => 'ID de la orden a completar']], 'required' => ['orden_id']]],
                    ['name' => 'cancelar_orden_compra', 'description' => 'Cancela una orden de compra pendiente.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['orden_id' => ['type' => 'STRING', 'description' => 'ID de la orden a cancelar']], 'required' => ['orden_id']]],
                    ['name' => 'actualizar_orden_compra', 'description' => 'Actualiza campos de una orden de compra pendiente.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['orden_id' => ['type' => 'STRING', 'description' => 'ID de la orden'], 'observaciones' => ['type' => 'STRING', 'description' => 'Nuevas observaciones']], 'required' => ['orden_id']]],
                    ['name' => 'obtener_estadisticas_compras', 'description' => 'KPIs de compras a proveedores: total invertido, ordenes por estado.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['dias' => ['type' => 'INTEGER', 'description' => 'Dias a analizar (defecto 30)']]]],
                    // === MODULO 6: CANALES ===
                    ['name' => 'listar_canales', 'description' => 'Lista los canales de pedido disponibles (tienda presencial, WhatsApp, web, etc.)', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    ['name' => 'crear_canal', 'description' => 'Registra un nuevo canal de pedido.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['descripcion' => ['type' => 'STRING', 'description' => 'Nombre o descripcion del canal']], 'required' => ['descripcion']]],
                    ['name' => 'actualizar_canal', 'description' => 'Actualiza la descripcion o estado de un canal existente.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['canal_id' => ['type' => 'STRING', 'description' => 'ID del canal'], 'descripcion' => ['type' => 'STRING', 'description' => 'Nueva descripcion']], 'required' => ['canal_id']]],
                    // === MODULO 7: KARDEX ===
                    ['name' => 'consultar_kardex', 'description' => 'Consulta el Kardex (historial de movimientos) de un producto especifico.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'ID del producto'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Cantidad de movimientos a mostrar']], 'required' => ['producto_id']]],
                    ['name' => 'listar_movimientos', 'description' => 'Lista movimientos de inventario con filtros por producto, tipo, fecha y periodo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['producto_id' => ['type' => 'STRING', 'description' => 'Filtrar por producto'], 'tipo' => ['type' => 'STRING', 'description' => 'E=entradas, S=salidas'], 'fecha_desde' => ['type' => 'STRING', 'description' => 'Fecha inicio (YYYY-MM-DD)'], 'fecha_hasta' => ['type' => 'STRING', 'description' => 'Fecha fin (YYYY-MM-DD)'], 'dias' => ['type' => 'INTEGER', 'description' => 'Ultimos N dias'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina']]]],
                    ['name' => 'obtener_resumen_kardex', 'description' => 'Resumen del Kardex: total entradas, salidas y movimientos en un periodo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['dias' => ['type' => 'INTEGER', 'description' => 'Dias a analizar (defecto 30)']]]],
                    // === MODULO 8: REPORTES ===
                    ['name' => 'obtener_estadisticas_generales', 'description' => 'Estadisticas generales del dashboard: pedidos, despacho, eficiencia y flujo de ordenes.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['dias' => ['type' => 'INTEGER', 'description' => 'Periodo en dias (1, 7, 15, 30)']]]],
                    ['name' => 'obtener_ventas_por_periodo', 'description' => 'Ventas completadas agrupadas por dia en un periodo de tiempo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['dias' => ['type' => 'INTEGER', 'description' => 'Ultimos N dias (defecto 30)']]]],
                    ['name' => 'obtener_top_productos', 'description' => 'Lista los productos mas vendidos por cantidad y monto facturado en un periodo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['limite' => ['type' => 'INTEGER', 'description' => 'Numero de productos a listar (defecto 10)'], 'dias' => ['type' => 'INTEGER', 'description' => 'Periodo en dias (defecto 30)']]]],
                    ['name' => 'obtener_top_clientes', 'description' => 'Lista los clientes con mayor monto de compras en un periodo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['limite' => ['type' => 'INTEGER', 'description' => 'Numero de clientes (defecto 10)'], 'dias' => ['type' => 'INTEGER', 'description' => 'Periodo en dias (defecto 30)']]]],
                    ['name' => 'obtener_reporte_inventario', 'description' => 'Obtiene el balance consolidado del inventario: valor total valorizado, productos agotados y con bajo stock. Usar para preguntas sobre el valor o balance general del inventario.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
                    // === MODULO 9: NOTIFICACIONES ===
                    ['name' => 'listar_alertas', 'description' => 'Lista las alertas y notificaciones del sistema con filtros por tipo y estado de lectura.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['tipo' => ['type' => 'STRING', 'description' => 'Tipo de alerta: merma, warning, info, etc.'], 'solo_no_leidas' => ['type' => 'BOOLEAN', 'description' => 'Solo mostrar alertas no leidas'], 'per_page' => ['type' => 'INTEGER', 'description' => 'Resultados por pagina']]]],
                    ['name' => 'marcar_alerta_leida', 'description' => 'Marca una notificacion especifica como leida.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['notificacion_id' => ['type' => 'STRING', 'description' => 'ID de la notificacion (ej: NOT-00001)']], 'required' => ['notificacion_id']]],
                    ['name' => 'notificar_pedidos_por_expirar', 'description' => 'Detecta pedidos proximos a expirar y crea notificaciones de alerta para el equipo.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['margen_horas' => ['type' => 'INTEGER', 'description' => 'Horas de margen antes de expirar para notificar (defecto 2)']]]],
                    // === MODULO 10: UTILIDADES ===
                    ['name' => 'solicitar_campo_faltante', 'description' => 'Solicita al usuario que proporcione campos faltantes necesarios para completar una operacion.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['campos' => ['type' => 'ARRAY', 'description' => 'Lista de campos que faltan', 'items' => ['type' => 'STRING']], 'contexto' => ['type' => 'STRING', 'description' => 'Descripcion de la operacion que se esta realizando'], 'pregunta' => ['type' => 'STRING', 'description' => 'Pregunta clara para el usuario']], 'required' => ['campos']]],
                    ['name' => 'confirmar_accion', 'description' => 'Solicita confirmacion explota del usuario antes de ejecutar acciones destructivas (cancelar, eliminar, ajustar stock).', 'parameters' => ['type' => 'OBJECT', 'properties' => ['accion' => ['type' => 'STRING', 'description' => 'Nombre de la accion a confirmar'], 'descripcion' => ['type' => 'STRING', 'description' => 'Descripcion clara de lo que se va a hacer'], 'impacto' => ['type' => 'STRING', 'description' => 'Impacto de la accion (irreversible, afecta stock, etc.)'], 'tool_siguiente' => ['type' => 'STRING', 'description' => 'Nombre de la tool a ejecutar si el usuario confirma'], 'args_siguientes' => ['type' => 'OBJECT', 'description' => 'Argumentos para la tool siguiente']], 'required' => ['accion', 'descripcion']]],
                    ['name' => 'buscar_global', 'description' => 'Busqueda cruzada en productos, clientes y proveedores con un solo termino.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['termino' => ['type' => 'STRING', 'description' => 'Termino a buscar en todas las entidades']], 'required' => ['termino']]],
                    ['name' => 'obtener_ayuda', 'description' => 'Muestra el catalogo de capacidades de Valencia AI y como usarlas.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['modulo' => ['type' => 'STRING', 'description' => 'Modulo especifico: pedidos, clientes, productos, proveedores, ordenes_compra, kardex, reportes, alertas']]]],
                    // === MODULO 11: BI CONVERSACIONAL — GRAFICOS Y ANALISIS DINAMICOS ===
                    ['name' => 'generar_grafico_dashboard', 'description' => 'Genera un grafico dinamico (pie, donut, bar, horizontal_bar, line, area, stacked_bar) con datos reales del negocio. USAR cuando el usuario pida ver metricas, graficos, estadisticas o resumenes visuales. Si el usuario pide ultimos N dias, usar dias=N o metrica=ventas_por_dia.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'tipo_grafico' => ['type' => 'STRING', 'enum' => ['pie', 'donut', 'bar', 'horizontal_bar', 'line', 'area', 'stacked_bar'], 'description' => 'Tipo de grafico a renderizar. Elegir automaticamente si el usuario no lo especifica.'],
                            'metrica'      => ['type' => 'STRING', 'enum' => ['pedidos_por_estado', 'pedidos_por_canal', 'ventas_del_dia', 'ventas_por_dia', 'top_productos', 'top_clientes', 'stock_critico', 'errores_por_tipo', 'roturas_por_categoria', 'tiempo_busqueda_promedio'], 'description' => 'Metrica: ventas_por_dia (evolucion de ventas en el tiempo, ultimos 7 dias, etc.), ventas_del_dia (ventas de hoy por canal), pedidos_por_estado, pedidos_por_canal, top_productos, top_clientes, stock_critico.'],
                            'dias'         => ['type' => 'INTEGER', 'description' => 'Dias a considerar hacia atras (ej: 7 para ultimos 7 dias, 15, 30). Obligatorio si el usuario pide ultimos N dias o esta semana.'],
                            'agrupacion'   => ['type' => 'STRING', 'enum' => ['canal', 'categoria', 'producto', 'cliente', 'fecha', 'estado'], 'description' => 'Como agrupar los datos (opcional).'],
                            'filtros'      => ['type' => 'OBJECT', 'properties' => [
                                'fecha_desde' => ['type' => 'STRING', 'description' => 'Fecha inicio YYYY-MM-DD.'],
                                'fecha_hasta' => ['type' => 'STRING', 'description' => 'Fecha fin YYYY-MM-DD.'],
                                'dias'        => ['type' => 'INTEGER', 'description' => 'Dias hacia atras (ej: 7).'],
                                'canal'       => ['type' => 'STRING', 'description' => 'Filtrar por ID de canal.'],
                                'categoria'   => ['type' => 'STRING', 'description' => 'Filtrar por ID de categoria.'],
                            ]],
                        ],
                        'required' => ['tipo_grafico', 'metrica'],
                    ]],
                    ['name' => 'consultar_kpi', 'description' => 'Consulta un indicador oficial de la investigacion: PODE (% ordenes completadas), PEOR (% errores), PRS (% roturas de stock), TBPP (tiempo promedio de busqueda) o TSA (tasa de servicio IA). USAR cuando el usuario pregunte por indicadores, KPIs o metricas de rendimiento.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'indicador' => ['type' => 'STRING', 'enum' => ['PODE', 'PEOR', 'PRS', 'TBPP', 'TSA'], 'description' => 'Indicador a consultar.'],
                            'periodo'   => ['type' => 'STRING', 'description' => 'Periodo a consultar: YYYY-MM (ej: 2026-09) o YYYY-Wnn (ej: 2026-W37). Por defecto: mes actual.'],
                        ],
                        'required' => ['indicador'],
                    ]],
                    ['name' => 'generar_reporte_periodo', 'description' => 'Genera un reporte ejecutivo integral consolidado del periodo con metricas de pedidos, ventas y alertas. USAR cuando el usuario pida un reporte diario, semanal o mensual completo.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'tipo_reporte'     => ['type' => 'STRING', 'enum' => ['diario', 'semanal', 'mensual'], 'description' => 'Tipo de reporte a generar.'],
                            'fecha_desde'      => ['type' => 'STRING', 'description' => 'Fecha inicio YYYY-MM-DD.'],
                            'fecha_hasta'      => ['type' => 'STRING', 'description' => 'Fecha fin YYYY-MM-DD.'],
                            'incluir_graficos' => ['type' => 'BOOLEAN', 'description' => 'Si true, incluir graficos en la respuesta.'],
                        ],
                        'required' => ['tipo_reporte'],
                    ]],
                    ['name' => 'comparar_periodos', 'description' => 'Compara dos periodos temporales para una metrica (ventas, pedidos, errores, roturas) y muestra la variacion porcentual con grafico. USAR cuando el usuario diga: "compara esta semana vs la anterior", "este mes vs el pasado", etc.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'metrica'          => ['type' => 'STRING', 'enum' => ['ventas', 'pedidos', 'errores', 'roturas'], 'description' => 'Metrica a comparar.'],
                            'periodo_actual'   => ['type' => 'STRING', 'description' => 'Periodo actual en formato YYYY-MM o YYYY-Wnn.'],
                            'periodo_anterior' => ['type' => 'STRING', 'description' => 'Periodo anterior en formato YYYY-MM o YYYY-Wnn.'],
                        ],
                        'required' => ['metrica', 'periodo_actual', 'periodo_anterior'],
                    ]],
                    ['name' => 'renderizar_tabla', 'description' => 'Muestra una tabla interactiva de datos en el chat con pedidos, productos, clientes o movimientos filtrados. USAR cuando el usuario pida una lista detallada, tabla o reporte en formato de filas y columnas.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'entidad'  => ['type' => 'STRING', 'enum' => ['pedidos', 'productos', 'clientes', 'movimientos'], 'description' => 'Entidad a listar.'],
                            'filtros'  => ['type' => 'OBJECT', 'properties' => [
                                'estado'      => ['type' => 'STRING', 'description' => 'Filtrar por estado (P, C, A).'],
                                'fecha_desde' => ['type' => 'STRING', 'description' => 'Fecha inicio YYYY-MM-DD.'],
                                'fecha_hasta' => ['type' => 'STRING', 'description' => 'Fecha fin YYYY-MM-DD.'],
                                'categoria'   => ['type' => 'STRING', 'description' => 'Filtrar por categoria.'],
                            ]],
                            'limite'   => ['type' => 'INTEGER', 'description' => 'Cantidad maxima de filas a mostrar (defecto 10, maximo 50).'],
                        ],
                        'required' => ['entidad'],
                    ]],
                    ['name' => 'listar_graficos_disponibles', 'description' => 'Muestra el catalogo completo de graficos y analisis estadisticos disponibles en Valencia AI con botones y opciones para que el usuario elija. USAR OBLIGATORIAMENTE cuando el usuario diga "estadisticas generales", pregunte que graficos o estadisticas puede ver, o solicite opciones visuales.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'modulo' => ['type' => 'STRING', 'description' => 'Filtro opcional (ventas, pedidos, inventario, kpis).'],
                        ],
                    ]],
                    // === MODULO 12: PREDICCIONES DE REABASTECIMIENTO ===
                    ['name' => 'obtener_predicciones_compra', 'description' => 'Obtiene las sugerencias de reabastecimiento semanal para productos de Comercial Valencia. Calcula demanda proyectada, stock objetivo, cantidad sugerida y precio referencia. Usar cuando el usuario pregunte qué debe comprar, cuánto reponer, qué productos faltan, cuál es la demanda semanal, o quiera saber las sugerencias de compra.', 'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'producto_id'  => ['type' => 'STRING', 'description' => 'ID del producto (ej: PROD-00001). Usar si el usuario consulta un producto específico.'],
                            'categoria_id' => ['type' => 'STRING', 'description' => 'ID de la categoría para filtrar (ej: CAT-00001). Opcional.'],
                            'semana'       => ['type' => 'STRING', 'description' => 'Semana ISO a consultar (ej: 2026-W38). Default: semana actual.'],
                            'limite'       => ['type' => 'INTEGER', 'description' => 'Máximo de resultados a devolver (default: 10).'],
                        ],
                    ]],
                ],
            ],
        ];

        if ($readOnly) {
            $tools[0]['functionDeclarations'] = array_values(array_filter(
                $tools[0]['functionDeclarations'],
                fn ($t) => in_array($t['name'], GeminiToolsService::READ_ONLY_TOOLS, true)
            ));
        }

        if (!empty($userQuery)) {
            $tools[0]['functionDeclarations'] = $this->filterDeclarationsByQuery(
                $tools[0]['functionDeclarations'],
                $userQuery
            );
        }

        return $tools;
    }

    /**
     * Filtra dinámicamente las herramientas enviadas a Gemini según la intención y palabras clave de la consulta.
     * Reduce el payload de 31 KB (12,000 tokens) a ~5-12 KB (1,500-2,500 tokens), acelerando la respuesta
     * de 15s a menos de 2.5s y evitando errores de saturación (HTTP 503 / 429).
     */
    protected function filterDeclarationsByQuery(array $declarations, string $query): array
    {
        $byName = [];
        foreach ($declarations as $d) {
            $byName[$d['name']] = $d;
        }

        $q = mb_strtolower($query);

        $isBi = (bool) preg_match('/grafic|gr\x{00e1}fic|barra|pastel|pie|donut|torta|linea|l\x{00ed}nea|tendencia|kpi|tabla|dashboard|estad\x{00ed}stic|estadistic|resumen|m\x{00e9}tric|metric|comparar|exportar|reporte|top|ventas|despacho|an\x{00e1}lisis|analisis/u', $q);
        $isPedido = (bool) preg_match('/pedi|pedid|ped-|orden|ordenes|crea|armar|haz|nuevo|cancel|anul|complet|modific|borrador|compr|vend|despach|envio|env\x{00ed}o|factur/u', $q);
        $isStock = (bool) preg_match('/stock|inventario|producto|productos|almacen|almac\x{00e9}n|agotado|m\x{00ed}nimo|minimo|kardex|k\x{00e1}rdex|ajuste|precio|categor\x{00ed}a|categoria/u', $q);
        $isCliente = (bool) preg_match('/cliente|don|do\x{00f1}a|ruc|dni|contacto|telefono|tel\x{00e9}fono|direcci\x{00f3}n|direccion|comprador/u', $q);
        $isProveedor = (bool) preg_match('/proveedor|proveedores|abastecer|compra|compras|orden.*compra|ordenes.*compra/u', $q);
        $isAlerta = (bool) preg_match('/alerta|alertas|notificaci\x{00f3}n|notificacion|aviso|avisos|expirar/u', $q);
        $isConfirmation = (bool) preg_match('/confirm|si|s\x{00ed}|procede|adelante|hazlo|dale|correcto|de acuerdo|ejecuta|canc[eé]lal|complet|anul|elimina|guarda|acepto/ui', $q);

        $isSearch = (bool) preg_match('/busc|encuentr|rastre|donde|d\x{00f3}nde|global/u', $q);

        $isReabastecimiento = (bool) preg_match('/reabastec|reposici[oó]n|reponer|predic|suger|comprar esta semana|qu[eé] comprar|cu[aá]nto comprar|cu[aá]nto pedir|pedir esta semana|demanda semanal|stock cr[ií]tico|abastec|qu[eé] falta|qu[eé] me falta|productos cr[ií]ticos|falta stock/ui', $q);

        $selected = [];
        $core = ['obtener_ayuda'];
        if ($isSearch) {
            $core[] = 'buscar_global';
        }
        foreach ($core as $c) {
            if (isset($byName[$c])) $selected[$c] = $byName[$c];
        }

        if ($isBi) {
            $biTools = [
                'listar_graficos_disponibles', 'generar_grafico_dashboard', 'consultar_kpi', 'generar_reporte_periodo', 'comparar_periodos', 'renderizar_tabla',
                'obtener_ventas_por_periodo', 'obtener_pedidos_del_dia'
            ];
            foreach ($biTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        if ($isPedido && !$isBi) {
            $pedidoTools = [
                'abrir_formulario_pedido', 'crear_pedido_cliente', 'limpiar_formulario_pedido', 'recuperar_borrador_pedido', 'listar_pedidos',
                'obtener_pedido', 'completar_pedido', 'cancelar_pedido', 'actualizar_pedido', 'eliminar_pedido',
                'obtener_pedidos_del_dia', 'buscar_cliente', 'crear_cliente', 'abrir_formulario_cliente', 'buscar_producto', 'consultar_stock'
            ];
            foreach ($pedidoTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        if ($isStock) {
            $stockTools = [
                'consultar_stock', 'buscar_producto', 'listar_productos_bajo_stock', 'listar_productos',
                'obtener_producto', 'ajustar_stock', 'consultar_kardex', 'listar_categorias'
            ];
            foreach ($stockTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        if ($isCliente) {
            $clienteTools = [
                'buscar_cliente', 'obtener_cliente', 'listar_clientes', 'crear_cliente',
                'obtener_historial_cliente', 'abrir_formulario_cliente', 'abrir_formulario_pedido'
            ];
            foreach ($clienteTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        if ($isProveedor) {
            $provTools = [
                'buscar_proveedor', 'obtener_proveedor', 'listar_proveedores', 'crear_proveedor', 'abrir_formulario_proveedor',
                'abrir_formulario_orden_compra', 'crear_orden_compra', 'limpiar_formulario_orden_compra', 'recuperar_borrador_orden_compra',
                'listar_ordenes_compra', 'obtener_orden_compra', 'completar_orden_compra', 'cancelar_orden_compra'
            ];
            foreach ($provTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        if ($isAlerta) {
            $alertaTools = ['listar_alertas', 'marcar_alerta_leida', 'notificar_pedidos_por_expirar'];
            foreach ($alertaTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        if ($isReabastecimiento) {
            $reabastecimientoTools = [
                'obtener_predicciones_compra', 'buscar_producto', 'listar_categorias',
                'consultar_stock', 'listar_productos_bajo_stock'
            ];
            foreach ($reabastecimientoTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        // Si es una confirmación de acción, asegurar todas las herramientas de ejecución / mutación
        if ($isConfirmation) {
            $actionTools = [
                'cancelar_pedido', 'completar_pedido', 'crear_pedido_cliente', 'actualizar_pedido', 'eliminar_pedido',
                'ajustar_stock', 'cancelar_orden_compra', 'completar_orden_compra', 'crear_orden_compra',
                'eliminar_cliente', 'eliminar_producto', 'eliminar_proveedor'
            ];
            foreach ($actionTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        if ($isBi) {
            $intent = 'BI';
        } elseif ($isPedido) {
            $intent = 'pedidos';
        } elseif ($isStock) {
            $intent = 'stock';
        } elseif ($isCliente) {
            $intent = 'clientes';
        } elseif ($isProveedor) {
            $intent = 'proveedores';
        } elseif ($isAlerta) {
            $intent = 'alertas';
        } elseif ($isConfirmation) {
            $intent = 'confirmacion';
        } else {
            $intent = 'general';
        }

        // Si la consulta es corta, general o no coincidió con ninguna categoría específica, proveer catálogo base compacto con soporte de acción
        if (count($selected) <= 2) {
            $defaultTools = [
                'cancelar_pedido', 'completar_pedido', 'crear_pedido_cliente',
                'abrir_formulario_pedido', 'listar_pedidos', 'obtener_pedidos_del_dia',
                'consultar_stock', 'buscar_producto', 'buscar_cliente',
                'listar_graficos_disponibles', 'generar_grafico_dashboard', 'consultar_kpi', 'buscar_global', 'obtener_ayuda'
            ];
            foreach ($defaultTools as $t) { if (isset($byName[$t])) $selected[$t] = $byName[$t]; }
        }

        $toolsSent = count($selected);
        $toolsTotal = count($declarations);
        $hitRatio = $toolsTotal > 0 ? round(($toolsSent / $toolsTotal) * 100, 1) . '%' : '100%';

        Log::info('Tool filter', [
            'query'          => mb_substr($query, 0, 50),
            'intencion'      => $intent,
            'tools_enviadas' => $toolsSent,
            'tools_totales'  => $toolsTotal,
            'hit_ratio'      => $hitRatio,
        ]);

        $this->lastFilterMetadata = [
            'intent'         => $intent,
            'tools_sent'     => $toolsSent,
            'tools_total'    => $toolsTotal,
            'hit_ratio'      => $hitRatio,
        ];

        return array_values($selected);
    }

    /**
     * Procesar y estructurar la respuesta JSON de Gemini.
     */
    protected function parseResponse(array $raw): array
    {
        $candidate = $raw['candidates'][0] ?? null;
        $textParts = [];
        $functionCalls = [];

        if ($candidate && isset($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                // Texto de respuesta
                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }

                // Detección de Tools / Function Calls
                if (isset($part['functionCall'])) {
                    $functionCalls[] = [
                        'name' => $part['functionCall']['name'] ?? null,
                        'args' => $part['functionCall']['args'] ?? [],
                    ];
                }
            }
        }

        return [
            'text' => implode("\n", $textParts),
            'has_function_calls' => !empty($functionCalls),
            'function_calls' => $functionCalls,
            'finish_reason' => $candidate['finishReason'] ?? 'UNKNOWN',
            'model_version' => $raw['modelVersion'] ?? $this->model,
            'usage' => $raw['usageMetadata'] ?? [],
            'raw_candidate' => $candidate,
        ];
    }

    /**
     * Segunda llamada a Gemini: enviar el resultado de la tool como functionResponse.
     * Gemini procesa el resultado y devuelve texto natural para el usuario.
     *
     * @param array  $originalContents  Historial de contents de la primera llamada
     * @param array  $rawCandidate      Candidato original devuelto por Gemini (incluye thoughtSignature)
     * @param string $functionName      Nombre de la tool ejecutada
     * @param array  $functionResult    Resultado de la ejecucion de la tool
     * @param string|null $systemInstruction
     * @param array  $tools              Herramientas disponibles para llamadas consecutivas (multi-tool loop)
     * @return array
     */
    public function appendFunctionTurn(
        array $contents,
        array $rawCandidate,
        string $functionName,
        array $functionResult
    ): array {
        // Turno del modelo: tomar el content exacto de Gemini (preserva thoughtSignature y partes)
        $modelContent = $rawCandidate['content'] ?? [
            'role' => 'model',
            'parts' => [
                ['functionCall' => ['name' => $functionName, 'args' => (object) []]],
            ],
        ];

        // Normalizar args para evitar que arrays asociativos vacios se serialicen como []
        if (isset($modelContent['parts']) && is_array($modelContent['parts'])) {
            foreach ($modelContent['parts'] as &$part) {
                if (isset($part['functionCall'])) {
                    if (empty($part['functionCall']['args'])) {
                        $part['functionCall']['args'] = (object) [];
                    }
                }
            }
            unset($part);
        }

        $contents[] = $modelContent;

        // Agregar el turno con el resultado de la funcion enriquecido
        $responsePayload = [
            'name'            => $functionName,
            'success'         => $functionResult['success'] ?? true,
            'datos'           => $functionResult['data'] ?? null,
            'resumen_tecnico' => $functionResult['message'] ?? '',
            'instruccion'     => 'Sintetiza estos datos y responde al usuario en lenguaje natural y conversacional. Si la intención del usuario requiere una acción consecutiva (por ejemplo, registrar el pedido tras encontrar el producto o verificar el stock), ejecuta la siguiente tool de inmediato.',
        ];

        if (isset($functionResult['total_encontrados'])) {
            $responsePayload['total_encontrados'] = $functionResult['total_encontrados'];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [
                [
                    'functionResponse' => [
                        'name'     => $functionName,
                        'response' => $responsePayload,
                    ],
                ],
            ],
        ];

        return $contents;
    }

    public function sendFunctionResponse(
        array $originalContents,
        array $rawCandidate,
        string $functionName,
        array $functionResult,
        ?string $systemInstruction = null,
        array $tools = []
    ): array {
        $contents = $this->appendFunctionTurn($originalContents, $rawCandidate, $functionName, $functionResult);
        return $this->generateContent($contents, $systemInstruction, $tools);
    }

    /**
     * Agrega un turno con MÚLTIPLES functionResponses coincidentes con las llamadas paralelas del modelo.
     * Requisito estricto de Gemini: cada functionCall en modelContent['parts'] DEBE tener su functionResponse correspondiente.
     *
     * @param array $contents
     * @param array $rawCandidate
     * @param array $executedTools Array de ['name' => $name, 'result' => $result]
     * @return array
     */
    public function appendMultipleFunctionTurns(
        array $contents,
        array $rawCandidate,
        array $executedTools
    ): array {
        $modelContent = $rawCandidate['content'] ?? [
            'role' => 'model',
            'parts' => [],
        ];

        // Normalizar args en las partes de functionCall
        if (isset($modelContent['parts']) && is_array($modelContent['parts'])) {
            foreach ($modelContent['parts'] as &$part) {
                if (isset($part['functionCall']) && empty($part['functionCall']['args'])) {
                    $part['functionCall']['args'] = (object) [];
                }
            }
            unset($part);
        }

        $contents[] = $modelContent;

        // Construir partes de functionResponse para cada tool ejecutada en este turno
        $responseParts = [];
        foreach ($executedTools as $item) {
            $fnName = $item['name'];
            $fnResult = $item['result'];

            $responsePayload = [
                'name'            => $fnName,
                'success'         => $fnResult['success'] ?? true,
                'datos'           => $fnResult['data'] ?? null,
                'resumen_tecnico' => $fnResult['message'] ?? '',
                'instruccion'     => 'Sintetiza estos datos y responde al usuario en lenguaje natural y conversacional.',
            ];

            if (isset($fnResult['total_encontrados'])) {
                $responsePayload['total_encontrados'] = $fnResult['total_encontrados'];
            }

            $responseParts[] = [
                'functionResponse' => [
                    'name'     => $fnName,
                    'response' => $responsePayload,
                ],
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => $responseParts,
        ];

        return $contents;
    }

    /**
     * Streamea la síntesis de texto final token a token usando streamGenerateContent con SSE.
     * Permite que el usuario empiece a leer en 2-3 segundos.
     *
     * @param array $contents
     * @param string|null $systemInstruction
     * @param callable|null $onToken Callback fn(string $token)
     * @return array ['success' => bool, 'text' => string, 'model' => string]
     */
    public function streamSynthesis(
        array $contents,
        ?string $systemInstruction = null,
        ?callable $onToken = null
    ): array {
        $apiKey = $this->apiKey;
        $model = $this->model ?: 'gemini-3.6-flash';
        $url = "{$this->baseUrl}/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";

        $payload = ['contents' => $contents];
        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        $fullText = '';
        $buffer = '';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$fullText, &$buffer, $onToken) {
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $event) as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'data: ')) {
                        $jsonStr = substr($line, 6);
                        $data = json_decode($jsonStr, true);
                        $token = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        if ($token !== '') {
                            $fullText .= $token;
                            if ($onToken) {
                                $onToken($token);
                            }
                        }
                    }
                }
            }
            return strlen($chunk);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'text'    => $fullText,
            'model'   => $model,
        ];
    }

    /**
     * Transcribe un archivo de audio utilizando Gemini Multimodal (con clave y canal de logs dedicados).
     *
     * @param string $audioBytes O ruta al archivo de audio
     * @param string $mimeType MIME type del audio (ej: audio/webm, audio/mp4, audio/wav)
     * @return array ['success' => bool, 'text' => string, 'model' => string, 'duration_ms' => float]
     * @throws Exception
     */
    public function transcribeAudio(string $audioData, string $mimeType = 'audio/webm'): array
    {
        $apiKey = $this->transcribeApiKey ?: $this->apiKey;
        if (empty($apiKey)) {
            throw new Exception('No se ha configurado la clave de API para transcripción (GEMINI_TRANSCRIBE_KEY / GEMINI_API_KEY).');
        }

        // Limpiar el MIME type (quitar parámetros como ;codecs=opus)
        $cleanMime = explode(';', $mimeType)[0];
        $cleanMime = trim(strtolower($cleanMime));
        if (str_contains($cleanMime, 'm4a') || $cleanMime === 'audio/x-m4a') {
            $cleanMime = 'audio/mp4';
        }

        // Si se pasa una ruta de archivo existente, leer los bytes
        if (strlen($audioData) < 1000 && @file_exists($audioData)) {
            $binaryData = file_get_contents($audioData);
        } else {
            $binaryData = $audioData;
        }

        $base64Data = base64_encode($binaryData);
        $audioSizeBytes = strlen($binaryData);
        $audioSizeKb = round($audioSizeBytes / 1024, 2);

        // Modelos de transcripción optimizados para baja latencia (<4s) y audio webm/opus
        $candidateModels = array_values(array_unique([
            $this->transcribeModel ?: 'gemini-3.1-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.1-flash-lite-preview',
            'gemini-3.6-flash',
        ]));

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $cleanMime,
                                'data'     => $base64Data,
                            ],
                        ],
                        [
                            'text' => "INSTRUCCIÓN ESTRICTA DE TRANSCRIPCIÓN COMERCIAL:\n" .
                                      "Eres un transcriptor literal de audio a texto para una distribuidora y comercializadora en Perú.\n" .
                                      "Tu ÚNICA tarea es escribir textualmente en español lo que se escucha en este audio, palabra por palabra, con puntuación natural.\n" .
                                      "Reconoce con alta precisión marcas comerciales (ej: Pepsi, San Carlos, San Mateo, Gloria, Bonle, etc.), números, documentos (DNI, RUC) y unidades de empaque (paquetes, packs, six pack, fardos, cajas, botellas, litros, unidades).\n\n" .
                                      "REGLAS CRÍTICAS:\n" .
                                      "1. NUNCA respondas al audio como interlocutor o chatbot. NO saludes, NO confirmes órdenes, NO ejecutes acciones.\n" .
                                      "2. NO añadas comillas que envuelvan el texto ni aclaraciones de 'Transcripción:'.\n" .
                                      "3. Si el audio es silencio absoluto, ruidos no vocales o no contiene palabras comprensibles, responde ÚNICAMENTE con una cadena vacía.\n" .
                                      "4. Devuelve SOLAMENTE las palabras habladas transcritas fielmente.",
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.0,
                'maxOutputTokens' => 500,
            ],
        ];

        $lastException = null;

        foreach ($candidateModels as $idx => $modelToTry) {
            $startTime = microtime(true);
            $url = "{$this->baseUrl}/models/{$modelToTry}:generateContent";
            $urlWithKey = str_contains($url, '?') ? "{$url}&key={$apiKey}" : "{$url}?key={$apiKey}";

            try {
                $response = Http::withOptions([
                    'force_ip_resolve' => 'v4',
                    'timeout'          => 25,
                ])->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type'   => 'application/json',
                ])->post($urlWithKey, $payload);

                $durationMs = round((microtime(true) - $startTime) * 1000, 2);

                if (!$response->successful()) {
                    $body = $response->json();
                    $errorMsg = $body['error']['message'] ?? $response->body();
                    $statusCode = $response->status();

                    Log::channel('voice_transcription')->warning("Gemini Voice: Error HTTP {$statusCode} con modelo {$modelToTry}", [
                        'error'       => $errorMsg,
                        'duration_ms' => $durationMs,
                        'attempt'     => $idx + 1,
                    ]);

                    // Para errores recuperables (alta demanda, sobrecarga), siempre intentar el siguiente modelo
                    if (in_array($statusCode, [404, 429, 500, 503], true)) {
                        $lastException = new Exception("Error en Gemini Voice ({$statusCode}): {$errorMsg}");
                        continue; // → siguiente modelo en la lista
                    }

                    throw new Exception("Error en Gemini Voice ({$statusCode}): {$errorMsg}");
                }

                $data = $response->json();
                $candidates = $data['candidates'] ?? [];
                $rawText = '';

                if (!empty($candidates[0]['content']['parts'][0]['text'])) {
                    $rawText = $candidates[0]['content']['parts'][0]['text'];
                }

                // Limpiar texto de prefijos de pensamiento o análisis y comillas envolventes
                $cleanText = trim($rawText);
                $cleanText = preg_replace('/^thought\s+/iu', '', $cleanText);
                $cleanText = preg_replace('/^análisis\s+de\s+audio:\s*/iu', '', $cleanText);
                $cleanText = preg_replace('/^["\']|["\']$/u', '', $cleanText);
                $cleanText = trim($cleanText);

                $tokensUsed = $data['usageMetadata']['totalTokenCount'] ?? 0;

                // FALLBACK: si el texto está vacío y los tokens son muy bajos (<150),
                // el modelo no reconoció el audio correctamente → intentar el siguiente.
                if ($cleanText === '' && $tokensUsed < 150 && $idx < count($candidateModels) - 1) {
                    Log::channel('voice_transcription')->warning("Gemini Voice: Texto vacío con pocos tokens ({$tokensUsed}), pasando al siguiente modelo", [
                        'model'       => $modelToTry,
                        'duration_ms' => $durationMs,
                        'size_kb'     => $audioSizeKb,
                        'mime'        => $cleanMime,
                        'attempt'     => $idx + 1,
                    ]);
                    continue;
                }

                // Si la respuesta tardó más de 10 segundos, emitir advertencia de rendimiento
                if ($durationMs > 10000) {
                    Log::channel('voice_transcription')->warning("Gemini Voice: Transcripción lenta ({$durationMs} ms)", [
                        'model'       => $modelToTry,
                        'size_kb'     => $audioSizeKb,
                        'tokens'      => $tokensUsed,
                    ]);
                } else {
                    Log::channel('voice_transcription')->info("Gemini Voice: Transcripción exitosa", [
                        'model'       => $modelToTry,
                        'duration_ms' => $durationMs,
                        'size_kb'     => $audioSizeKb,
                        'tokens'      => $tokensUsed,
                        'mime'        => $cleanMime,
                        'fallback'    => $idx > 0,
                        'text_empty'  => ($cleanText === ''),
                    ]);
                }

                return [
                    'success'     => true,
                    'text'        => $cleanText,
                    'model'       => $modelToTry,
                    'duration_ms' => $durationMs,
                    'size_kb'     => $audioSizeKb,
                    'tokens'      => $tokensUsed,
                ];

            } catch (Exception $e) {
                $lastException = $e;
                Log::channel('voice_transcription')->error("Gemini Voice Exception [{$modelToTry}]", [
                    'message' => $e->getMessage(),
                    'attempt' => $idx + 1,
                ]);

                if ($idx < count($candidateModels) - 1) {
                    continue;
                }
            }
        }

        throw $lastException ?: new Exception('No se pudo transcribir el audio con ninguno de los modelos disponibles.');
    }
}
