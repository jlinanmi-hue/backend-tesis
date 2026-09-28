<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Requests\UpdateEstadoClienteRequest;
use App\Services\ClienteService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    public function __construct(
        protected ClienteService $clienteService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search',
                'estado',
                'tab',
                'eliminados',
                'min_compras',
                'dias_recientes',
                'dias_inactividad',
                'orden',
            ]);
            $perPage = (int) $request->input('per_page', 15);

            $clientes = $this->clienteService->listarClientes($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $clientes,
                'message' => 'Lista de clientes obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener clientes: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resumen(): JsonResponse
    {
        try {
            $resumen = $this->clienteService->obtenerResumenDirectorio();

            return response()->json([
                'success' => true,
                'data' => $resumen,
                'message' => 'Resumen del directorio de clientes obtenido exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener resumen: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function inactivos(Request $request): JsonResponse
    {
        try {
            $dias = (int) $request->input('dias', 90);
            $perPage = (int) $request->input('per_page', 15);
            $clientes = $this->clienteService->obtenerClientesInactivos($dias, $perPage);

            return response()->json([
                'success' => true,
                'data' => $clientes,
                'message' => "Clientes inactivos (sin compras en {$dias} días) obtenidos correctamente.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener clientes inactivos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function buscar(Request $request): JsonResponse
    {
        try {
            $q = (string) $request->input('q', '');
            $limit = (int) $request->input('limit', 10);

            if (trim($q) === '') {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Especifique un término de búsqueda en el parámetro q.',
                ], 200);
            }

            $clientes = $this->clienteService->buscarClientesRapido($q, $limit);

            return response()->json([
                'success' => true,
                'data' => $clientes,
                'message' => 'Búsqueda rápida de clientes completada.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error en búsqueda rápida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateClienteRequest $request): JsonResponse
    {
        try {
            $cliente = $this->clienteService->crearCliente($request->validated());

            return response()->json([
                'success' => true,
                'data' => $cliente,
                'message' => 'Cliente registrado exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos del cliente.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $cliente = $this->clienteService->obtenerClientePorId($id);

            return response()->json([
                'success' => true,
                'data' => $cliente,
                'message' => 'Detalle del cliente obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function historialPedidos(Request $request, string $id): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 10);
            $pedidos = $this->clienteService->obtenerHistorialPedidos($id, $perPage);

            return response()->json([
                'success' => true,
                'data' => $pedidos,
                'message' => 'Historial de pedidos del cliente obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener historial de pedidos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateClienteRequest $request, string $id): JsonResponse
    {
        try {
            $cliente = $this->clienteService->actualizarCliente($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $cliente,
                'message' => 'Cliente actualizado correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al actualizar cliente.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEstado(UpdateEstadoClienteRequest $request, string $id): JsonResponse
    {
        try {
            $cliente = $this->clienteService->cambiarEstadoCliente($id, $request->input('estado'));
            $estadoTexto = $request->input('estado') === 'A' ? 'activado' : 'desactivado';

            return response()->json([
                'success' => true,
                'data' => $cliente,
                'message' => "Cliente {$estadoTexto} exitosamente.",
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
                'message' => 'Error al cambiar estado del cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->clienteService->eliminarCliente($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Cliente eliminado lógicamente de forma exitosa.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $this->clienteService->restaurarCliente($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Cliente restaurado exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function sincronizarCompras(Request $request): JsonResponse
    {
        try {
            $clienteId = $request->input('cliente_id');
            $this->clienteService->sincronizarContadorCompras($clienteId);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Contador de compras y montos sincronizados exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al sincronizar compras: ' . $e->getMessage(),
            ], 500);
        }
    }
}
