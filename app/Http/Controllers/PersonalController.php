<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePersonalRequest;
use App\Http\Requests\UpdateEstadoRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePersonalRequest;
use App\Repositories\Contracts\CargoRepositoryInterface;
use App\Services\GestionPersonalService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    /**
     * Dependency injection of GestionPersonalService and CargoRepositoryInterface.
     */
    public function __construct(
        protected GestionPersonalService $gestionPersonalService,
        protected CargoRepositoryInterface $cargoRepository
    ) {}

    /**
     * Display a listing of personal/empleados.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'cargoId', 'estado', 'eliminados', 'todos']);
            $perPage = (int) $request->input('per_page', 50);

            $personal = $this->gestionPersonalService->listarPersonal($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $personal,
                'message' => 'Lista de personal obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener la lista de personal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created Empleado and automatically generate Usuario + Async Welcome Mail.
     */
    public function store(CreatePersonalRequest $request): JsonResponse
    {
        try {
            $empleado = $this->gestionPersonalService->crearPersonal(
                $request->validated(),
                $request->input('rolUserId')
            );

            return response()->json([
                'success' => true,
                'data' => $empleado,
                'message' => 'Personal y usuario creados exitosamente. Correo de bienvenida encolado.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos enviados.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar personal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified personal record.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $empleado = $this->gestionPersonalService->obtenerPersonalPorId($id);

            return response()->json([
                'success' => true,
                'data' => $empleado,
                'message' => 'Detalle de personal obtenido correctamente.',
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
     * Update the specified personal record, synchronize user credentials and send update notification.
     */
    public function update(UpdatePersonalRequest $request, string $id): JsonResponse
    {
        try {
            $empleado = $this->gestionPersonalService->actualizarPersonal(
                $id,
                $request->validated(),
                $request->input('rolUserId'),
                (bool) $request->input('resetearPassword', false)
            );

            return response()->json([
                'success' => true,
                'data' => $empleado,
                'message' => 'Personal y usuario actualizados correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos enviados.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar personal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update or reset password specifically for a personal account.
     */
    public function updatePassword(UpdatePasswordRequest $request, string $id): JsonResponse
    {
        try {
            $this->gestionPersonalService->cambiarPassword(
                $id,
                $request->input('password'),
                (bool) $request->input('esReset', false)
            );

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Contraseña actualizada exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar contraseña: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fast update for employee state/absence (A, V, L, S, I).
     */
    public function updateEstado(UpdateEstadoRequest $request, string $id): JsonResponse
    {
        try {
            $empleado = $this->gestionPersonalService->cambiarEstado(
                $id,
                $request->input('estado'),
                $request->input('motivo')
            );

            return response()->json([
                'success' => true,
                'data' => $empleado,
                'message' => 'Estado del empleado actualizado correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified personal record logically (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->gestionPersonalService->eliminarPersonal($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Personal y acceso de usuario eliminados lógicamente de forma exitosa.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar personal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a logically deleted personal record.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $this->gestionPersonalService->restaurarPersonal($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Personal y acceso de usuario restaurados exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar personal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List available cargos.
     */
    public function cargos(): JsonResponse
    {
        try {
            $cargos = $this->cargoRepository->getAll();

            return response()->json([
                'success' => true,
                'data' => $cargos,
                'message' => 'Lista de cargos obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener cargos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
