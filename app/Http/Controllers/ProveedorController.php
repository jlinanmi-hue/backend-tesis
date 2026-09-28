<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProveedorRequest;
use App\Http\Requests\UpdateEstadoProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Services\ProveedorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProveedorController extends Controller
{
    public function __construct(
        protected ProveedorService $proveedorService
    ) {}

    /**
     * List suppliers with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'estado', 'eliminados', 'tab', 'all']);
            $perPage = (int) $request->input('per_page', 15);

            $proveedores = $this->proveedorService->listarProveedores($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $proveedores,
                'message' => 'Lista de proveedores obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener la lista de proveedores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumen de contadores KPI del directorio de proveedores.
     */
    public function resumen(): JsonResponse
    {
        try {
            $resumen = $this->proveedorService->obtenerResumenDirectorio();

            return response()->json([
                'success' => true,
                'data' => $resumen,
                'message' => 'Resumen de proveedores obtenido exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener resumen de proveedores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Quick search for autocomplete / purchase order modal.
     */
    public function buscar(Request $request): JsonResponse
    {
        try {
            $q = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));
            $limit = (int) $request->input('limit', 10);

            if (empty($q)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Especifique un término de búsqueda en el parámetro q o search.',
                ], 200);
            }

            $proveedores = $this->proveedorService->buscarProveedoresRapido($q, $limit);

            return response()->json([
                'success' => true,
                'data' => $proveedores,
                'message' => 'Búsqueda de proveedores completada con éxito.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error en búsqueda de proveedores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el catálogo de productos suministrados por un proveedor específico.
     * GET /api/proveedores/{id}/productos
     */
    public function productos(string $id): JsonResponse
    {
        try {
            $resultado = $this->proveedorService->obtenerProductosProveedor($id);

            return response()->json([
                'success' => true,
                'data' => $resultado,
                'message' => 'Catálogo de productos del proveedor obtenido exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Store a newly created supplier.
     */
    public function store(CreateProveedorRequest $request): JsonResponse
    {
        try {
            $proveedor = $this->proveedorService->crearProveedor($request->validated());

            return response()->json([
                'success' => true,
                'data' => $proveedor,
                'message' => 'Proveedor registrado exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos del proveedor.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar el proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified supplier.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $proveedor = $this->proveedorService->obtenerProveedorPorId($id);

            return response()->json([
                'success' => true,
                'data' => $proveedor,
                'message' => 'Detalle del proveedor obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified supplier.
     */
    public function update(UpdateProveedorRequest $request, string $id): JsonResponse
    {
        try {
            $proveedor = $this->proveedorService->actualizarProveedor($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $proveedor,
                'message' => 'Proveedor actualizado correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al actualizar el proveedor.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar el proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate or Deactivate supplier.
     */
    public function updateEstado(UpdateEstadoProveedorRequest $request, string $id): JsonResponse
    {
        try {
            $proveedor = $this->proveedorService->cambiarEstadoProveedor($id, $request->input('estado'));

            $estadoTexto = $request->input('estado') === 'A' ? 'activado' : 'desactivado';

            return response()->json([
                'success' => true,
                'data' => $proveedor,
                'message' => "Proveedor {$estadoTexto} exitosamente.",
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en el estado.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al cambiar estado del proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified supplier logically (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->proveedorService->eliminarProveedor($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Proveedor eliminado lógicamente de forma exitosa.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a logically deleted supplier.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $this->proveedorService->restaurarProveedor($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Proveedor restaurado exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }
}
