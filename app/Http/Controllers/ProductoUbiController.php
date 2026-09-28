<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductoUbiRequest;
use App\Http\Requests\UpdateEstadoProductoUbiRequest;
use App\Http\Requests\UpdateProductoUbiRequest;
use App\Services\ProductoUbiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductoUbiController extends Controller
{
    public function __construct(
        protected ProductoUbiService $ubiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'estado', 'eliminados', 'todos']);
            $perPage = (int) $request->input('per_page', 15);

            $ubicaciones = $this->ubiService->listarUbicaciones($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $ubicaciones,
                'message' => 'Lista de ubicaciones de producto obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener ubicaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateProductoUbiRequest $request): JsonResponse
    {
        try {
            $ubi = $this->ubiService->crearUbicacion($request->validated());

            return response()->json([
                'success' => true,
                'data' => $ubi,
                'message' => 'Ubicación de producto registrada exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al registrar ubicación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $ubi = $this->ubiService->obtenerUbicacionPorId($id);

            return response()->json([
                'success' => true,
                'data' => $ubi,
                'message' => 'Detalle de la ubicación obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(UpdateProductoUbiRequest $request, string $id): JsonResponse
    {
        try {
            $ubi = $this->ubiService->actualizarUbicacion($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $ubi,
                'message' => 'Ubicación actualizada correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al actualizar ubicación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEstado(UpdateEstadoProductoUbiRequest $request, string $id): JsonResponse
    {
        try {
            $ubi = $this->ubiService->cambiarEstadoUbicacion($id, $request->input('estado'));
            $estadoTexto = $request->input('estado') === 'A' ? 'activada' : 'desactivada';

            return response()->json([
                'success' => true,
                'data' => $ubi,
                'message' => "Ubicación {$estadoTexto} exitosamente.",
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
                'message' => 'Error al cambiar estado de ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->ubiService->eliminarUbicacion($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Ubicación eliminada lógicamente de forma exitosa.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $this->ubiService->restaurarUbicacion($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Ubicación restaurada exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }
}
