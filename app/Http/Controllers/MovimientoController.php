<?php

namespace App\Http\Controllers;

use App\Services\InventarioService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MovimientoController extends Controller
{
    public function __construct(
        protected InventarioService $inventarioService
    ) {}

    /**
     * List inventory movements (Kardex) with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'productoId', 'tipoMovimiento', 'fechaDesde', 'fechaHasta', 'dias', 'subtipo']);
            $perPage = (int) $request->input('per_page', 8);

            $movimientos = $this->inventarioService->listarMovimientos($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $movimientos,
                'message' => 'Historial de movimientos de inventario obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener movimientos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register a new inventory movement (Entry or Exit) and update stock automatically.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'productoId' => ['required', 'string', 'exists:Producto,ProductoId'],
                'unidadesMedidaId' => ['required', 'string', 'exists:unidades_medida,unidades_medidaId'],
                'tipoMovimiento' => ['required', 'string', 'in:E,S,e,s'],
                'cantidad' => ['required', 'numeric', 'min:0.01'],
                'documentoOperacionId' => ['nullable', 'string', 'max:45'],
                'precioUnitario' => ['nullable', 'numeric', 'min:0'],
                'fechaMovimiento' => ['nullable', 'date'],
                'fechaAproxSalida' => ['nullable', 'date'],
            ]);

            $movimiento = $this->inventarioService->registrarMovimiento($validated);

            return response()->json([
                'success' => true,
                'data' => $movimiento,
                'message' => 'Movimiento de inventario registrado y stock actualizado exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al registrar movimiento.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar movimiento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar un ajuste manual de inventario con motivo obligatorio (mínimo 10 caracteres).
     * POST /api/inventario/movimientos/ajuste-manual
     */
    public function ajusteManual(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'producto_id' => ['sometimes', 'string', 'exists:Producto,ProductoId'],
                'productoId' => ['sometimes', 'string', 'exists:Producto,ProductoId'],
                'unidades_medida_id' => ['nullable', 'string', 'exists:unidades_medida,unidades_medidaId'],
                'unidadesMedidaId' => ['nullable', 'string', 'exists:unidades_medida,unidades_medidaId'],
                'tipo_movimiento' => ['sometimes', 'string', 'in:E,S,e,s'],
                'tipo' => ['sometimes', 'string', 'in:E,S,e,s'],
                'cantidad' => ['required', 'numeric', 'gt:0'],
                'motivo' => ['required', 'string', 'min:10', 'max:250'],
                'precio_unitario' => ['nullable', 'numeric', 'min:0'],
            ], [
                'motivo.required' => 'El motivo del ajuste manual es obligatorio.',
                'motivo.min' => 'El motivo debe tener al menos 10 caracteres explicativos.',
            ]);

            $productoId = $validated['producto_id'] ?? $validated['productoId'] ?? null;
            if (empty($productoId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El identificador del producto es obligatorio.',
                ], 422);
            }

            $tipo = strtoupper((string) ($validated['tipo_movimiento'] ?? $validated['tipo'] ?? 'E'));

            $datos = [
                'producto_id' => $productoId,
                'unidades_medida_id' => $validated['unidades_medida_id'] ?? $validated['unidadesMedidaId'] ?? 'UND-00001',
                'tipo_movimiento' => $tipo,
                'cantidad' => (float) $validated['cantidad'],
                'motivo' => $validated['motivo'],
                'precio_unitario' => $validated['precio_unitario'] ?? null,
            ];

            $movimiento = $this->inventarioService->ajusteManual($datos);

            return response()->json([
                'success' => true,
                'data' => $movimiento,
                'message' => 'Ajuste manual de inventario registrado y auditado correctamente en Kárdex.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación al registrar ajuste manual',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar ajuste manual: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all movements specifically for a given product.
     */
    public function porProducto(Request $request, string $productoId): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $movimientos = $this->inventarioService->obtenerMovimientosPorProducto($productoId, $perPage);

            return response()->json([
                'success' => true,
                'data' => $movimientos,
                'message' => "Movimientos del producto '{$productoId}' obtenidos correctamente.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener movimientos del producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified movement record.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $movimiento = $this->inventarioService->obtenerMovimientoPorId($id);

            return response()->json([
                'success' => true,
                'data' => $movimiento,
                'message' => 'Detalle del movimiento obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
