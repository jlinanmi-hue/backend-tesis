<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarProveedorRequest;
use App\Http\Requests\CambiarEstadoOrdenCompraRequest;
use App\Http\Requests\CreateOrdenCompraRequest;
use App\Http\Requests\UpdateOrdenCompraRequest;
use App\Http\Resources\OrdenCompraResource;
use App\Services\OrdenCompraService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrdenCompraController extends Controller
{
    public function __construct(
        protected OrdenCompraService $ordenCompraService
    ) {}

    /**
     * Listar órdenes de compra con filtros y paginación.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'estado', 'proveedor_id', 'fecha_desde', 'fecha_hasta', 'include_deleted']);
            $perPage = (int) $request->input('per_page', 15);

            $ordenes = $this->ordenCompraService->listarOrdenes($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => OrdenCompraResource::collection($ordenes),
                'meta' => [
                    'current_page' => $ordenes->currentPage(),
                    'last_page' => $ordenes->lastPage(),
                    'per_page' => $ordenes->perPage(),
                    'total' => $ordenes->total(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar órdenes de compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el próximo código sugerido para la orden de compra.
     */
    public function siguienteCodigo(): JsonResponse
    {
        try {
            $codigo = $this->ordenCompraService->obtenerSiguienteCodigo();
            return response()->json([
                'success' => true,
                'codigo' => $codigo,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar código: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el catálogo de estados permitidos.
     */
    public function estados(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ordenCompraService->obtenerEstados(),
        ]);
    }

    /**
     * Registrar una nueva orden de compra con sus detalles.
     */
    public function store(CreateOrdenCompraRequest $request): JsonResponse
    {
        try {
            $orden = $this->ordenCompraService->crearOrden($request->validated());

            return response()->json([
                'success' => true,
                'message' => "Orden de compra {$orden->Orden_CompraId} registrada exitosamente.",
                'data' => new OrdenCompraResource($orden),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden de compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver el detalle de una orden de compra por su ID.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $orden = $this->ordenCompraService->obtenerOrdenPorId($id);

            return response()->json([
                'success' => true,
                'data' => new OrdenCompraResource($orden),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Actualizar una orden de compra pendiente.
     */
    public function update(UpdateOrdenCompraRequest $request, string $id): JsonResponse
    {
        try {
            $orden = $this->ordenCompraService->actualizarOrden($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => "Orden de compra {$id} actualizada correctamente.",
                'data' => new OrdenCompraResource($orden),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asignar un proveedor a una orden de compra pendiente.
     */
    public function asignarProveedor(AsignarProveedorRequest $request, string $id): JsonResponse
    {
        try {
            $proveedorId = $request->input('proveedor_id');
            $orden = $this->ordenCompraService->asignarProveedor($id, $proveedorId);

            return response()->json([
                'success' => true,
                'message' => "Proveedor asignado correctamente a la orden {$id}.",
                'data' => new OrdenCompraResource($orden),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambiar el estado de la orden ('P' = Pendiente, 'C' = Completada, 'A' = Anulada).
     */
    public function cambiarEstado(CambiarEstadoOrdenCompraRequest $request, string $id): JsonResponse
    {
        try {
            $estado = $request->input('estado');
            $motivo = $request->input('motivo');

            $orden = $this->ordenCompraService->cambiarEstado($id, $estado, $motivo);

            $mensaje = match (strtoupper($estado)) {
                'C' => "Orden de compra {$id} marcada como completada.",
                'A' => "Orden de compra {$id} anulada correctamente.",
                default => "Estado de orden {$id} actualizado a Pendiente.",
            };

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'data' => new OrdenCompraResource($orden),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
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
     * Anulación lógica (eliminación suave) de la orden.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->ordenCompraService->eliminarOrden($id);

            return response()->json([
                'success' => true,
                'message' => "Orden de compra {$id} eliminada correctamente.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
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
     * Restaurar una orden de compra eliminada lógicamente.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $orden = $this->ordenCompraService->restaurarOrden($id);

            return response()->json([
                'success' => true,
                'message' => "Orden de compra {$id} restaurada exitosamente.",
                'data' => new OrdenCompraResource($orden),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar mensaje estructurado y URL para enviar el pedido por WhatsApp al proveedor.
     */
    public function whatsapp(string $id): JsonResponse
    {
        try {
            $datos = $this->ordenCompraService->generarDatosWhatsApp($id);

            return response()->json([
                'success' => true,
                'message' => 'Enlace de WhatsApp generado correctamente.',
                'data' => $datos,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede generar WhatsApp',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar enlace de WhatsApp: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Previsualizar el mensaje de WhatsApp con formato y teléfono del proveedor.
     */
    public function mensajeWhatsapp(string $id): JsonResponse
    {
        try {
            $datos = $this->ordenCompraService->generarDatosWhatsApp($id);

            return response()->json([
                'success' => true,
                'message' => 'Previsualización de mensaje WhatsApp generada correctamente.',
                'data' => $datos,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede generar mensaje de WhatsApp',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar mensaje de WhatsApp: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar envío de pedido por WhatsApp y retornar enlace con auditoría.
     */
    public function enviarWhatsapp(string $id): JsonResponse
    {
        try {
            $datos = $this->ordenCompraService->registrarEnvioWhatsApp($id);

            return response()->json([
                'success' => true,
                'message' => 'Envío de WhatsApp registrado correctamente.',
                'data' => $datos,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede registrar envío de WhatsApp',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar envío de WhatsApp: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar y descargar/visualizar PDF de la orden de compra.
     */
    public function pdf(Request $request, string $id)
    {
        try {
            $download = $request->boolean('download') || $request->input('download') === '1';
            return $this->ordenCompraService->generarPdf($id, $download);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener URL directa pública y nombre del archivo PDF de la orden.
     */
    public function pdfUrl(string $id): JsonResponse
    {
        try {
            $datos = $this->ordenCompraService->obtenerUrlPdf($id);

            return response()->json([
                'success' => true,
                'data' => $datos,
                'message' => 'URL del PDF obtenida correctamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener URL del PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar reporte consolidado en PDF de órdenes de compra con filtros por periodo.
     */
    public function reportePdf(Request $request)
    {
        try {
            $filtros = $request->only(['fecha_desde', 'fecha_hasta', 'estado', 'proveedor_id']);
            return $this->ordenCompraService->generarReporteConsolidadoPdf($filtros);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte consolidado en PDF: ' . $e->getMessage(),
            ], 500);
        }
    }
}
