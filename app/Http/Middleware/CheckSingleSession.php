<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSingleSession
{
    /**
     * Rutas exentas de verificación de sesión única.
     */
    protected array $except = [
        'api/auth/login',
        'api/auth/logout',
        'api/chat/webhook',
        'api/up',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si la ruta está en la lista de exclusiones, continuar
        foreach ($this->except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        $usuarioId = $request->header('X-User-Id') ?? $request->input('usuario_id');
        $sessionToken = $request->header('X-Session-Token') ?? $request->input('session_token');

        // Si el cliente envía los encabezados de sesión, verificamos estrictamente
        if (!empty($usuarioId) && !empty($sessionToken)) {
            $usuario = Usuario::where('UsuarioId', $usuarioId)
                ->where('UsuarioEliminado', 'N')
                ->select(['UsuarioId', 'UsuarioSessionToken', 'UsuarioUltimoDispositivo', 'UsuarioUltimoLogin'])
                ->first();

            if ($usuario && $usuario->UsuarioSessionToken && $usuario->UsuarioSessionToken !== $sessionToken) {
                return response()->json([
                    'success' => false,
                    'active'  => false,
                    'code'    => 'SESSION_SUPERSEDED',
                    'ultimo_dispositivo' => $usuario->UsuarioUltimoDispositivo,
                    'ultimo_login' => $usuario->UsuarioUltimoLogin,
                    'message' => 'Tu sesión ha sido abierta en otro dispositivo (' . ($usuario->UsuarioUltimoDispositivo ?? 'Otro') . '). Solo se permite una sesión activa por cuenta.',
                ], 401);
            }
        }

        return $next($request);
    }
}
