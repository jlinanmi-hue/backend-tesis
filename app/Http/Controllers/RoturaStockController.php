<?php

namespace App\Http\Controllers;

use App\Services\RoturaStockService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoturaStockController extends Controller
{
    public function __construct(
        protected RoturaStockService $roturaStockService
    ) {}

    /**
     * Listar incidencias de rotura de stock con filtros y paginación.
     * GET /api/roturas-stock
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['tipo_rotura', 'producto_id', 'pedido_id', 'fecha_desde', 'fecha_hasta']);
            $perPage = (int) $request->input('per_page', 15);

            $roturas = $this->roturaStockService->listarRoturas($filtros, $perPage);

            return response()->json([
                'success' => true,
                'data' => $roturas,
                'message' => 'Listado de roturas de stock obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar roturas de stock: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar un intento de compra sin stock suficiente.
     * POST /api/roturas-stock/intento
     */
    public function intento(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'producto_id' => 'required|string|exists:Producto,ProductoId',
                'cantidad_solicitada' => 'required|numeric|gt:0',
                'cantidad_disponible' => 'nullable|numeric|min:0',
                'cantidad_faltante' => 'nullable|numeric|min:0',
                'pedido_id' => 'nullable|string',
                'detalle_pedido_id' => 'nullable|string',
                'observaciones' => 'nullable|string|max:250',
            ]);

            $rotura = $this->roturaStockService->registrarIntento($validated);

            return response()->json([
                'success' => true,
                'message' => 'Intento de rotura registrado para telemetría.',
                'data' => $rotura,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación al registrar intento',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar intento de rotura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirmar una rotura de stock definitiva (anulación de pedido por falta de stock).
     * POST /api/roturas-stock/confirmar
     */
    public function confirmar(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'producto_id' => 'required|string|exists:Producto,ProductoId',
                'cantidad_solicitada' => 'required|numeric|gt:0',
                'cantidad_disponible' => 'nullable|numeric|min:0',
                'cantidad_faltante' => 'nullable|numeric|min:0',
                'pedido_id' => 'nullable|string',
                'detalle_pedido_id' => 'nullable|string',
                'observaciones' => 'nullable|string|max:250',
            ]);

            $rotura = $this->roturaStockService->confirmarRotura($validated);

            return response()->json([
                'success' => true,
                'message' => 'Rotura de stock confirmada y registrada para el indicador PRS.',
                'data' => $rotura,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación al confirmar rotura',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar rotura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumen analítico de roturas para el dashboard.
     * GET /api/roturas-stock/resumen
     */
    public function resumen(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->input('fecha_desde');
            $fechaHasta = $request->input('fecha_hasta');

            $resumen = $this->roturaStockService->obtenerResumenDashboard($fechaDesde, $fechaHasta);

            return response()->json([
                'success' => true,
                'data' => $resumen,
                'message' => 'Resumen de roturas de stock obtenido exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de roturas: ' . $e->getMessage(),
            ], 500);
        }
    }
}
