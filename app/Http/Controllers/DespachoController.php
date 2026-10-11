<?php

namespace App\Http\Controllers;

/**
 * ⚠️ REGLA DEL PROYECTO:
 * Este controlador NO crea tablas ni relaciones.
 * Delega toda la lógica a DespachoService,
 * que solo usa JOINs sobre el esquema existente.
 */

use App\Services\DespachoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DespachoController extends Controller
{
    public function __construct(
        protected DespachoService $despachoService
    ) {}

    // =========================================================================
    // GET /api/despacho/pedidos
    // Bandeja de despacho con KPIs y listado paginado
    // =========================================================================
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = [
                'estado_despacho' => $request->query('estado_despacho'),
                'canal_id'        => $request->query('canal_id'),
                'fecha_desde'     => $request->query('fecha_desde'),
                'fecha_hasta'     => $request->query('fecha_hasta'),
                'search'          => $request->query('search'),
            ];
            $perPage = (int) $request->query('per_page', 15);

            $pedidos = $this->despachoService->listarPedidosDespacho($filtros, $perPage);
            $kpis    = $this->despachoService->obtenerKpisBandeja();

            return response()->json([
                'success' => true,
                'kpis'    => $kpis,
                'data'    => $pedidos,
            ]);
        } catch (\Throwable $e) {
            Log::error('DespachoController::index', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // GET /api/despacho/pedidos/{id}
    // Detalle completo (cabecera + ítems + stock actual)
    // =========================================================================
    public function show(string $id): JsonResponse
    {
        try {
            $detalle = $this->despachoService->obtenerDetalleParaDespacho($id);
            return response()->json(['success' => true, 'data' => $detalle]);
        } catch (\Throwable $e) {
            Log::error('DespachoController::show', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    // =========================================================================
    // POST /api/despacho/pedidos/{id}/iniciar
    // Pasa a EN_PREPARACION
    // =========================================================================
    public function iniciar(string $id): JsonResponse
    {
        try {
            $pedido = $this->despachoService->iniciarPreparacion($id);
            return response()->json([
                'success' => true,
                'message' => "Preparación iniciada. Pedido {$id} en estado EN_PREPARACION.",
                'data'    => $pedido,
            ]);
        } catch (\Throwable $e) {
            Log::error('DespachoController::iniciar', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // POST /api/despacho/pedidos/{id}/cancelar-preparacion
    // Vuelve a PENDIENTE_DESPACHO
    // =========================================================================
    public function cancelarPreparacion(Request $request, string $id): JsonResponse
    {
        try {
            $motivo = $request->input('motivo', '');
            $pedido = $this->despachoService->cancelarPreparacion($id, $motivo);
            return response()->json([
                'success' => true,
                'message' => "Preparación cancelada. Pedido {$id} disponible para otro logístico.",
                'data'    => $pedido,
            ]);
        } catch (\Throwable $e) {
            Log::error('DespachoController::cancelarPreparacion', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // POST /api/despacho/pedidos/{id}/completar
    // Despacho al 100% — PODE=1, PRS=0
    // =========================================================================
    public function completar(Request $request, string $id): JsonResponse
    {
        try {
            $datos  = $request->only(['observacion']);
            $pedido = $this->despachoService->confirmarDespachoCompleto($id, $datos);
            return response()->json([
                'success' => true,
                'message' => "Pedido {$id} despachado al 100%. PODE=1, PRS=0.",
                'data'    => $pedido,
            ]);
        } catch (\RuntimeException $e) {
            // Idempotencia — ya fue procesado
            if (str_starts_with($e->getMessage(), 'IDEMPOTENCIA_409')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido ya fue procesado previamente.',
                ], 409);
            }
            Log::error('DespachoController::completar', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('DespachoController::completar', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // POST /api/despacho/pedidos/{id}/faltante
    // Despacho con faltante aceptado — PODE=1, PRS=1
    // Body: { items: [{detalle_id, cantidad_despachada, observacion}], observacion }
    // =========================================================================
    public function faltante(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'items'                       => 'required|array|min:1',
                'items.*.detalle_id'          => 'required',
                'items.*.cantidad_despachada' => 'required|numeric|min:0',
                'observacion'                 => 'required|string|min:5',
            ]);

            $pedido = $this->despachoService->confirmarDespachoConFaltante($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => "Pedido {$id} cerrado con faltante aceptado. PODE=1, PRS=1.",
                'data'    => $pedido,
            ]);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'IDEMPOTENCIA_409')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido ya fue procesado previamente.',
                ], 409);
            }
            Log::error('DespachoController::faltante', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('DespachoController::faltante', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // POST /api/despacho/pedidos/{id}/rechazar
    // Cliente desiste — PODE=0, PRS=1
    // Body: { motivo, items_con_rotura: [{detalle_id, cantidad_faltante}] }
    // =========================================================================
    public function rechazar(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'motivo' => 'required|string|min:5',
            ]);

            $pedido = $this->despachoService->rechazarDespachoPorRotura($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => "Pedido {$id} rechazado. Stock reintegrado. PODE=0, PRS=1.",
                'data'    => $pedido,
            ]);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'IDEMPOTENCIA_409')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido ya fue procesado previamente.',
                ], 409);
            }
            Log::error('DespachoController::rechazar', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('DespachoController::rechazar', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
