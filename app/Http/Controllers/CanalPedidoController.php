<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCanalPedidoRequest;
use App\Http\Requests\UpdateCanalPedidoRequest;
use App\Http\Requests\UpdateEstadoCanalPedidoRequest;
use App\Services\CanalPedidoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CanalPedidoController extends Controller
{
    public function __construct(
        protected CanalPedidoService $canalService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'estado', 'eliminados', 'todos']);
            $perPage = (int) $request->input('per_page', 50);

            $canales = $this->canalService->listarCanales($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $canales,
                'message' => 'Lista de canales de pedido obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener canales de pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateCanalPedidoRequest $request): JsonResponse
    {
        try {
            $canal = $this->canalService->crearCanal($request->validated());

            return response()->json([
                'success' => true,
                'data' => $canal,
                'message' => 'Canal de pedido registrado exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos del canal de pedido.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar canal de pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $canal = $this->canalService->obtenerCanalPorId($id);

            return response()->json([
                'success' => true,
                'data' => $canal,
                'message' => 'Detalle del canal de pedido obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(UpdateCanalPedidoRequest $request, string $id): JsonResponse
    {
        try {
            $canal = $this->canalService->actualizarCanal($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $canal,
                'message' => 'Canal de pedido actualizado correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al actualizar el canal de pedido.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar el canal de pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEstado(UpdateEstadoCanalPedidoRequest $request, string $id): JsonResponse
    {
        try {
            $canal = $this->canalService->cambiarEstadoCanal($id, $request->input('estado'));
            $estadoTexto = $request->input('estado') === 'A' ? 'activado' : 'desactivado';

            return response()->json([
                'success' => true,
                'data' => $canal,
                'message' => "Canal de pedido {$estadoTexto} exitosamente.",
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
                'message' => 'Error al cambiar estado del canal de pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->canalService->eliminarCanal($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Canal de pedido eliminado lógicamente de forma exitosa.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar canal de pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $this->canalService->restaurarCanal($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Canal de pedido restaurado exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar canal de pedido: ' . $e->getMessage(),
            ], 500);
        }
    }
}
