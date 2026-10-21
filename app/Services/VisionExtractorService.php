<?php

namespace App\Services;

use App\Models\AiVisionLog;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VisionExtractorService
{
    public function __construct(
        protected GeminiService $geminiService,
        protected OpenAiService $openAiService,
        protected CatalogMatcherService $matcherService
    ) {}

    /**
     * Extrae pedidos desde una foto (lista manuscrita o digital) y los empareja con la BD.
     */
    public function extraerPedidoDesdeFoto(string $base64, string $mime, ?string $usuarioId = null): array
    {
        $inicio = microtime(true);

        $prompt = <<<PROMPT
Eres un experto en transcripción logística de pedidos para Comercial Valencia.
Analiza minuciosamente esta imagen que contiene una lista de compras o pedido (escrita a mano, impresa o en pantalla).
Identifica cada producto solicitado.
Para cada producto extrae:
- "nombre_buscado": Nombre del producto incluyendo marca, tipo y tamaño si es visible (ej: "Inca Kola 3L", "Aceite Primor 1L", "Arroz Costeño 5kg").
- "cantidad": Cantidad solicitada en número (ej: 2, 5, 12). Si no se indica, asume 1.
- "presentacion": Indicación de presentación si está escrita explícitamente (ej: "caja", "paquete", "sixpack", "lata", "bolsa"). Si no se menciona o es por unidad, devuelve null.

Devuelve estrictamente un JSON con esta estructura:
{
  "productos": [
    {
      "nombre_buscado": "nombre del producto",
      "cantidad": 1,
      "presentacion": null
    }
  ]
}
PROMPT;

        $jsonSchema = [
            'type' => 'OBJECT',
            'properties' => [
                'productos' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'nombre_buscado' => ['type' => 'STRING'],
                            'cantidad'       => ['type' => 'NUMBER'],
                            'presentacion'   => ['type' => 'STRING', 'nullable' => true],
                        ],
                        'required' => ['nombre_buscado', 'cantidad'],
                    ],
                ],
            ],
            'required' => ['productos'],
        ];

        $aiRes = null;
        $geminiError = null;

        // 1. Intentar con Gemini Vision
        try {
            $aiRes = $this->geminiService->analyzeImage(
                base64Image: $base64,
                mimeType: $mime,
                prompt: $prompt,
                jsonSchema: $jsonSchema,
                systemInstruction: 'Eres un transcriptor logístico de alta precisión. Devuelve exclusivamente JSON.'
            );
        } catch (Exception $e) {
            $geminiError = $e->getMessage();
            Log::warning("VisionExtractorService: Gemini falló ('{$geminiError}'). Intentando fallback a OpenAI Vision...");
        }

        // 2. Si Gemini falló o no devolvió datos válidos, recurrir a OpenAI Vision (gpt-4o-mini)
        if (empty($aiRes) || empty($aiRes['success'])) {
            try {
                $aiRes = $this->openAiService->analyzeImage(
                    base64Image: $base64,
                    mimeType: $mime,
                    prompt: $prompt,
                    jsonSchema: $jsonSchema,
                    systemInstruction: 'Eres un transcriptor logístico de alta precisión para Comercial Valencia. Devuelve exclusivamente JSON válido.'
                );
                Log::info("VisionExtractorService: Procesado exitosamente con fallback de OpenAI Vision.");
            } catch (Exception $eOpenAi) {
                Log::error("VisionExtractorService: Ambos servicios de Visión fallaron.");
                throw new Exception("Error al procesar visión con IA. Gemini: {$geminiError} | OpenAI: " . $eOpenAi->getMessage());
            }
        }

        $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);

        $datos = $aiRes['data'] ?? [];
        $rawProductos = $datos['productos'] ?? [];

        // Emparejar cada ítem con el catálogo de productos de la base de datos
        $itemsMatched = $this->matcherService->matchMultipleItems($rawProductos);

        // Generar token de auditoría para trazabilidad
        $tokenAuditoria = (string) Str::uuid();

        // Guardar snapshot de IA en caché durante 1 hora
        Cache::put("vision_snapshot:{$tokenAuditoria}", [
            'usuario_id'         => $usuarioId ?? 'USR-ANON',
            'tokens_consumidos'  => $aiRes['tokens'] ?? 258,
            'tiempo_ms'          => $tiempoMs,
            'items_detectados'   => $itemsMatched,
            'timestamp'          => now()->toIso8601String(),
        ], 3600);

        return [
            'success'           => true,
            'token_auditoria'   => $tokenAuditoria,
            'items'             => $itemsMatched,
            'total_detectados'  => count($itemsMatched),
            'tokens_consumidos' => $aiRes['tokens'] ?? 258,
            'tiempo_ms'         => $tiempoMs,
            'model'             => $aiRes['model'] ?? 'vision-ai',
        ];
    }

    /**
     * Auditar el pedido guardado comparando contra el snapshot original de la IA
     */
    public function auditarPedidoGuardado(
        string $tokenAuditoria,
        string $pedidoId,
        array $itemsGuardadosFinales,
        ?string $usuarioId = null,
        ?int $tiempoRevisionSeg = null
    ): ?AiVisionLog {
        try {
            $snapshot = Cache::get("vision_snapshot:{$tokenAuditoria}");
            if (!$snapshot) {
                Log::info("Auditoría Visión: No se encontró snapshot en caché para token {$tokenAuditoria}.");
                return null;
            }

            $itemsIA = $snapshot['items_detectados'] ?? [];
            $totalIA = count($itemsIA);

            $validadosSinCambio = 0;
            $modificados = 0;
            $eliminados = 0;

            // Comparar cada ítem detectado por la IA contra los guardados
            foreach ($itemsIA as $iaItem) {
                $prodIdIA = $iaItem['producto_id'] ?? null;
                $cantIA = (float) ($iaItem['cantidad_detectada'] ?? 1);
                $unidadIA = $iaItem['presentacion_id'] ?? null;

                // Buscar en los guardados
                $encontrado = null;
                foreach ($itemsGuardadosFinales as $guardado) {
                    $gProdId = $guardado['producto_id'] ?? $guardado['Detalle_Pedido_Productos_ProductoId'] ?? null;
                    if ($gProdId && $gProdId === $prodIdIA) {
                        $encontrado = $guardado;
                        break;
                    }
                }

                if (!$encontrado) {
                    $eliminados++;
                } else {
                    $gCant = (float) ($encontrado['cantidad'] ?? $encontrado['Detalle_Pedido_Productos_cantidad'] ?? 1);
                    $gUnidad = $encontrado['unidad_medida_id'] ?? $encontrado['Detalle_Pedido_Productos_unidades_medidaId'] ?? null;

                    if ($gCant === $cantIA && ($unidadIA === null || $gUnidad === $unidadIA)) {
                        $validadosSinCambio++;
                    } else {
                        $modificados++;
                    }
                }
            }

            $totalGuardados = count($itemsGuardadosFinales);
            $agregados = max(0, $totalGuardados - ($validadosSinCambio + $modificados));

            // Tasa de precisión: (ítems sin cambios / total ítems IA) * 100
            $tasaPrecision = ($totalIA > 0)
                ? round(($validadosSinCambio / $totalIA) * 100, 2)
                : 100.0;

            $log = AiVisionLog::create([
                'token_auditoria'            => $tokenAuditoria,
                'pedido_id'                  => $pedidoId,
                'usuario_id'                 => $usuarioId ?? $snapshot['usuario_id'] ?? 'USR-ANON',
                'tokens_consumidos'          => $snapshot['tokens_consumidos'] ?? 258,
                'tiempo_procesamiento_ms'    => $snapshot['tiempo_ms'] ?? 0,
                'items_detectados_ia'        => $itemsIA,
                'items_guardados_final'      => $itemsGuardadosFinales,
                'total_items_ia'             => $totalIA,
                'items_validados_sin_cambio' => $validadosSinCambio,
                'items_modificados'          => $modificados,
                'items_eliminados'           => $eliminados,
                'items_agregados'            => $agregados,
                'tasa_precision_pct'         => $tasaPrecision,
                'tiempo_revision_humana_seg' => $tiempoRevisionSeg,
            ]);

            // Limpiar caché
            Cache::forget("vision_snapshot:{$tokenAuditoria}");

            Log::info("Auditoría Visión Registrada", [
                'token'     => $tokenAuditoria,
                'pedido_id' => $pedidoId,
                'precision' => "{$tasaPrecision}%",
            ]);

            return $log;
        } catch (Exception $e) {
            Log::error("Error al auditar visión: " . $e->getMessage());
            return null;
        }
    }
}
