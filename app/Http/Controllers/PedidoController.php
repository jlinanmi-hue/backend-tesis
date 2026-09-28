<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoPedidoRequest;
use App\Http\Requests\CreatePedidoRequest;
use App\Http\Requests\UpdatePedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Services\PedidoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    public function __construct(
        protected PedidoService $pedidoService
    ) {}

    /**
     * Obtener todas las órdenes de clientes con filtros y paginación.
     * GET /api/pedidos o GET /api/orders/client
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'estado', 'cliente_id', 'canal_id', 'fecha_desde', 'fecha_hasta', 'include_deleted'
            ]);
            $perPage = (int) $request->input('per_page', 15);

            $pedidos = $this->pedidoService->listarPedidos($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => PedidoResource::collection($pedidos)->response()->getData(true),
                'message' => 'Órdenes de clientes obtenidas exitosamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar órdenes de clientes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener una orden específica por ID.
     * GET /api/pedidos/{id} o GET /api/orders/client/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $pedido = $this->pedidoService->obtenerPedidoPorId($id);

            return response()->json([
                'success' => true,
                'data' => new PedidoResource($pedido),
                'message' => "Orden de cliente {$id} obtenida exitosamente.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Crear una nueva orden de cliente (Resta stock automáticamente y asienta en Kárdex).
     * POST /api/pedidos o POST /api/orders/client
     */
    public function store(CreatePedidoRequest $request): JsonResponse
    {
        try {
            $pedido = $this->pedidoService->crearPedido($request->validated());

            return response()->json([
                'success' => true,
                'data' => new PedidoResource($pedido),
                'message' => "Orden de cliente {$pedido->PedidoId} creada exitosamente. Stock reservado y descontado del Kárdex.",
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación al crear orden de cliente.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear orden de cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una orden (solo ciertos campos permitidos y si está en estado Pendiente).
     * PUT /api/pedidos/{id} o PUT /api/orders/client/{id}
     */
    public function update(UpdatePedidoRequest $request, string $id): JsonResponse
    {
        try {
            $pedido = $this->pedidoService->actualizarPedido($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => new PedidoResource($pedido),
                'message' => "Orden de cliente {$id} actualizada exitosamente.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede actualizar la orden.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una orden lógicamente (solo si está pendiente, devuelve stock a almacén).
     * DELETE /api/pedidos/{id} o DELETE /api/orders/client/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->pedidoService->eliminarPedido($id);

            return response()->json([
                'success' => true,
                'message' => "Orden {$id} eliminada exitosamente. El stock fue reintegrado al inventario.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la orden.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar orden como COMPLETADA (no modifica stock).
     * PATCH /api/pedidos/{id}/complete o PATCH /api/orders/client/{id}/complete
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        try {
            $pedido = $this->pedidoService->completarPedido($id, $request->all());

            return response()->json([
                'success' => true,
                'data' => new PedidoResource($pedido),
                'message' => "Orden {$id} marcada como COMPLETADA. (Stock sin alteraciones).",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede completar la orden.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al completar orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar orden manualmente (devuelve stock al almacén y Kárdex).
     * PATCH /api/pedidos/{id}/cancel o PATCH /api/orders/client/{id}/cancel
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $motivo = $request->input('motivo');
            $pedido = $this->pedidoService->cancelarPedido($id, $motivo, $request->all());

            return response()->json([
                'success' => true,
                'data' => new PedidoResource($pedido),
                'message' => "Orden {$id} CANCELADA exitosamente. Stock devuelto a los productos y asentado en Kárdex.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cancelar la orden.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambiar estado genérico (con validaciones de flujo).
     * PATCH /api/pedidos/{id}/status o PATCH /api/orders/client/{id}/status
     */
    public function status(CambiarEstadoPedidoRequest $request, string $id): JsonResponse
    {
        try {
            $estado = $request->input('estado');
            $motivo = $request->input('motivo');
            $pedido = $this->pedidoService->cambiarEstado($id, $estado, $motivo, $request->validated());

            return response()->json([
                'success' => true,
                'data' => new PedidoResource($pedido),
                'message' => "Estado de la orden {$id} actualizado a {$pedido->nombre_estado}.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint interno / cron job que verifica órdenes >12h y las cancela automáticamente devolviendo stock.
     * POST /api/pedidos/check-timeout o POST /api/orders/client/check-timeout
     */
    public function checkTimeout(Request $request): JsonResponse
    {
        try {
            $horas = $request->input('horas') ? (int) $request->input('horas') : null;
            $resultado = $this->pedidoService->cancelarPedidosPorTimeout($horas);

            return response()->json([
                'success' => true,
                'data' => $resultado,
                'message' => "Verificación de timeout completada: {$resultado['total_cancelados']} órdenes expiradas canceladas.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar timeout de órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Notificar órdenes que están a punto de expirar (<2 horas restantes).
     * POST /api/pedidos/notify-expiring o POST /api/orders/client/notify-expiring
     */
    public function notifyExpiring(Request $request): JsonResponse
    {
        try {
            $horas = $request->input('horas') ? (int) $request->input('horas') : null;
            $ventana = $request->input('ventana') ? (int) $request->input('ventana') : 2;
            $resultado = $this->pedidoService->notificarPedidosPorExpirar($horas, $ventana);

            return response()->json([
                'success' => true,
                'data' => $resultado,
                'message' => "Notificación completada: {$resultado['total_notificados']} órdenes próximas a expirar.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al notificar órdenes por expirar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Historial de órdenes de un cliente específico.
     * GET /api/clients/{id}/orders o GET /api/clientes/{id}/pedidos
     */
    public function porCliente(Request $request, string $clienteId): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $pedidos = $this->pedidoService->obtenerHistorialPorCliente($clienteId, $perPage);

            return response()->json([
                'success' => true,
                'data' => PedidoResource::collection($pedidos)->response()->getData(true),
                'message' => "Historial de órdenes del cliente {$clienteId} obtenido correctamente.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial de órdenes del cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver stock disponible de productos (considerando stock físico, reservado y disponible).
     * GET /api/products/stock o GET /api/productos/stock
     */
    public function stock(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['search']);
            $stock = $this->pedidoService->obtenerStockProductos(null, $filtros);

            return response()->json([
                'success' => true,
                'data' => $stock,
                'message' => 'Disponibilidad de stock obtenida exitosamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar stock de productos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver stock de un producto específico.
     * GET /api/products/stock/{id} o GET /api/productos/{id}/stock
     */
    public function stockProducto(string $id): JsonResponse
    {
        try {
            $stock = $this->pedidoService->obtenerStockProductos($id);

            if (empty($stock)) {
                return response()->json([
                    'success' => false,
                    'message' => "Producto con ID {$id} no encontrado.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $stock,
                'message' => "Disponibilidad de stock del producto {$id} obtenida exitosamente.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar stock del producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumen estadístico: totales, pendientes, completadas, canceladas.
     * GET /api/orders/client/stats o GET /api/pedidos/stats
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'estado', 'canal_id', 'fecha_desde', 'fecha_hasta']);
            $stats = $this->pedidoService->obtenerEstadisticas($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Estadísticas de órdenes obtenidas exitosamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Órdenes del día (para el panel / dashboard).
     * GET /api/orders/client/daily o GET /api/pedidos/daily
     */
    public function daily(Request $request): JsonResponse
    {
        try {
            $fecha = $request->input('fecha');
            $perPage = (int) $request->input('per_page', 15);
            $pedidos = $this->pedidoService->obtenerPedidosDelDia($fecha, $perPage);

            return response()->json([
                'success' => true,
                'data' => PedidoResource::collection($pedidos)->response()->getData(true),
                'message' => 'Órdenes de la jornada obtenidas exitosamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener órdenes del día: ' . $e->getMessage(),
            ], 500);
        }
    }
}
