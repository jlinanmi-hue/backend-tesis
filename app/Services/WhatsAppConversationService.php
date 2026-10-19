<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Pedido;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppConversationService
 *
 * Administra el ciclo conversacional para clientes que interactúan a través de WhatsApp.
 * - TTL de sesión: 45 minutos.
 * - Buffer dual: 8 mensajes para contexto activo de IA, 20 mensajes para auditoría de tesis.
 * - Reglas de negocio: Cotización interna automática, aislamiento estricto de historial por teléfono,
 *   registro ligero (solo nombre y dirección), vocabulario cliente-facing ("pedido"),
 *   y doble barrera de seguridad.
 */
class WhatsAppConversationService
{
    public const SESSION_TTL = 2700; // 45 minutos en segundos
    public const MAX_CONTEXT_MESSAGES = 8;
    public const MAX_AUDIT_MESSAGES = 20;

    public const SYSTEM_INSTRUCTION_WHATSAPP = <<<'PROMPT'
Eres el asistente virtual de Comercial Valencia. Atiendes a clientes por WhatsApp de forma cercana, breve y profesional.

═══════════════════════════════════════
REGLAS DE TONO Y VOCABULARIO
═══════════════════════════════════════
- Saluda con calidez: "¡Hola! ¿Cómo estás? 😊" (o usando su nombre si ya lo conoces).
- Usa SIEMPRE la palabra "pedido" con el cliente.
  NUNCA uses la palabra "cotización" — es un término interno de Comercial Valencia.
- Frases cortas y claras. Un mensaje = una idea principal.
- Emojis moderados y amigables (1-2 por mensaje).
- Negritas con *asterisco simple* (*texto*), NUNCA con asteriscos dobles (**texto**).
- Listas con viñetas limpias (•).
- Sin tablas, sin bloques de código, sin encabezados Markdown (#).
- No menciones que estás usando WhatsApp (es obvio para el cliente).

═══════════════════════════════════════
REGISTRO DE CLIENTE NUEVO
═══════════════════════════════════════
Cuando un cliente nuevo quiera hacer un pedido, pídele ÚNICAMENTE:
1. Nombre completo
2. Dirección de entrega

- NO le pidas RUC ni DNI (es totalmente opcional; si el cliente lo proporciona voluntariamente, guárdalo, pero nunca lo exijas).
- NO le pidas su número de WhatsApp o celular (el sistema ya lo tiene automáticamente).
- No valides con SUNAT durante el registro.
- En cuanto te dé nombre y dirección, regístralo inmediatamente usando la herramienta crear_cliente.

═══════════════════════════════════════
CONSULTA DE PRODUCTOS Y STOCK
═══════════════════════════════════════
- Si el cliente pregunta por productos, precios o disponibilidad, usa buscar_producto o consultar_stock.
- Comunica siempre los precios en Soles (S/) de manera transparente y precisa.

═══════════════════════════════════════
FLUJO DE REGISTRO DE PEDIDO
═══════════════════════════════════════
1. Si el cliente solicita productos:
   - Verifica precios y disponibilidad con las herramientas.
   - Preséntale el resumen de su pedido con viñetas:
     • [Cantidad]x [Producto] - S/ [Subtotal]
     *Total estimado: S/ [Total]*
   - Pregúntale cordialmente si confirma su pedido o si desea agregar algo más.

2. Cuando el cliente CONFIRME ("sí", "confirmo", "dale", "hazlo", "procede", "está bien", etc.):
   - Si aún no está registrado, pídele su nombre completo y dirección de entrega.
   - En cuanto esté registrado (o si ya lo estaba), ejecuta crear_pedido_cliente indicando:
     cliente_id, detalles, confirmado: true, acuerdo_comercial: "Cotización".
   - Confírmale que su pedido fue registrado con su código (ej: PED-00012).
   - Añade siempre el mensaje de cierre:
     "Nuestro equipo revisará la disponibilidad física de los productos y se comunicará contigo por este medio para coordinar la entrega. ¡Muchas gracias por tu preferencia! 😊"

═══════════════════════════════════════
HISTORIAL Y SEGUIMIENTO
═══════════════════════════════════════
- Si el cliente pregunta por sus pedidos anteriores o el estado de una orden, usa obtener_historial_cliente.
- Preséntale el estado de sus pedidos de forma clara y concisa.
PROMPT;

    public function __construct(
        protected GeminiService      $geminiService,
        protected OpenAiService      $openAiService,
        protected GeminiToolsService $toolsService,
        protected ClienteService     $clienteService
    ) {}

    /**
     * Resuelve el servicio de IA activo según configuración del entorno.
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
     * Procesa un mensaje entrante de WhatsApp y genera la respuesta correspondiente.
     */
    public function processMessage(string $phoneNumber, string $message): array
    {
        $rawPhone = trim($phoneNumber);
        $cleanPhone = preg_replace('/\D/', '', $rawPhone);
        if (empty($cleanPhone)) {
            return [
                'success'  => false,
                'response' => 'Número de teléfono inválido.',
            ];
        }

        $userMessage = trim($message);
        if (empty($userMessage)) {
            return [
                'success'  => false,
                'response' => 'El mensaje no puede estar vacío.',
            ];
        }

        // Establecer headers de auditoría para todos los modelos que se creen en esta petición
        $auditUser = config('services.whatsapp.audit_user', 'WHATSAPP_BOT');
        $auditHost = config('services.whatsapp.audit_host', 'WHATSAPP_N8N');
        request()->headers->set('X-User', $auditUser);
        request()->headers->set('X-Host', $auditHost);

        // 1. Cargar o inicializar sesión de WhatsApp (TTL 45 min)
        $sessionKey = "whatsapp_session:{$cleanPhone}";
        $session = Cache::get($sessionKey);
        $isNewSession = ($session === null);

        // Resolver cliente en base de datos si existe por su número telefónico
        $cliente = $this->buscarClientePorTelefono($cleanPhone);

        if ($isNewSession) {
            $session = [
                'phone_number'   => $cleanPhone,
                'cliente_id'     => $cliente?->ClienteId,
                'cliente_nombre' => $cliente?->ClienteNombre,
                'created_at'     => now()->toIso8601String(),
                'last_active'    => now()->toIso8601String(),
                'messages'       => [], // Buffer activo para IA (máximo 8)
            ];
        } else {
            // Si el cliente no estaba asociado pero ahora existe en BD, actualizar sesión
            if (empty($session['cliente_id']) && $cliente) {
                $session['cliente_id'] = $cliente->ClienteId;
                $session['cliente_nombre'] = $cliente->ClienteNombre;
            }
            $session['last_active'] = now()->toIso8601String();
        }

        $sessionId = "wsp_{$cleanPhone}";

        // 2. Construir la instrucción de sistema enriquecida con datos del cliente actual
        $systemInstruction = self::SYSTEM_INSTRUCTION_WHATSAPP;
        if (!empty($session['cliente_id'])) {
            $systemInstruction .= "\n\n═══════════════════════════════════════\nINFORMACIÓN DEL CLIENTE ACTUAL:\n" .
                "- ID Cliente: {$session['cliente_id']}\n" .
                "- Nombre: {$session['cliente_nombre']}\n" .
                "- Teléfono WhatsApp: {$cleanPhone}\n" .
                "REGLA: Este cliente ya está registrado. Trátalo cordialmente por su nombre y NO le vuelvas a pedir registro ni teléfono.\n";
        } else {
            $systemInstruction .= "\n\n═══════════════════════════════════════\nINFORMACIÓN DEL CLIENTE ACTUAL:\n" .
                "- Teléfono WhatsApp: {$cleanPhone}\n" .
                "- Estado: Cliente nuevo (aún no registrado en el sistema).\n" .
                "REGLA: Si confirma un pedido, pídele su nombre completo y dirección de entrega para registrarlo.\n";
        }

        // 3. Construir historial de contexto (máximo 8 mensajes)
        $history = array_slice($session['messages'] ?? [], -self::MAX_CONTEXT_MESSAGES);
        $contents = $this->buildContentsFromHistory($history);
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        // 4. Obtener herramientas autorizadas para WhatsApp (11 herramientas estrictas)
        $tools = $this->getAiService()->getWhatsAppTools();

        // 5. Primera llamada al modelo de IA
        $aiService = $this->getAiService();
        $response = $aiService->generateContent($contents, $systemInstruction, $tools);

        $createdPedidoId = null;
        $toolUsed = null;
        $iteration = 0;
        $maxIterations = 3;

        // 6. Ciclo de Function Calling
        while (!empty($response['has_function_calls']) && !empty($response['function_calls']) && $iteration < $maxIterations) {
            $iteration++;
            $allCalls = $response['function_calls'];
            $executedInThisTurn = [];

            foreach ($allCalls as $fc) {
                $toolName = $fc['name'];
                $toolArgs = $fc['args'] ?? [];
                $toolUsed = $toolName;

                // ── BARRERA DE SEGURIDAD 1 & 2: Validar herramienta permitida ──
                if (!in_array($toolName, GeminiToolsService::WHATSAPP_ALLOWED_TOOLS, true)) {
                    Log::warning("WhatsApp Bot: Intento de ejecución de tool no autorizada: {$toolName}");
                    $toolResult = [
                        'success' => false,
                        'data'    => null,
                        'message' => 'Un asesor se comunicará contigo para ayudarte con eso. 😊',
                    ];
                    $executedInThisTurn[] = ['name' => $toolName, 'result' => $toolResult];
                    continue;
                }

                // ── REGLA DE NEGOCIO: Overrides específicos para WhatsApp ──
                if ($toolName === 'crear_cliente') {
                    $toolArgs['telefono'] = $cleanPhone;
                    $toolArgs['ClienteOrigen'] = 'WhatsApp';
                    if (empty($toolArgs['documento']) && empty($toolArgs['ClienteRuc'])) {
                        $toolArgs['documento'] = '00000000';
                    }
                }

                if ($toolName === 'crear_pedido_cliente') {
                    // Forzar siempre Cotización interna, canal WhatsApp y usuario bot
                    $toolArgs['acuerdo_comercial'] = 'Cotización';
                    $toolArgs['canal_id'] = config('services.whatsapp.canal_id', 'CNL-00003');
                    $toolArgs['usuario_registro'] = $auditUser;
                    $toolArgs['confirmado'] = true;

                    // Si ya tenemos cliente_id en sesión, forzarlo
                    if (!empty($session['cliente_id'])) {
                        $toolArgs['cliente_id'] = $session['cliente_id'];
                    }
                }

                if ($toolName === 'obtener_historial_cliente') {
                    // Restringir estrictamente a los pedidos del propio teléfono
                    if (empty($session['cliente_id'])) {
                        $toolResult = [
                            'success' => true,
                            'data'    => [],
                            'message' => 'Aún no tienes pedidos registrados con nosotros. ¿Te gustaría realizar uno hoy? 😊',
                        ];
                        $executedInThisTurn[] = ['name' => $toolName, 'result' => $toolResult];
                        continue;
                    }
                    $toolArgs['cliente_id'] = $session['cliente_id'];
                }

                // Ejecutar herramienta en GeminiToolsService con contexto 'whatsapp'
                $toolResult = $this->toolsService->dispatch(
                    $toolName,
                    $toolArgs,
                    false,
                    null,
                    $userMessage,
                    $sessionId,
                    'whatsapp'
                );

                // Si se creó cliente exitosamente, actualizar datos en la sesión activa
                if ($toolName === 'crear_cliente' && ($toolResult['success'] ?? false)) {
                    $session['cliente_id'] = $toolResult['data']['cliente_id'] ?? $session['cliente_id'];
                    $session['cliente_nombre'] = $toolResult['data']['nombre'] ?? $session['cliente_nombre'];
                }

                // Si se creó pedido (cotización) exitosamente, capturar ID
                if ($toolName === 'crear_pedido_cliente' && ($toolResult['success'] ?? false)) {
                    $createdPedidoId = $toolResult['data']['pedido_id'] ?? null;
                }

                $executedInThisTurn[] = [
                    'name'   => $toolName,
                    'result' => $toolResult,
                ];
            }

            if (empty($executedInThisTurn)) {
                break;
            }

            // Enviar resultados de vuelta a la IA
            $contents = $aiService->appendMultipleFunctionTurns(
                $contents,
                $response['raw_candidate'] ?? [],
                $executedInThisTurn
            );

            $response = $aiService->generateContent($contents, $systemInstruction, $tools);
        }

        // 7. Extraer y limpiar respuesta final para WhatsApp
        $rawReply = trim($response['text'] ?? '');
        if (empty($rawReply)) {
            $rawReply = '¡Hola! He recibido tu mensaje. ¿En qué te puedo ayudar hoy? 😊';
        }

        $cleanResponse = $this->cleanWhatsAppMarkdown($rawReply);

        // 8. Actualizar buffer de contexto activo (últimos 8)
        $session['messages'][] = ['role' => 'user', 'text' => $userMessage];
        $session['messages'][] = ['role' => 'bot', 'text' => $cleanResponse];
        if (count($session['messages']) > self::MAX_CONTEXT_MESSAGES) {
            $session['messages'] = array_slice($session['messages'], -self::MAX_CONTEXT_MESSAGES);
        }

        // Guardar sesión en caché con TTL de 45 minutos
        Cache::put($sessionKey, $session, now()->addSeconds(self::SESSION_TTL));

        // 9. Actualizar historial de auditoría persistente (hasta 20 mensajes para tesis)
        $this->appendAuditTrail($cleanPhone, $userMessage, $cleanResponse, [
            'cliente_id'    => $session['cliente_id'] ?? null,
            'pedido_id'     => $createdPedidoId,
            'tool_used'     => $toolUsed,
            'is_new_session'=> $isNewSession,
        ]);

        return [
            'success'       => true,
            'response'      => $cleanResponse,
            'phone_number'  => $cleanPhone,
            'cliente_id'    => $session['cliente_id'] ?? null,
            'cotizacion_id' => $createdPedidoId,
            'session_fresh' => $isNewSession,
            'tool_used'     => $toolUsed,
        ];
    }

    /**
     * Limpia y adapta el formato Markdown de la IA para que luzca perfecto en WhatsApp.
     */
    public function cleanWhatsAppMarkdown(string $text): string
    {
        // 1. Eliminar bloques de código ``` y backticks
        $text = preg_replace('/```[a-z]*\s*/i', '', $text);
        $text = str_replace('```', '', $text);
        $text = preg_replace('/`([^`]+)`/', '*$1*', $text);

        // 2. Convertir encabezados Markdown (#, ##, ###) a negritas simples de WhatsApp
        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '*$1*', $text);

        // 3. Convertir dobles asteriscos de Markdown (**texto**) a asterisco simple de WhatsApp (*texto*)
        $text = preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);

