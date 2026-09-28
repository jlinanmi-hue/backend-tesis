<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate user with deferred access validation.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $username = $request->input('username');
        $password = $request->input('password');

        // 1. Buscar usuario por UserName o Correo del empleado vinculado
        $usuario = Usuario::with(['empleado.cargo', 'rolUser'])
            ->where(function ($q) use ($username) {
                $q->where('UsuarioUserName', $username)
                  ->orWhereHas('empleado', function ($qEmp) use ($username) {
                      $qEmp->where('EmpleadoCorreo', $username);
                  });
            })
            ->where('UsuarioEliminado', 'N')
            ->first();

        if (!$usuario || !Hash::check($password, $usuario->UsuarioPassword)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Credenciales incorrectas o usuario no encontrado.',
            ], 401);
        }

        // 2. Validar estado del Empleado vinculado
        $empleado = $usuario->empleado;
        if (!$empleado || $empleado->EmpleadoEliminado === 'S') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'El empleado asociado a esta cuenta se encuentra dado de baja.',
            ], 403);
        }

        if ($empleado->EmpleadoEstado === 'I') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'La cuenta de empleado se encuentra inactiva.',
            ], 403);
        }

        // 3. VALIDACIÓN DE ACTIVACIÓN DIFERIDA (now < EmpleadoFechaIngreso)
        if (!empty($empleado->EmpleadoFechaIngreso)) {
            $fechaIngreso = Carbon::parse($empleado->EmpleadoFechaIngreso)->startOfDay();
            $hoy = now()->startOfDay();

            if ($hoy->lt($fechaIngreso)) {
                return response()->json([
                    'success' => false,
                    'data' => [
                        'fecha_ingreso' => $fechaIngreso->format('d/m/Y'),
                        'dias_restantes' => (int) $hoy->diffInDays($fechaIngreso),
                    ],
                    'message' => "Acceso no habilitado todavía. Tu fecha de ingreso es el {$fechaIngreso->format('d/m/Y')}. A partir de ese día, tus credenciales estarán habilitadas para el aplicativo web.",
                ], 403);
            }
        }

        // 4. Iniciar sesión o generar token
        Auth::login($usuario);

        return response()->json([
            'success' => true,
            'data' => [
                'usuario' => [
                    'UsuarioId' => $usuario->UsuarioId,
                    'UsuarioUserName' => $usuario->UsuarioUserName,
                    'rol' => $usuario->rolUser->Rol_UserRol_Usercol ?? null,
                ],
                'empleado' => [
                    'EmpleadoId' => $empleado->EmpleadoId,
                    'EmpleadoNombres' => $empleado->EmpleadoNombres,
                    'EmpleadoApellidos' => $empleado->EmpleadoApellidos,
                    'EmpleadoCorreo' => $empleado->EmpleadoCorreo,
                    'EmpleadoFechaIngreso' => $empleado->EmpleadoFechaIngreso ? Carbon::parse($empleado->EmpleadoFechaIngreso)->format('Y-m-d') : null,
                    'cargo' => $empleado->cargo->cargoDescripcion ?? null,
                    'estado' => $empleado->EmpleadoEstado,
                ],
            ],
            'message' => 'Inicio de sesión exitoso.',
        ], 200);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Sesión cerrada correctamente.',
        ], 200);
    }
}
