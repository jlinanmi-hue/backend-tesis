<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        // 4. Iniciar sesión única y generar token de sesión
        Auth::login($usuario);

        $ua = $request->header('User-Agent', '');
        $isMobile = preg_match('/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry/i', $ua);
        $isTablet = preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua);
        $dispositivo = $isMobile ? 'MOVIL' : ($isTablet ? 'TABLET' : 'DESKTOP');

        $sessionToken = (string) Str::uuid();

        // Control de sesión única: Invalida cualquier sesión previa del usuario
        $usuario->UsuarioSessionToken = $sessionToken;
        $usuario->UsuarioUltimoLogin = now();
        $usuario->UsuarioUltimoDispositivo = $dispositivo;
        $usuario->save();

        return response()->json([
            'success' => true,
            'data' => [
                'session_token' => $sessionToken,
                'token' => $sessionToken,
                'dispositivo' => $dispositivo,
                'usuario' => [
                    'UsuarioId' => $usuario->UsuarioId,
                    'UsuarioUserName' => $usuario->UsuarioUserName,
                    'rol' => $usuario->rolUser->Rol_UserRol_Usercol ?? null,
                    'rol_codigo' => $usuario->rolUser->Rol_UserEmpleadocol ?? null,
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
     * Verificar si la sesión actual del dispositivo sigue siendo la única activa.
     */
    public function verifySession(Request $request): JsonResponse
    {
        $usuarioId = $request->header('X-User-Id') ?? $request->input('usuario_id');
        $token = $request->header('X-Session-Token') ?? $request->input('session_token');

        if (empty($usuarioId) || empty($token)) {
            return response()->json([
                'success' => false,
                'active'  => false,
                'code'    => 'SESSION_INVALID',
                'message' => 'Faltan credenciales de sesión.',
            ], 401);
        }

        $usuario = Usuario::where('UsuarioId', $usuarioId)
            ->where('UsuarioEliminado', 'N')
            ->first();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'active'  => false,
                'code'    => 'USER_NOT_FOUND',
                'message' => 'Usuario no encontrado.',
            ], 401);
        }

        // Si el token almacenado no coincide, significa que se inició sesión en otro dispositivo
        if ($usuario->UsuarioSessionToken !== $token) {
            return response()->json([
                'success' => false,
                'active'  => false,
                'code'    => 'SESSION_SUPERSEDED',
                'ultimo_dispositivo' => $usuario->UsuarioUltimoDispositivo,
                'ultimo_login' => $usuario->UsuarioUltimoLogin,
                'message' => 'Tu sesión ha sido abierta en otro dispositivo (' . ($usuario->UsuarioUltimoDispositivo ?? 'Otro') . '). Solo se permite una sesión activa por cuenta.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'active'  => true,
            'dispositivo' => $usuario->UsuarioUltimoDispositivo,
            'message' => 'Sesión activa y válida.',
        ], 200);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): JsonResponse
    {
        $usuarioId = $request->header('X-User-Id') ?? $request->input('usuario_id');
        if ($usuarioId) {
            Usuario::where('UsuarioId', $usuarioId)->update(['UsuarioSessionToken' => null]);
        }

        Auth::logout();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Sesión cerrada correctamente.',
        ], 200);
    }
}