        // 4. Regla estricta de vocabulario: reemplazar 'cotización' por 'pedido' en textos hacia el cliente
        $text = preg_replace('/\bcotizaci[oó]n\b/ui', 'pedido', $text);
        $text = preg_replace('/\bcotizaciones\b/ui', 'pedidos', $text);

        // 5. Eliminar separadores horizontales Markdown (--- o ===)
        $text = preg_replace('/^[=\-]{3,}\s*$/m', '', $text);

        // 6. Normalizar saltos de línea repetidos (máximo 2 saltos consecutivos)
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Busca un cliente en la base de datos a partir de los dígitos de su número de teléfono.
     */
    protected function buscarClientePorTelefono(string $cleanPhone): ?Cliente
    {
        $last9 = substr($cleanPhone, -9);

        return Cliente::where('ClienteEliminado', 'N')
            ->where(function ($query) use ($last9, $cleanPhone) {
                if (!empty($last9)) {
                    $query->where('ClienteNumero', 'like', "%{$last9}%");
                }
                $query->orWhere('ClienteNumero', $cleanPhone);
            })
            ->first();
    }

    /**
     * Convierte el historial de mensajes al formato de Contents para Gemini / OpenAI.
     */
    protected function buildContentsFromHistory(array $history): array
    {
        $contents = [];
        foreach ($history as $msg) {
            $role = ($msg['role'] ?? 'user') === 'bot' || ($msg['role'] ?? 'user') === 'model' ? 'model' : 'user';
            $text = trim((string)($msg['text'] ?? $msg['content'] ?? ''));
            if (!empty($text)) {
                $contents[] = [
                    'role'  => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }
        return $contents;
    }

    /**
     * Guarda registro en el buffer de auditoría (máximo 20 mensajes persistidos para la tesis).
     */
    protected function appendAuditTrail(string $phone, string $userMessage, string $botResponse, array $metadata = []): void
    {
        $auditKey = "whatsapp_audit:{$phone}";
        $audit = Cache::get($auditKey, []);

        $audit[] = [
            'timestamp'    => now()->toIso8601String(),
            'user_message' => $userMessage,
            'bot_response' => $botResponse,
            'metadata'     => $metadata,
        ];

        if (count($audit) > self::MAX_AUDIT_MESSAGES) {
            $audit = array_slice($audit, -self::MAX_AUDIT_MESSAGES);
        }

        // Guardar por 30 días para trazabilidad y defensa de tesis
        Cache::put($auditKey, $audit, now()->addDays(30));
    }

    /**
     * Obtiene el historial de auditoría de un cliente para análisis de tesis.
     */
    public function getAuditTrail(string $phoneNumber): array
    {
        $cleanPhone = preg_replace('/\D/', '', $phoneNumber);
        return Cache::get("whatsapp_audit:{$cleanPhone}", []);
    }
}
