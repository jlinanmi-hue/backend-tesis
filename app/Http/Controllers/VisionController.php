<?php

namespace App\Http\Controllers;

use App\Services\VisionExtractorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VisionController extends Controller
{
    public function __construct(
        protected VisionExtractorService $visionService
    ) {}

    /**
     * Procesar foto de lista de compras / pedido.
     * POST /api/vision/procesar-pedido
     */
    public function procesarPedido(Request $request): JsonResponse
    {
        try {
            $base64 = null;
            $mime = 'image/jpeg';

            // Opción 1: Archivo Multipart
            if ($request->hasFile('imagen')) {
                $file = $request->file('imagen');
                $base64 = base64_encode(file_get_contents($file->getRealPath()));
                $mime = $file->getMimeType() ?: 'image/jpeg';
            }
            // Opción 2: Base64 directo en JSON (soporta imagen_base64, base64 o image)
            elseif ($request->filled('imagen_base64') || $request->filled('base64') || $request->filled('image')) {
                $base64 = $request->input('imagen_base64') ?? $request->input('base64') ?? $request->input('image');
                $mime = $request->input('mime_type');

                // Si viene como Data URL (data:image/webp;base64,...), limpiar y deducir MIME
                if (is_string($base64) && str_contains($base64, ';base64,')) {
                    $partes = explode(';base64,', $base64);
                    if (empty($mime) && preg_match('#^data:(image/[a-zA-Z0-9.+-]+)#', $partes[0], $coincidencias)) {
                        $mime = $coincidencias[1];
                    }
                    $base64 = $partes[1];
                }

                if (empty($mime)) {
                    $mime = 'image/jpeg';
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes proporcionar una imagen válida (archivo o imagen_base64).',
                ], 422);
            }

            $usuarioId = $request->header('X-User-Id') ?? $request->input('usuario_id', 'USR-00001');

            $resultado = $this->visionService->extraerPedidoDesdeFoto(
                base64: $base64,
                mime: $mime,
                usuarioId: $usuarioId
            );

            return response()->json([
                'success' => true,
                'data'    => $resultado,
                'message' => "Se detectaron {$resultado['total_detectados']} producto(s) en la imagen.",
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('VisionController::procesarPedido error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No se pudo interpretar la imagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint opcional para registrar auditoría de discrepancias manualmente si no se hizo al guardar el pedido.
     * POST /api/vision/auditar-resultado
     */
    public function auditarResultado(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token_auditoria'            => 'required|string',
            'pedido_id'                  => 'required|string',
            'items_guardados'            => 'required|array',
            'usuario_id'                 => 'nullable|string',
            'tiempo_revision_humana_seg' => 'nullable|integer',
        ]);

        $log = $this->visionService->auditarPedidoGuardado(
            tokenAuditoria: $validated['token_auditoria'],
            pedidoId: $validated['pedido_id'],
            itemsGuardadosFinales: $validated['items_guardados'],
            usuarioId: $validated['usuario_id'] ?? $request->header('X-User-Id'),
            tiempoRevisionSeg: $validated['tiempo_revision_humana_seg'] ?? null
        );

        return response()->json([
            'success' => $log !== null,
            'data'    => $log,
            'message' => $log ? "Auditoría registrada: precisión del {$log->tasa_precision_pct}%." : 'No se pudo auditar (token expirado o ya procesado).',
        ], 200);
    }
}
