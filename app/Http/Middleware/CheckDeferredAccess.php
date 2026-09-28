<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDeferredAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->empleado) {
            $empleado = $user->empleado;

            if ($empleado->EmpleadoEliminado === 'S' || $empleado->EmpleadoEstado === 'I') {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Tu cuenta se encuentra inactiva o ha sido dada de baja. Contacta a Recursos Humanos.',
                ], 403);
            }

            if (!empty($empleado->EmpleadoFechaIngreso)) {
                $fechaIngreso = Carbon::parse($empleado->EmpleadoFechaIngreso)->startOfDay();
                $hoy = now()->startOfDay();

                if ($hoy->lt($fechaIngreso)) {
                    return response()->json([
                        'success' => false,
                        'data' => [
                            'fecha_ingreso' => $fechaIngreso->format('d/m/Y'),
                        ],
                        'message' => "Acceso restringido. Tu cuenta estará habilitada a partir del {$fechaIngreso->format('d/m/Y')}.",
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
