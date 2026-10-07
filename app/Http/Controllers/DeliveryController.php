<?php

namespace App\Http\Controllers;

use App\Services\DeliveryService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService
    ) {}

    /**
     * Listar zonas de delivery con filtros y paginación opcional.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->query('search'),
                'estado' => $request->query('estado'),
                'solo_activas' => $request->boolean('solo_activas', false),
            ];

            $perPage = (int) $request->query('per_page', 50);
            $zonas = $this->deliveryService->listarZonas($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $zonas,
                'message' => 'Zonas de delivery obtenidas exitosamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar las zonas de delivery: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar únicamente las zonas activas para mapas y selectores de pedidos.
     */
    public function activas(): JsonResponse
    {
        try {
            $zonas = $this->deliveryService->listarZonas(['solo_activas' => true], 0);

            return response()->json([
                'success' => true,
                'data' => $zonas,
                'message' => 'Zonas activas de delivery obtenidas con éxito.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener zonas activas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener una zona específica por su ID.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $zona = $this->deliveryService->obtenerZonaPorId($id);

            return response()->json([
                'success' => true,
                'data' => $zona,
                'message' => 'Zona de delivery obtenida con éxito.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Crear una nueva zona de delivery.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'Zona_DeliveryNombre' => 'nullable|string|max:100',
            'nombre' => 'nullable|string|max:100',
            'Zona_DeliveryTarifa' => 'nullable|numeric|min:0',
            'tarifa' => 'nullable|numeric|min:0',
            'Zona_DeliveryPoligonoGeoJSON' => 'nullable',
            'poligono_geojson' => 'nullable',
            'Zona_DeliveryEstado' => 'nullable|in:S,N',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $zona = $this->deliveryService->crearZona($request->all());

            return response()->json([
                'success' => true,
                'data' => $zona,
                'message' => "Zona '{$zona->Zona_DeliveryNombre}' creada exitosamente.",
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida al crear zona.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar zona: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una zona existente.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'Zona_DeliveryNombre' => 'nullable|string|max:100',
            'nombre' => 'nullable|string|max:100',
            'Zona_DeliveryTarifa' => 'nullable|numeric|min:0',
            'tarifa' => 'nullable|numeric|min:0',
            'Zona_DeliveryPoligonoGeoJSON' => 'nullable',
            'poligono_geojson' => 'nullable',
            'Zona_DeliveryEstado' => 'nullable|in:S,N',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $zona = $this->deliveryService->actualizarZona($id, $request->all());

            return response()->json([
                'success' => true,
                'data' => $zona,
                'message' => "Zona '{$zona->Zona_DeliveryNombre}' actualizada exitosamente.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida al actualizar zona.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar zona: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alternar o cambiar estado lógico ('S' ↔ 'N'). Principio 5: Nunca eliminación física.
     */
    public function cambiarEstado(Request $request, string $id): JsonResponse
    {
        try {
            $nuevoEstado = $request->input('estado');
            $zona = $this->deliveryService->cambiarEstado($id, $nuevoEstado);

            $estadoTexto = ($zona->Zona_DeliveryEstado === 'S') ? 'activada' : 'desactivada';

            return response()->json([
                'success' => true,
                'data' => $zona,
                'message' => "Zona '{$zona->Zona_DeliveryNombre}' {$estadoTexto} correctamente.",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación al cambiar estado.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado de zona: ' . $e->getMessage(),
            ], 500);
        }
    }
}
