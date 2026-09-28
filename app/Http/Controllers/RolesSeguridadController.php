<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\RolUser;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolesSeguridadController extends Controller
{
    // ==========================================
    // SECCIÓN 1: GESTIÓN DE ROLES DE USUARIO
    // ==========================================

    public function roles(Request $request): JsonResponse
    {
        try {
            $query = RolUser::withCount(['usuarios' => function ($q) {
                $q->where('UsuarioEliminado', 'N');
            }]);

            if ($request->input('eliminados') == '1' || $request->input('eliminados') == 'true') {
                $query->where('Rol_UserEliminado', 'S');
            } elseif ($request->input('todos') != '1') {
                $query->where('Rol_UserEliminado', 'N');
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('Rol_UserRol_Usercol', 'like', "%{$search}%")
                      ->orWhere('Rol_UserEmpleadocol', 'like', "%{$search}%");
                });
            }

            $query->orderBy('Rol_UserId', 'asc');
            $roles = $query->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
                'message' => 'Lista de roles obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener roles: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeRol(Request $request): JsonResponse
    {
        $request->validate([
            'Rol_UserRol_Usercol' => 'required|string|max:45|unique:Rol_User,Rol_UserRol_Usercol',
            'Rol_UserEmpleadocol' => 'nullable|string|max:45',
        ]);

        try {
            $last = DB::table('Rol_User')
                ->where('Rol_UserId', 'like', 'ROL-%')
                ->orderBy('Rol_UserId', 'desc')
                ->value('Rol_UserId');

            $nextNum = $last ? ((int) substr($last, 4)) + 1 : 1;
            $nextId = 'ROL-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);

            $nombre = trim($request->input('Rol_UserRol_Usercol'));
            $codigo = $request->filled('Rol_UserEmpleadocol')
                ? strtoupper(trim($request->input('Rol_UserEmpleadocol')))
                : strtoupper(str_replace(' ', '_', $nombre));

            $rol = RolUser::create(array_merge([
                'Rol_UserId' => $nextId,
                'Rol_UserRol_Usercol' => $nombre,
                'Rol_UserEmpleadocol' => $codigo,
                'Rol_UserEliminado' => 'N',
            ], AuditHelper::getCreationAudit('Rol_User')));

            return response()->json([
                'success' => true,
                'data' => $rol,
                'message' => 'Rol de usuario creado exitosamente.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al crear rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateRol(Request $request, string $id): JsonResponse
    {
        $rol = RolUser::where('Rol_UserId', $id)->first();
        if (!$rol) {
            return response()->json(['success' => false, 'message' => 'Rol no encontrado.'], 404);
        }

        $request->validate([
            'Rol_UserRol_Usercol' => 'required|string|max:45',
            'Rol_UserEmpleadocol' => 'nullable|string|max:45',
        ]);

        try {
            $nombre = trim($request->input('Rol_UserRol_Usercol'));
            $codigo = $request->filled('Rol_UserEmpleadocol')
                ? strtoupper(trim($request->input('Rol_UserEmpleadocol')))
                : $rol->Rol_UserEmpleadocol;

            $rol->update(array_merge([
                'Rol_UserRol_Usercol' => $nombre,
                'Rol_UserEmpleadocol' => $codigo,
            ], AuditHelper::getModificationAudit('Rol_User')));

            return response()->json([
                'success' => true,
                'data' => $rol,
                'message' => 'Rol de usuario actualizado exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroyRol(string $id): JsonResponse
    {
        $rol = RolUser::where('Rol_UserId', $id)->first();
        if (!$rol) {
            return response()->json(['success' => false, 'message' => 'Rol no encontrado.'], 404);
        }

        try {
            $rol->update(array_merge([
                'Rol_UserEliminado' => 'S',
            ], AuditHelper::getDeletionAudit('Rol_User')));

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Rol eliminado lógicamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restoreRol(string $id): JsonResponse
    {
        $rol = RolUser::where('Rol_UserId', $id)->first();
        if (!$rol) {
            return response()->json(['success' => false, 'message' => 'Rol no encontrado.'], 404);
        }

        try {
            $rol->update(array_merge([
                'Rol_UserEliminado' => 'N',
                'Rol_UserUsuarioEliminacion' => null,
                'Rol_UserHostEliminacion' => null,
                'Rol_UserFechaEliminacion' => null,
            ], AuditHelper::getModificationAudit('Rol_User')));

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Rol restaurado exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==========================================
    // SECCIÓN 2: GESTIÓN DE CARGOS DE PERSONAL
    // ==========================================

    public function cargos(Request $request): JsonResponse
    {
        try {
            $query = Cargo::withCount(['empleados' => function ($q) {
                $q->where('EmpleadoEliminado', 'N');
            }]);

            if ($request->input('eliminados') == '1' || $request->input('eliminados') == 'true') {
                $query->where('cargoEliminado', 'S');
            } elseif ($request->input('todos') != '1') {
                $query->where('cargoEliminado', 'N');
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where('cargoDescripcion', 'like', "%{$search}%");
            }

            $query->orderBy('cargoId', 'asc');
            $cargos = $query->get();

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

    public function storeCargo(Request $request): JsonResponse
    {
        $request->validate([
            'cargoDescripcion' => 'required|string|max:45|unique:cargo,cargoDescripcion',
            'cargoEstado' => 'nullable|string|in:A,I',
        ]);

        try {
            $last = DB::table('cargo')
                ->where('cargoId', 'like', 'CAR-%')
                ->orderBy('cargoId', 'desc')
                ->value('cargoId');

            $nextNum = $last ? ((int) substr($last, 4)) + 1 : 1;
            $nextId = 'CAR-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);

            $cargo = Cargo::create(array_merge([
                'cargoId' => $nextId,
                'cargoDescripcion' => trim($request->input('cargoDescripcion')),
                'cargoEstado' => $request->input('cargoEstado', 'A'),
                'cargoEliminado' => 'N',
            ], AuditHelper::getCreationAudit('cargo')));

            return response()->json([
                'success' => true,
                'data' => $cargo,
                'message' => 'Cargo de personal registrado exitosamente.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar cargo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateCargo(Request $request, string $id): JsonResponse
    {
        $cargo = Cargo::where('cargoId', $id)->first();
        if (!$cargo) {
            return response()->json(['success' => false, 'message' => 'Cargo no encontrado.'], 404);
        }

        $request->validate([
            'cargoDescripcion' => 'required|string|max:45',
            'cargoEstado' => 'nullable|string|in:A,I',
        ]);

        try {
            $cargo->update(array_merge([
                'cargoDescripcion' => trim($request->input('cargoDescripcion')),
                'cargoEstado' => $request->input('cargoEstado', $cargo->cargoEstado),
            ], AuditHelper::getModificationAudit('cargo')));

            return response()->json([
                'success' => true,
                'data' => $cargo,
                'message' => 'Cargo de personal actualizado exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar cargo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroyCargo(string $id): JsonResponse
    {
        $cargo = Cargo::where('cargoId', $id)->first();
        if (!$cargo) {
            return response()->json(['success' => false, 'message' => 'Cargo no encontrado.'], 404);
        }

        try {
            $cargo->update(array_merge([
                'cargoEliminado' => 'S',
                'cargoEstado' => 'I',
            ], AuditHelper::getDeletionAudit('cargo')));

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Cargo eliminado lógicamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar cargo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restoreCargo(string $id): JsonResponse
    {
        $cargo = Cargo::where('cargoId', $id)->first();
        if (!$cargo) {
            return response()->json(['success' => false, 'message' => 'Cargo no encontrado.'], 404);
        }

        try {
            $cargo->update(array_merge([
                'cargoEliminado' => 'N',
                'cargoEstado' => 'A',
                'cargoUsuarioEliminacion' => null,
                'cargoHostEliminacion' => null,
                'cargoFechaEliminacion' => null,
            ], AuditHelper::getModificationAudit('cargo')));

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Cargo restaurado exitosamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar cargo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
