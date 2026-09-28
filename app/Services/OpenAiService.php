<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAiService
 *
 * Motor de Inteligencia Artificial basado en OpenAI GPT-5.5 y Whisper-1.
 * Implementa Function Calling de 63 herramientas, normalización estricta de esquemas JSON,
 * transcripción de notas de voz y arquitectura multi-proveedor con failover a GeminiService.
 */
class OpenAiService
{
    protected string $apiKey;
    protected string $model;
    protected string $transcribeModel;
    protected string $baseUrl;
    protected array $lastFilterMetadata = [];
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService   = $geminiService;
        $this->apiKey          = (string) Config::get('services.openai.api_key', env('OPENAI_API_KEY', ''));
        $this->model           = (string) Config::get('services.openai.model', env('OPENAI_MODEL', 'gpt-5.5'));
        $this->transcribeModel = (string) Config::get('services.openai.transcribe_model', env('OPENAI_TRANSCRIBE_MODEL', 'whisper-1'));
        $this->baseUrl         = rtrim((string) Config::get('services.openai.base_url', env('OPENAI_BASE_URL', 'https://api.openai.com/v1')), '/');
    }

    /**
     * Retorna la metadata de la última poda de herramientas realizada.
     */
    public function getLastFilterMetadata(): array
    {
        return !empty($this->lastFilterMetadata)
            ? $this->lastFilterMetadata
            : $this->geminiService->getLastFilterMetadata();
    }

    /**
     * Clasifica la complejidad de la consulta para aplicar timeout adaptativo.
     */
    public function detectarComplejidad(string $query, string $intent = 'general'): array
    {
        return $this->geminiService->detectarComplejidad($query, $intent);
    }

    /**
     * Catálogo de declaraciones de Tools adaptadas para OpenAI con filtrado inteligente.
     */
    public function getRegisteredTools(bool $readOnly = false, ?string $userQuery = null): array
    {
        $geminiTools = $this->geminiService->getRegisteredTools($readOnly, $userQuery);
        $this->lastFilterMetadata = $this->geminiService->getLastFilterMetadata();
        return $this->normalizeToolsForOpenAi($geminiTools);
    }

    /**
     * Normaliza un array de tools (formato Gemini o genérico) a la especificación estricta de OpenAI Function Calling.
     */
    public function normalizeToolsForOpenAi(array $tools): array
    {
        if (empty($tools)) {
            return [];
        }

        // Si ya está en formato OpenAI: [['type' => 'function', ...]]
        if (isset($tools[0]['type']) && $tools[0]['type'] === 'function') {
            return $tools;
        }

        // Si viene en formato Gemini: [['functionDeclarations' => [...]]]
        $declarations = [];
        if (isset($tools[0]['functionDeclarations']) && is_array($tools[0]['functionDeclarations'])) {
            $declarations = $tools[0]['functionDeclarations'];
        } else {
            $declarations = $tools;
        }

        $openAiTools = [];
        foreach ($declarations as $d) {
            if (!is_array($d) || empty($d['name'])) {
                continue;
            }
            $openAiTools[] = [
                'type' => 'function',
                'function' => [
                    'name'        => $d['name'],
                    'description' => $d['description'] ?? '',
                    'parameters'  => $this->normalizeSchemaForOpenAi($d['parameters'] ?? []),
                ],
            ];
        }

        return $openAiTools;
    }

    /**
     * Normalizador recursivo de esquemas JSON para cumplir con la especificación estricta de OpenAI.
     */
    public function normalizeSchemaForOpenAi(mixed $schema): array
    {
        if (!is_array($schema) && !is_object($schema)) {
            return ['type' => 'object', 'properties' => (object) []];
        }
        $schema = (array) $schema;

        $out = [];
        $type = strtolower($schema['type'] ?? 'object');
        $out['type'] = $type;

        if (isset($schema['description'])) {
            $out['description'] = (string) $schema['description'];
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $out['enum'] = array_values($schema['enum']);
        }

        if ($type === 'object') {
            $props = $schema['properties'] ?? (object) [];
            if (empty($props)) {
                $out['properties'] = (object) [];
            } else {
                $normalizedProps = [];
                foreach ((array) $props as $propName => $propSchema) {
                    $normalizedProps[$propName] = $this->normalizeSchemaForOpenAi($propSchema);
                }
                $out['properties'] = (object) $normalizedProps;
            }

            if (isset($schema['required']) && is_array($schema['required']) && !empty($schema['required'])) {
                $out['required'] = array_values(array_map('strval', $schema['required']));
            }
        } elseif ($type === 'array') {
            $items = $schema['items'] ?? ['type' => 'string'];
            $out['items'] = $this->normalizeSchemaForOpenAi($items);
        }

        return $out;
    }

    /**
     * Transforma el historial de conversación (sea formato simple, Gemini o OpenAI) al estándar de messages de OpenAI.
     */
    public function buildOpenAiMessages(string|array $contents, ?string $systemInstruction = null): array
    {
        $messages = [];

        // 1. System Instruction
        if (!empty($systemInstruction)) {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemInstruction,
            ];
        }

        // 2. Si contents es string simple
        if (is_string($contents)) {
            $messages[] = [
                'role'    => 'user',
                'content' => $contents,
            ];
            return $messages;
        }

        // 3. Procesar turnos
        foreach ($contents as $turn) {
            if (!is_array($turn)) {
                continue;
            }

            // Ya es mensaje nativo de OpenAI
            if (isset($turn['role']) && in_array($turn['role'], ['system', 'tool'])) {
                $messages[] = $turn;
                continue;
            }
            if (isset($turn['role']) && $turn['role'] === 'assistant' && (isset($turn['tool_calls']) || isset($turn['content']))) {
                $messages[] = $turn;
                continue;
            }

            $rawRole = $turn['role'] ?? 'user';
            $openAiRole = ($rawRole === 'model' || $rawRole === 'assistant') ? 'assistant' : 'user';

            // Viene con 'content' directo
            if (isset($turn['content']) && !isset($turn['parts'])) {
                $messages[] = [
                    'role'    => $openAiRole,
                    'content' => is_string($turn['content']) ? $turn['content'] : json_encode($turn['content'], JSON_UNESCAPED_UNICODE),
                ];
                continue;
            }

            // Viene con 'parts' estilo Gemini
            $parts = $turn['parts'] ?? [];
            $textParts = [];
            $functionCalls = [];
            $functionResponses = [];

            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
                if (isset($part['functionCall'])) {
                    $functionCalls[] = $part['functionCall'];
                }
                if (isset($part['functionResponse'])) {
                    $functionResponses[] = $part['functionResponse'];
                }
            }

            if (!empty($functionCalls)) {
                $toolCalls = [];
                foreach ($functionCalls as $idx => $fc) {
                    $callId = $fc['id'] ?? ('call_' . substr(md5($fc['name'] . $idx . microtime()), 0, 20));
                    $toolCalls[] = [
                        'id'       => $callId,
                        'type'     => 'function',
                        'function' => [
                            'name'      => $fc['name'],
                            'arguments' => json_encode($fc['args'] ?? (object)[], JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => !empty($textParts) ? implode("\n", $textParts) : null,
                    'tool_calls' => $toolCalls,
                ];
                continue;
            }

            if (!empty($functionResponses)) {
                foreach ($functionResponses as $fr) {
                    $fnName = $fr['name'] ?? 'tool';
                    $fnResponse = $fr['response'] ?? [];
                    $callId = $fr['id'] ?? $this->findRecentToolCallId($messages, $fnName) ?? ('call_' . substr(uniqid(), 0, 20));

                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $callId,
                        'name'         => $fnName,
                        'content'      => is_string($fnResponse) ? $fnResponse : json_encode($fnResponse, JSON_UNESCAPED_UNICODE),
                    ];
                }
                continue;
            }

            $messages[] = [
                'role'    => $openAiRole,
                'content' => implode("\n", $textParts),
            ];
        }

        return $messages;
    }

    protected function findRecentToolCallId(array $messages, string $fnName): ?string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if (($msg['role'] ?? '') === 'assistant' && !empty($msg['tool_calls'])) {
                foreach ($msg['tool_calls'] as $tc) {
                    if (($tc['function']['name'] ?? '') === $fnName) {
                        return $tc['id'];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Generar contenido usando OpenAI Chat Completions API con Function Calling y Fallback a Gemini.
     */
    public function generateContent(
        string|array $contents,
        ?string $systemInstruction = null,
        array $tools = [],
        array $generationConfig = [],
        ?int $customTimeout = null
    ): array {
        if (empty($this->apiKey)) {
            throw new Exception('No se ha configurado la clave de API de OpenAI (OPENAI_API_KEY).');
        }

        $messages = $this->buildOpenAiMessages($contents, $systemInstruction);
        $normalizedTools = !empty($tools) ? $this->normalizeToolsForOpenAi($tools) : [];

        $payload = [
            'model'    => $this->model,
            'messages' => $messages,
        ];

        if (!empty($normalizedTools)) {
            $payload['tools'] = $normalizedTools;
        }

        // gpt-5.5 y modelos de razonamiento (o1, o3) solo soportan temperature=1 por defecto; rechazan valores como 0.1 o 0.2
        if (isset($generationConfig['temperature']) && !str_starts_with($this->model, 'gpt-5') && !str_starts_with($this->model, 'o1') && !str_starts_with($this->model, 'o3')) {
            $payload['temperature'] = (float) $generationConfig['temperature'];
        }
        if (isset($generationConfig['maxOutputTokens'])) {
            $payload['max_completion_tokens'] = (int) $generationConfig['maxOutputTokens'];
        } elseif (isset($generationConfig['max_completion_tokens'])) {
            $payload['max_completion_tokens'] = (int) $generationConfig['max_completion_tokens'];
        } elseif (isset($generationConfig['max_tokens'])) {
            $payload['max_completion_tokens'] = (int) $generationConfig['max_tokens'];
        }

        $url = "{$this->baseUrl}/chat/completions";
        $timeout = $customTimeout ?? 30;

        try {
            $response = Http::withOptions([
                'force_ip_resolve' => 'v4',
                'timeout'          => $timeout,
                'connect_timeout'  => 5,
            ])->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])->post($url, $payload);

            $status = $response->status();

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error']['message'] ?? $response->body();

                if (in_array($status, [429, 500, 502, 503], true)) {
                    Log::warning("Valencia AI: OpenAI HTTP {$status} ({$errorMessage}). Ejecutando failover transparente a GeminiService...");
                    return $this->geminiService->generateContent($contents, $systemInstruction, $tools, $generationConfig, $customTimeout);
                }

                Log::error('Error en OpenAI API:', ['status' => $status, 'response' => $errorData]);
                throw new Exception("Error en OpenAI API ({$status}): {$errorMessage}");
            }

            $responseData = $response->json();
            return $this->parseOpenAiResponse($responseData);

        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (
                str_contains($msg, '429') ||
                str_contains($msg, '503') ||
                str_contains($msg, 'timed out') ||
                str_contains($msg, 'Operation timed out') ||
                str_contains($msg, 'cURL error 28')
            ) {
                Log::warning("Valencia AI: Timeout o error de red con OpenAI ({$msg}). Failover a GeminiService...");
                return $this->geminiService->generateContent($contents, $systemInstruction, $tools, $generationConfig, $customTimeout);
            }

            Log::error('Excepción al conectar con OpenAI:', ['mensaje' => $msg]);
            throw $e;
        }
    }

    /**
     * Parsea la respuesta de OpenAI para que coincida exactamente con la estructura que AiController espera.
     */
    public function parseOpenAiResponse(array $raw): array
    {
        $choice = $raw['choices'][0] ?? null;
        $message = $choice['message'] ?? null;
        $text = $message['content'] ?? '';
        $toolCalls = $message['tool_calls'] ?? [];

        $functionCalls = [];
        if (!empty($toolCalls) && is_array($toolCalls)) {
            foreach ($toolCalls as $tc) {
                if (($tc['type'] ?? '') === 'function' && !empty($tc['function'])) {
                    $rawArgs = $tc['function']['arguments'] ?? '{}';
                    $decodedArgs = is_string($rawArgs) ? json_decode($rawArgs, true) : (array) $rawArgs;
                    $functionCalls[] = [
                        'id'   => $tc['id'] ?? null,
                        'name' => $tc['function']['name'] ?? '',
                        'args' => is_array($decodedArgs) ? $decodedArgs : [],
                    ];
                }
            }
        }

        $usage = $raw['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;
        $totalTokens = $usage['total_tokens'] ?? ($promptTokens + $completionTokens);

        return [
            'text'               => (string) $text,
            'has_function_calls' => !empty($functionCalls),
            'function_calls'     => $functionCalls,
            'finish_reason'      => $choice['finish_reason'] ?? 'stop',
            'model_version'      => $raw['model'] ?? $this->model,
            'usage'              => [
                'prompt_tokens'        => $promptTokens,
                'completion_tokens'    => $completionTokens,
                'total_tokens'         => $totalTokens,
                'promptTokenCount'     => $promptTokens,
                'candidatesTokenCount' => $completionTokens,
                'totalTokenCount'      => $totalTokens,
            ],
            'raw_candidate'      => $message ?? [],
        ];
    }

    /**
     * Agrega turnos de Assistant (con tool_calls) y Tool (con respuestas) al historial.
     */
    public function appendMultipleFunctionTurns(
        array $contents,
        array $rawCandidate,
        array $executedTools
    ): array {
        $assistantMsg = [
            'role'       => 'assistant',
            'content'    => $rawCandidate['content'] ?? null,
            'tool_calls' => $rawCandidate['tool_calls'] ?? [],
        ];

        if (empty($assistantMsg['tool_calls']) && isset($rawCandidate['content']['parts'])) {
            $toolCalls = [];
            foreach ($rawCandidate['content']['parts'] as $idx => $part) {
                if (isset($part['functionCall'])) {
                    $toolCalls[] = [
                        'id'       => $part['functionCall']['id'] ?? ('call_' . substr(md5($part['functionCall']['name'] . $idx), 0, 20)),
                        'type'     => 'function',
                        'function' => [
                            'name'      => $part['functionCall']['name'],
                            'arguments' => json_encode($part['functionCall']['args'] ?? (object)[], JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
            }
            $assistantMsg['tool_calls'] = $toolCalls;
        }

        $contents[] = $assistantMsg;

        foreach ($executedTools as $idx => $item) {
            $fnName = $item['name'];
            $fnResult = $item['result'];

            $callId = $assistantMsg['tool_calls'][$idx]['id'] ?? null;
            if (!$callId) {
                foreach ($assistantMsg['tool_calls'] as $tc) {
                    if (($tc['function']['name'] ?? '') === $fnName) {
                        $callId = $tc['id'];
                        break;
                    }
                }
            }
            if (!$callId) {
                $callId = 'call_' . substr(uniqid(), 0, 20);
            }

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

            $contents[] = [
                'role'         => 'tool',
                'tool_call_id' => $callId,
                'name'         => $fnName,
                'content'      => json_encode($responsePayload, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $contents;
    }

    public function appendFunctionTurn(
        array $contents,
        array $rawCandidate,
        string $functionName,
        array $functionResult
    ): array {
        return $this->appendMultipleFunctionTurns($contents, $rawCandidate, [
            ['name' => $functionName, 'result' => $functionResult]
        ]);
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
     * Transcribe un archivo de audio utilizando OpenAI Whisper-1 y calcula el costo para la tesis ($0.006/min).
     */
    public function transcribeAudio(string $audioData, string $mimeType = 'audio/webm'): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('No se ha configurado la clave de API de OpenAI (OPENAI_API_KEY).');
        }

        $startTime = microtime(true);

        $tempFile = null;
        if (strlen($audioData) < 1000 && @file_exists($audioData)) {
            $filePath = $audioData;
            $audioSizeBytes = filesize($filePath);
        } else {
            $cleanMime = explode(';', $mimeType)[0];
            $ext = match (trim(strtolower($cleanMime))) {
                'audio/mp4', 'audio/x-m4a', 'audio/m4a' => 'm4a',
                'audio/wav', 'audio/x-wav' => 'wav',
                'audio/mpeg', 'audio/mp3' => 'mp3',
                'audio/ogg' => 'ogg',
                default => 'webm',
            };
            $tempFile = tempnam(sys_get_temp_dir(), 'openai_audio_') . '.' . $ext;
            file_put_contents($tempFile, $audioData);
            $filePath = $tempFile;
            $audioSizeBytes = strlen($audioData);
        }

        $sizeKb = round($audioSizeBytes / 1024, 2);
        // Estimación de duración de audio para cálculo de costo (aprox 4 KB/s para audio de voz)
        $estimatedSeconds = max(1, round($audioSizeBytes / 4000));

        $prompt = "Distribuidora y comercializadora en Perú. Marcas: Pepsi, San Carlos, San Mateo, Gloria, Bonle, Inca Kola, Guaraná, Chef, Don Vittorio. Unidades: paquetes, packs, six-pack, cajas, fardos, botellas, litros, unidades.";

        try {
            $response = Http::withOptions([
                'force_ip_resolve' => 'v4',
                'timeout'          => 35,
                'connect_timeout'  => 5,
            ])->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->attach(
                'file',
                file_get_contents($filePath),
                basename($filePath)
            )->post("{$this->baseUrl}/audio/transcriptions", [
                'model'       => $this->transcribeModel,
                'language'    => 'es',
                'temperature' => 0.0,
                'prompt'      => $prompt,
            ]);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            if (!$response->successful()) {
                $status = $response->status();
                $err = $response->json()['error']['message'] ?? $response->body();

                if (in_array($status, [429, 500, 503], true)) {
                    Log::channel('voice_transcription')->warning("OpenAI Whisper HTTP {$status} ({$err}). Fallback a Gemini Voice...");
                    return $this->geminiService->transcribeAudio($audioData, $mimeType);
                }

                throw new Exception("Error en OpenAI Whisper ({$status}): {$err}");
            }

            $resultData = $response->json();
            $transcribedText = trim($resultData['text'] ?? '');

            // Cálculo de costo para tesis ($0.006 por minuto de audio)
            $whisperPerMinute = config('services.openai.pricing.whisper_per_minute', 0.006);
            $costUsd = round(($estimatedSeconds / 60) * $whisperPerMinute, 6);

            Log::channel('voice_transcription')->info("OpenAI Whisper: Transcripción exitosa", [
                'model'         => $this->transcribeModel,
                'duration_ms'   => $durationMs,
                'size_kb'       => $sizeKb,
                'est_seconds'   => $estimatedSeconds,
                'cost_usd'      => $costUsd,
                'text_len'      => mb_strlen($transcribedText),
            ]);

            return [
                'success'       => true,
                'text'          => $transcribedText,
                'model'         => $this->transcribeModel,
                'duration_ms'   => $durationMs,
                'size_kb'       => $sizeKb,
                'audio_seconds' => $estimatedSeconds,
                'cost_usd'      => $costUsd,
            ];

        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '429') || str_contains($msg, 'timed out') || str_contains($msg, 'cURL error 28')) {
                Log::channel('voice_transcription')->warning("OpenAI Whisper error ({$msg}). Fallback a Gemini Voice...");
                return $this->geminiService->transcribeAudio($audioData, $mimeType);
            }
            throw $e;
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Verifica la salud y conectividad con la API de OpenAI.
     */
    public function checkConnection(): array
    {
        $startTime = microtime(true);
        try {
            $result = $this->generateContent(
                'Responde estrictamente con la palabra OK.',
                'Eres un comprobador de estado del sistema.',
                [],
                ['max_completion_tokens' => 10, 'temperature' => 0.1],
                10
            );

            $latency = round((microtime(true) - $startTime) * 1000);

            return [
                'online'     => true,
                'model'      => $this->model,
                'response'   => trim($result['text'] ?? 'OK'),
                'latency_ms' => $latency,
            ];
        } catch (Exception $e) {
            return [
                'online' => false,
                'model'  => $this->model,
                'error'  => $e->getMessage(),
            ];
        }
    }
}