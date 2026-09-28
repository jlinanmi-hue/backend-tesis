<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUnidadesMedidaRequest;
use App\Http\Requests\UpdateEstadoUnidadesMedidaRequest;
use App\Http\Requests\UpdateUnidadesMedidaRequest;
use App\Services\UnidadesMedidaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UnidadesMedidaController extends Controller
{
    public function __construct(
        protected UnidadesMedidaService $unidadesService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'estado', 'eliminados', 'todos']);
            $perPage = (int) $request->input('per_page', 15);

            $unidades = $this->unidadesService->listarUnidades($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $unidades,
                'message' => 'Lista de unidades de medida obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener unidades de medida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateUnidadesMedidaRequest $request): JsonResponse
    {
        try {
            $unidad = $this->unidadesService->crearUnidad($request->validated());

            return response()->json([
                'success' => true,
                'data' => $unidad,
                'message' => 'Unidad de medida registrada exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos de la unidad de medida.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar unidad de medida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $unidad = $this->unidadesService->obtenerUnidadPorId($id);

            return response()->json([
                'success' => true,
                'data' => $unidad,
                'message' => 'Detalle de la unidad de medida obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(UpdateUnidadesMedidaRequest $request, string $id): JsonResponse
    {
        try {
            $unidad = $this->unidadesService->actualizarUnidad($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $unidad,
                'message' => 'Unidad de medida actualizada correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al actualizar la unidad de medida.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar la unidad de medida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEstado(UpdateEstadoUnidadesMedidaRequest $request, string $id): JsonResponse
    {
        try {
            $unidad = $this->unidadesService->cambiarEstadoUnidad($id, $request->input('estado'));
            $estadoTexto = $request->input('estado') === 'A' ? 'activada' : 'desactivada';

            return response()->json([
                'success' => true,
                'data' => $unidad,
                'message' => "Unidad de medida {$estadoTexto} exitosamente.",
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
                'message' => 'Error al cambiar estado de la unidad de medida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->unidadesService->eliminarUnidad($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Unidad de medida eliminada lógicamente de forma exitosa.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar unidad de medida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $this->unidadesService->restaurarUnidad($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Unidad de medida restaurada exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar unidad de medida: ' . $e->getMessage(),
            ], 500);
        }
    }
}
