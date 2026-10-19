<?php

namespace Tests\Feature;

use App\Jobs\GenerarAlertaQuiebreJob;
use App\Models\Notificacion;
use App\Models\Producto;
use App\Models\RolUser;
use App\Models\Usuario;
use App\Services\StockAlertService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StockAlertTest extends TestCase
{
    /**
     * Prueba 1: Determinar zona de inventario (QUIEBRE, ALERTA, SALUDABLE).
     */
    public function test_determinar_zona_clasifica_correctamente(): void
    {
        $service = app(StockAlertService::class);

        // Producto en QUIEBRE (stock <= 0)
        $prodQuiebre = new Producto([
            'ProductoStockActual' => 0,
            'ProductoStockMinimo' => 10,
        ]);
        $this->assertEquals('QUIEBRE', $service->determinarZona($prodQuiebre));

        // Producto en ALERTA (stock <= stock_minimo * 1.3)
        $prodAlerta = new Producto([
            'ProductoStockActual' => 12,
            'ProductoStockMinimo' => 10, // Umbral: 10 * 1.3 = 13 -> 12 <= 13
        ]);
        $this->assertEquals('ALERTA', $service->determinarZona($prodAlerta));

        // Producto SALUDABLE (stock > stock_minimo * 1.3)
        $prodSaludable = new Producto([
            'ProductoStockActual' => 20,
            'ProductoStockMinimo' => 10, // Umbral: 13 -> 20 > 13
        ]);
        $this->assertNull($service->determinarZona($prodSaludable));
    }

    /**
     * Prueba 2: Stock saludable no despacha ningún Job.
     */
    public function test_stock_saludable_no_despacha_alerta(): void
    {
        Queue::fake();

        $service = app(StockAlertService::class);
        $producto = Producto::where('ProductoEliminado', 'N')->first();

        if ($producto) {
            $producto->ProductoStockMinimo = 5;
            $producto->ProductoStockActual = 50;

            $zona = $service->evaluar($producto);

            $this->assertNull($zona);
            Queue::assertNotPushed(GenerarAlertaQuiebreJob::class);
        } else {
            $this->markTestSkipped('No hay productos en base de datos para esta prueba.');
        }
    }

    /**
     * Prueba 3: Producto en zona ALERTA o QUIEBRE despacha GenerarAlertaQuiebreJob.
     */
    public function test_stock_en_alerta_despacha_job(): void
    {
        Queue::fake();

        $service = app(StockAlertService::class);
        $producto = Producto::where('ProductoEliminado', 'N')->first();

        if ($producto) {
            // Asegurar que no haya notificaciones bloqueando en las últimas 24h para esta prueba
            Notificacion::where('NotificacionCategoria', 'STOCK_CRITICO')
                ->where('NotificacionReferenciaId', $producto->ProductoId)
                ->delete();

            $producto->ProductoStockMinimo = 20;
            $producto->ProductoStockActual = 5; // En zona de alerta (5 <= 26)

            $zona = $service->evaluar($producto);

            $this->assertEquals('ALERTA', $zona);
            Queue::assertPushed(GenerarAlertaQuiebreJob::class, function ($job) use ($producto) {
                return $job->productoId === $producto->ProductoId
                    && $job->zona === 'ALERTA'
                    && $job->cantidadSugerida >= 1;
            });
        } else {
            $this->markTestSkipped('No hay productos en base de datos para esta prueba.');
        }
    }

    /**
     * Prueba 4: Cooldown anti-spam de 24 horas bloquea alertas duplicadas si todos los usuarios ya fueron notificados.
     */
    public function test_cooldown_anti_spam_bloquea_duplicados(): void
    {
        Queue::fake();

        $service = app(StockAlertService::class);
        $producto = Producto::where('ProductoEliminado', 'N')->first();
        $destinatarios = $service->obtenerDestinatarios();

        if ($producto && $destinatarios->isNotEmpty()) {
            // Simular que todos los destinatarios ya recibieron alerta hace 2 horas
            foreach ($destinatarios as $usuario) {
                Notificacion::create([
                    'NotificacionId'          => 'TEST-ANTISPAM-' . $usuario->UsuarioId,
                    'NotificacionTitulo'      => 'Alerta Previa',
                    'NotificacionMensaje'     => 'Mensaje Previo',
                    'NotificacionTipo'        => 'warning',
                    'NotificacionCategoria'   => 'STOCK_CRITICO',
                    'NotificacionReferenciaId'=> $producto->ProductoId,
                    'NotificacionDatos'       => [],
                    'NotificacionLeida'       => 'N',
                    'NotificacionSilenciosa'  => 'N',
                    'NotificacionFecha'       => now()->subHours(2),
                    'NotificacionUsuarioId'   => $usuario->UsuarioId,
                    'NotificacionEliminado'   => 'N',
                ]);
            }

            $producto->ProductoStockMinimo = 20;
            $producto->ProductoStockActual = 0; // Quiebre

            $zona = $service->evaluar($producto);

            // Debe retornar null porque el anti-spam está activo
            $this->assertNull($zona);
            Queue::assertNotPushed(GenerarAlertaQuiebreJob::class);

            // Limpieza del registro de prueba
            Notificacion::where('NotificacionId', 'like', 'TEST-ANTISPAM-%')->delete();
        } else {
            $this->markTestSkipped('No hay productos o destinatarios en base de datos.');
        }
    }

    /**
     * Prueba 5: Micro-Ajuste 2 — GenerarAlertaQuiebreJob no crea notificación si el stock ya fue reabastecido.
     */
    public function test_job_cancela_si_producto_fue_reabastecido(): void
    {
        $producto = Producto::where('ProductoEliminado', 'N')->first();

        if ($producto) {
            $stockOriginal = $producto->ProductoStockActual;

            // Simular que se reabasteció a un nivel saludable
            $producto->ProductoStockActual = 100;
            $producto->ProductoStockMinimo = 10;
            $producto->save();

            $conteoPrevio = Notificacion::where('NotificacionCategoria', 'STOCK_CRITICO')
                ->where('NotificacionReferenciaId', $producto->ProductoId)
                ->count();

            // Ejecutar el Job directamente
            $job = new GenerarAlertaQuiebreJob($producto->ProductoId, 'QUIEBRE', 50);
            $job->handle();

            $conteoPosterior = Notificacion::where('NotificacionCategoria', 'STOCK_CRITICO')
                ->where('NotificacionReferenciaId', $producto->ProductoId)
                ->count();

            // No debió crearse ninguna notificación
            $this->assertEquals($conteoPrevio, $conteoPosterior);

            // Restaurar stock
            $producto->ProductoStockActual = $stockOriginal;
            $producto->save();
        } else {
            $this->markTestSkipped('No hay productos en base de datos.');
        }
    }

    /**
     * Prueba 6: Endpoint GET /api/ordenes-compra/precarga responde con estructura esperada.
     */
    public function test_endpoint_precarga_retorna_datos_correctos(): void
    {
        $producto = Producto::where('ProductoEliminado', 'N')->first();

        if ($producto) {
            $response = $this->getJson("/api/ordenes-compra/precarga?producto={$producto->ProductoId}&cantidad=25");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'proveedor',
                        'productos' => [
                            '*' => [
                                'tempId',
                                'productoId',
                                'productoNombre',
                                'unidadMedidaId',
                                'cantidad',
                                'precioUnitario',
                                'subtotal',
                                'unidadesDisponibles',
                            ],
                        ],
                        'observacion',
                    ],
                    'message',
                ]);

            $data = $response->json('data');
            $this->assertEquals($producto->ProductoId, $data['productos'][0]['productoId']);
            $this->assertEquals(25, $data['productos'][0]['cantidad']);
        } else {
            $this->markTestSkipped('No hay productos en base de datos.');
        }
    }
}
