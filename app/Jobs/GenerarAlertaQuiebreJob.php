<?php

namespace App\Jobs;

use App\Models\Notificacion;
use App\Models\Producto;
use App\Services\GeminiService;
use App\Services\NotificacionService;
use App\Services\StockAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GenerarAlertaQuiebreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos máximos permitidos en caso de error transitorio.
     */
    public int $tries = 2;

    /**
     * Tiempo de espera antes de reintentar (segundos).
     */
    public int $backoff = 5;

    /**
     * Crear una nueva instancia del Job.
     *
     * @param string $productoId ID del producto afectado
     * @param string $zona Zona detectada ('QUIEBRE' o 'ALERTA')
     * @param float $cantidadSugerida Cantidad económica a reponer calculada con EOQ
     */
    public function __construct(
        public string $productoId,
        public string $zona,
        public float $cantidadSugerida
    ) {}

    /**
     * Ejecuta el trabajo asíncrono:
     * 1. Re-consulta el producto para asegurar datos frescos (Ajuste 2: previene falsos positivos si fue reabastecido).
     * 2. Redacta el mensaje urgente y profesional con IA (GeminiService) o plantilla determinista.
     * 3. Despacha la notificación a cada usuario destinatario activo (Jefe de Tienda y Logístico)
     *    respetando el enfriamiento de 24 horas por usuario (Ajuste 1).
     */
    public function handle(): void
    {
        try {
            // 1. Re-consulta fresca del producto en base de datos
            $producto = Producto::find($this->productoId);
            if (!$producto || $producto->ProductoEliminado === 'S') {
                Log::info("GenerarAlertaQuiebreJob: Producto {$this->productoId} no existe o fue eliminado.");
                return;
            }

            // 2. Re-verificación de la zona de stock para descartar alertas obsoletas
            $alertService = app(StockAlertService::class);
            $zonaActual = $alertService->determinarZona($producto);
            if ($zonaActual === null) {
                Log::info("GenerarAlertaQuiebreJob: Producto {$this->productoId} reabastecido mientras estaba en cola. Alerta omitida.");
                return;
            }

            // 3. Generación del texto de la alerta mediante IA o fallback
            $geminiService = app(GeminiService::class);
            $mensaje = $geminiService->generarMensajeAlertaStock(
                $producto,
                (float) $producto->ProductoStockActual,
                $this->cantidadSugerida,
                $zonaActual
            );

            // 4. Obtener destinatarios activos con roles JEFE_TIENDA y LOGISTICO
            $destinatarios = $alertService->obtenerDestinatarios();
            if ($destinatarios->isEmpty()) {
                Log::warning("GenerarAlertaQuiebreJob: No se encontraron usuarios con rol JEFE_TIENDA o LOGISTICO.");
                return;
            }

            // 5. Anti-spam por usuario (Ajuste 1: comprobar quién ya recibió alerta en 24h)
            $cooldownHoras = (int) Config::get('stock.cooldown_horas', 24);
            $yaNotificadosIds = Notificacion::where('NotificacionCategoria', 'STOCK_CRITICO')
                ->where('NotificacionReferenciaId', $producto->ProductoId)
                ->where('NotificacionFecha', '>=', now()->subHours($cooldownHoras))
                ->pluck('NotificacionUsuarioId')
                ->toArray();

            $notificacionService = app(NotificacionService::class);
            $esQuiebre = ($zonaActual === 'QUIEBRE');

            $titulo = $esQuiebre
                ? "🚨 Quiebre de Stock: {$producto->ProductoNombre}"
                : "⚠️ Stock en Alerta: {$producto->ProductoNombre}";

            $accionUrl = "/ordenes-compra/crear?producto={$producto->ProductoId}&cantidad={$this->cantidadSugerida}";
            $notificadosCount = 0;

            foreach ($destinatarios as $usuario) {
                if (in_array($usuario->UsuarioId, $yaNotificadosIds, true)) {
                    continue; // Este usuario en particular ya cuenta con la notificación activa
                }

                $notificacionService->crear([
                    'titulo'             => $titulo,
                    'mensaje'            => $mensaje,
                    'tipo'               => $esQuiebre ? 'danger' : 'warning',
                    'categoria'          => 'STOCK_CRITICO',
                    'referencia_id'      => $producto->ProductoId,
                    'badge_texto'        => $esQuiebre ? 'Quiebre de Stock' : 'Stock en Alerta',
                    'badge_tipo'         => $esQuiebre ? 'danger' : 'warning',
                    'badge_color'        => $esQuiebre ? '#EF4444' : '#F59E0B',
                    'badge_icono'        => 'alert-triangle',
                    'flujo_origen'       => 'Almacén Central',
                    'flujo_destino'      => 'Gestión de Compras',
                    'flujo_indicador'    => "Reposición Sugerida ({$this->cantidadSugerida} und)",
                    'accion_texto'       => 'Generar Orden de Compra',
                    'accion_url'         => $accionUrl,
                    'usuario_id'         => $usuario->UsuarioId,
                    'silenciosa'         => false,
                ]);

                $notificadosCount++;
            }

            Log::info("GenerarAlertaQuiebreJob completado para {$producto->ProductoId}. Usuarios notificados: {$notificadosCount}.");

        } catch (\Throwable $e) {
            Log::error("GenerarAlertaQuiebreJob fallo para {$this->productoId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
