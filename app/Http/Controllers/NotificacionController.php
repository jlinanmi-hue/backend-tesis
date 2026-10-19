<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnviarNotificacionManualRequest;
use App\Services\NotificacionManualService;
use App\Services\NotificacionService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificacionController extends Controller
{
    public function __construct(
        protected NotificacionService $notificacionService,
        protected NotificacionManualService $notificacionManualService
    ) {}

    /**
     * Listar notificaciones con paginación y filtro de no leídas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['tipo', 'categoria', 'search', 'solo_no_leidas', 'usuario_id']);
            $perPage = (int) $request->input('per_page', 15);

            $resultado = $this->notificacionService->listar($filtros, $perPage);

            return response()->json([
                'success' => true,
                'data' => $resultado['notificaciones'],
                'total_no_leidas' => $resultado['total_no_leidas'],
                'message' => 'Notificaciones obtenidas correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener notificaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar una notificación como leída
     */
    public function marcarLeida(string $id): JsonResponse
    {
        try {
            $ok = $this->notificacionService->marcarLeida($id);

            if (!$ok) {
                return response()->json([
                    'success' => false,
                    'message' => "Notificación con ID '{$id}' no encontrada.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodas(Request $request): JsonResponse
    {
        try {
            $usuarioId = $request->input('usuario_id');
            $actualizadas = $this->notificacionService->marcarTodasLeidas($usuarioId);

            return response()->json([
                'success' => true,
                'total_actualizadas' => $actualizadas,
                'message' => "{$actualizadas} notificaciones marcadas como leídas.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una notificación
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $ok = $this->notificacionService->eliminar($id);

            if (!$ok) {
                return response()->json([
                    'success' => false,
                    'message' => "Notificación '{$id}' no encontrada.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar notificación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar todas las notificaciones
     */
    public function destroyAll(Request $request): JsonResponse
    {
        try {
            $usuarioId = $request->input('usuario_id');
            $cant = $this->notificacionService->eliminarTodas($usuarioId);

            return response()->json([
                'success' => true,
                'total_eliminadas' => $cant,
                'message' => "{$cant} notificaciones eliminadas.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar notificaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint SSE (Server-Sent Events) para recibir notificaciones en tiempo real
     */
    public function stream(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request) {
            // Deshabilitar buffering de salida en PHP
            if (ob_get_level()) {
                ob_end_clean();
            }

            $ultimoIdEnviado = null;
            $contadorHeartbeat = 0;

            // Stream en vivo por hasta 25 segundos por ciclo (reconecta automáticamente en navegador)
            $tiempoFin = time() + 25;

            while (time() < $tiempoFin) {
                $eventos = $this->notificacionService->obtenerEventosRecientes(
                    10,
                    $request->input('usuario_id')
                );

                if (!empty($eventos)) {
                    $ultimoEvento = $eventos[0];
                    if ($ultimoEvento && ($ultimoEvento['id'] !== $ultimoIdEnviado)) {
                        echo "event: notificacion\n";
                        echo "data: " . json_encode($ultimoEvento) . "\n\n";
                        flush();
                        $ultimoIdEnviado = $ultimoEvento['id'];
                    }
                }

                // Heartbeat cada 5 segundos para mantener viva la conexión
                $contadorHeartbeat++;
                if ($contadorHeartbeat >= 5) {
                    echo "event: ping\n";
                    echo "data: {\"timestamp\":" . time() . "}\n\n";
                    flush();
                    $contadorHeartbeat = 0;
                }

                // Esperar 1 segundo antes de la siguiente verificación
                sleep(1);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * Emitir notificación de prueba
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $silenciosa = $request->boolean('silenciosa', false);
            $tipo = $request->input('tipo', 'merma');

            $notificacion = $this->notificacionService->crear([
                'titulo' => $request->input('titulo', 'Alerta de Prueba del Sistema'),
                'mensaje' => $request->input('mensaje', 'Esta es una notificación de prueba en tiempo real.'),
                'tipo' => $tipo,
                'categoria' => 'INVENTARIO',
                'referencia_id' => 'TEST-' . rand(1000, 9999),
                'silenciosa' => $silenciosa,
                'badge_texto' => $silenciosa ? 'Modo Silencioso' : 'Alerta Sonora',
                'badge_tipo' => $silenciosa ? 'info' : 'warning',
                'badge_color' => $silenciosa ? '#3B82F6' : '#F59E0B',
                'badge_icono' => $silenciosa ? 'volume_off' : 'notifications_active',
                'flujo_origen' => 'Servidor Backend',
                'flujo_destino' => ($tipo === 'warning' ? 'Gestión de Compras' : ($tipo === 'success' ? 'Almacén Central' : 'Control de Mermas')),
                'flujo_indicador' => 'Conexión SSE OK',
                'impacto_financiero' => [
                    'costo_total_perdido' => 25.50,
                    'venta_total_perdida' => 32.00,
                    'moneda' => 'S/',
                ],
                'accion_texto' => ($tipo === 'warning' ? 'Generar Orden de Compra' : ($tipo === 'success' ? 'Ver Movimientos en Kárdex' : ($tipo === 'merma' ? 'Ver Mermas y Ajustes' : 'Ver Indicadores'))),
                'accion_url' => ($tipo === 'warning' ? '/ordenes-compra/crear?producto=PROD-00001&cantidad=50' : ($tipo === 'success' ? '/inventario/movimientos' : ($tipo === 'merma' ? '/inventario/ajustes' : '/indicadores'))),
            ]);

            return response()->json([
                'success' => true,
                'data' => $notificacion,
                'message' => 'Notificación de prueba emitida con éxito en tiempo real.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al emitir notificación de prueba: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar una notificación manual segmentada (usuarios / roles / todos).
     */
    public function enviarManual(EnviarNotificacionManualRequest $request): JsonResponse
    {
        try {
            $resultado = $this->notificacionManualService->enviar($request->validated());

            return response()->json([
                'success' => true,
                'data' => $resultado,
                'message' => "Notificación enviada a {$resultado['enviadas']} usuario(s).",
            ], 201);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al enviar la notificación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Catálogo de motivos, prioridades y roles para el compositor.
     */
    public function motivos(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->notificacionManualService->catalogo(),
                'message' => 'Catálogo de notificaciones manuales obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener el catálogo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Búsqueda de usuarios activos para el selector de destinatarios.
     */
    public function buscarUsuarios(Request $request): JsonResponse
    {
        try {
            $termino = (string) $request->input('q', '');

            return response()->json([
                'success' => true,
                'data' => [
                    'usuarios' => $this->notificacionManualService->buscarUsuarios($termino),
                ],
                'message' => 'Usuarios encontrados.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al buscar usuarios: ' . $e->getMessage(),
            ], 500);
        }
    }
}
