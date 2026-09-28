<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Http\Requests\UpdateEstadoCategoriaRequest;
use App\Services\CategoriaProductoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoriaProductoController extends Controller
{
    public function __construct(
        protected CategoriaProductoService $categoriaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'estado', 'eliminados', 'todos']);
            $perPage = (int) $request->input('per_page', 50);

            $categorias = $this->categoriaService->listarCategorias($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $categorias,
                'message' => 'Lista de categorías obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener categorías: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateCategoriaRequest $request): JsonResponse
    {
        try {
            $categoria = $this->categoriaService->crearCategoria($request->validated());

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoría registrada exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos de la categoría.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar la categoría: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $categoria = $this->categoriaService->obtenerCategoriaPorId($id);

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Detalle de la categoría obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(UpdateCategoriaRequest $request, string $id): JsonResponse
    {
        try {
            $categoria = $this->categoriaService->actualizarCategoria($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoría actualizada correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al actualizar la categoría.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar la categoría: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEstado(UpdateEstadoCategoriaRequest $request, string $id): JsonResponse
    {
        try {
            $categoria = $this->categoriaService->cambiarEstadoCategoria($id, $request->input('estado'));
            $estadoTexto = $request->input('estado') === 'A' ? 'activada' : 'desactivada';

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => "Categoría {$estadoTexto} exitosamente.",
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
                'message' => 'Error al cambiar estado de la categoría: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->categoriaService->eliminarCategoria($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Categoría eliminada lógicamente de forma exitosa.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar categoría: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $this->categoriaService->restaurarCategoria($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Categoría restaurada exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar categoría: ' . $e->getMessage(),
            ], 500);
        }
    }
}
