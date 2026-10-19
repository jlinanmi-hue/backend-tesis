<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresParityAndEndpointsTest extends TestCase
{
    /**
     * 1. Verificar vistas analíticas de la tesis en PostgreSQL
     */
    public function test_vistas_analiticas_existen_y_retornan_datos(): void
    {
        $diarios = DB::select('SELECT COUNT(*) as c FROM v_indicadores_diarios');
        $this->assertNotEmpty($diarios);
        $this->assertGreaterThan(0, $diarios[0]->c);

        $semanales = DB::select('SELECT COUNT(*) as c FROM v_indicadores_semanales');
        $this->assertNotEmpty($semanales);
        $this->assertGreaterThan(0, $semanales[0]->c);

        $mensuales = DB::select('SELECT COUNT(*) as c FROM v_indicadores_mensuales');
        $this->assertNotEmpty($mensuales);
        $this->assertGreaterThan(0, $mensuales[0]->c);
    }

    /**
     * 2. Verificar vistas de compatibilidad de casing en PostgreSQL
     */
    public function test_vistas_compatibilidad_casing_funcionan(): void
    {
        $this->assertGreaterThan(0, DB::table('Canal_Pedido')->count());
        $this->assertGreaterThan(0, DB::table('Unidades_Medida')->count());
        $this->assertGreaterThan(0, DB::table('Categoria_producto')->count());
        $this->assertGreaterThan(0, DB::table('Detalle_Producto_Medida')->count());
        $this->assertGreaterThan(0, DB::table('Movimiento_Producto')->count());
    }

    /**
     * 3. Dashboard: Resumen Ejecutivo (Screenshot 2 fix)
     */
    public function test_dashboard_ejecutivo_retorna_200(): void
    {
        $response = $this->getJson('/api/dashboard/ejecutivo');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'estado_general',
                'kpis_principales' => ['pode', 'peor', 'prs', 'tbpp'],
                'resumen_hoy',
                'evolucion_semanal',
                'top_causas',
            ],
        ]);
    }

    /**
     * 4. Dashboard: Indicadores principales y detallados
     */
    public function test_dashboard_indicadores_principales_retorna_200(): void
    {
        $response = $this->getJson('/api/dashboard/indicators');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Detail 1 (PODE)
        $respPode = $this->getJson('/api/dashboard/indicator/1');
        $respPode->assertStatus(200);
        $respPode->assertJson(['success' => true]);

        // Detail 2 (PEOR)
        $respPeor = $this->getJson('/api/dashboard/indicator/2');
        $respPeor->assertStatus(200);
        $respPeor->assertJson(['success' => true]);

        // Detail 3 (PRS)
        $respPrs = $this->getJson('/api/dashboard/indicator/3');
        $respPrs->assertStatus(200);
        $respPrs->assertJson(['success' => true]);

        // Detail 4 (TBPP)
        $respTbpp = $this->getJson('/api/dashboard/indicator/4');
        $respTbpp->assertStatus(200);
        $respTbpp->assertJson(['success' => true]);
    }

    /**
     * 5. Dashboard: Indicadores globales retrospectivos
     */
    public function test_dashboard_indicadores_retrospectivos_retorna_200(): void
    {
        $response = $this->getJson('/api/dashboard/indicadores');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * 6. Despacho: Bandeja de pedidos con KPIs (Screenshot 1 fix)
     */
    public function test_despacho_pedidos_index_retorna_200(): void
    {
        $response = $this->getJson('/api/despacho/pedidos');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'kpis' => [
                'pendientes',
                'en_preparacion',
                'despachados_hoy',
                'con_faltante_hoy',
            ],
            'data' => [
                'data',
                'total',
                'current_page',
            ],
        ]);
    }

    /**
     * 7. Despacho: Detalle de pedido para despacho
     */
    public function test_despacho_detalle_pedido_retorna_200(): void
    {
        $pedido = Pedido::where('PedidoEliminado', 'N')->first();
        $this->assertNotNull($pedido);

        $response = $this->getJson("/api/despacho/pedidos/{$pedido->PedidoId}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'pedido',
                'items',
            ],
        ]);
    }

    /**
     * 8. Pedidos: Listado, estadísticas y detalle
     */
    public function test_pedidos_rutas_retornan_200(): void
    {
        $listResp = $this->getJson('/api/pedidos');
        $listResp->assertStatus(200);

        $statsResp = $this->getJson('/api/pedidos/stats');
        $statsResp->assertStatus(200);

        $dailyResp = $this->getJson('/api/pedidos/daily');
        $dailyResp->assertStatus(200);

        $stockResp = $this->getJson('/api/pedidos/stock');
        $stockResp->assertStatus(200);

        $pedido = Pedido::where('PedidoEliminado', 'N')->first();
        if ($pedido) {
            $showResp = $this->getJson("/api/pedidos/{$pedido->PedidoId}");
            $showResp->assertStatus(200);
        }
    }

    /**
     * 9. Productos: Catálogo, búsqueda y stock crítico
     */
    public function test_productos_rutas_retornan_200(): void
    {
        $listResp = $this->getJson('/api/productos');
        $listResp->assertStatus(200);

        $stockBajoResp = $this->getJson('/api/productos/stock-bajo');
        $stockBajoResp->assertStatus(200);

        $buscarResp = $this->getJson('/api/productos/buscar?termino=Aceite');
        $buscarResp->assertStatus(200);

        $prod = Producto::where('ProductoEliminado', 'N')->first();
        if ($prod) {
            $showResp = $this->getJson("/api/productos/{$prod->ProductoId}");
            $showResp->assertStatus(200);
        }
    }

    /**
     * 10. Clientes: Listado y detalle
     */
    public function test_clientes_rutas_retornan_200(): void
    {
        $listResp = $this->getJson('/api/clientes');
        $listResp->assertStatus(200);

        $cliente = Cliente::where('ClienteEliminado', 'N')->first();
        if ($cliente) {
            $showResp = $this->getJson("/api/clientes/{$cliente->ClienteId}");
            $showResp->assertStatus(200);
        }
    }

    /**
     * 11. Proveedores: Listado y detalle
     */
    public function test_proveedores_rutas_retornan_200(): void
    {
        $listResp = $this->getJson('/api/proveedores');
        $listResp->assertStatus(200);

        $resumenResp = $this->getJson('/api/proveedores/resumen');
        $resumenResp->assertStatus(200);

        $proveedor = Proveedor::where('ProveedorEliminado', 'N')->first();
        if ($proveedor) {
            $showResp = $this->getJson("/api/proveedores/{$proveedor->ProveedorId}");
            $showResp->assertStatus(200);
        }
    }

    /**
     * 12. Órdenes de Compra: Listado y estados
     */
    public function test_ordenes_compra_rutas_retornan_200(): void
    {
        $listResp = $this->getJson('/api/ordenes-compra');
        $listResp->assertStatus(200);

        $estadosResp = $this->getJson('/api/ordenes-compra/estados');
        $estadosResp->assertStatus(200);

        $sigCodResp = $this->getJson('/api/ordenes-compra/siguiente-codigo');
        $sigCodResp->assertStatus(200);

        $orden = OrdenCompra::where('Orden_CompraEliminado', 'N')->first();
        if ($orden) {
            $showResp = $this->getJson("/api/ordenes-compra/{$orden->Orden_CompraId}");
            $showResp->assertStatus(200);
        }
    }

    /**
     * 13. Kárdex: Movimientos y resumen
     */
    public function test_kardex_rutas_retornan_200(): void
    {
        $movsResp = $this->getJson('/api/kardex/movimientos');
        $movsResp->assertStatus(200);

        $resumenResp = $this->getJson('/api/kardex/resumen');
        $resumenResp->assertStatus(200);

        $prod = Producto::where('ProductoEliminado', 'N')->first();
        if ($prod) {
            $prodKardexResp = $this->getJson("/api/kardex/producto/{$prod->ProductoId}");
            $prodKardexResp->assertStatus(200);
        }
    }

    /**
     * 14. Notificaciones: Listado y motivos
     */
    public function test_notificaciones_rutas_retornan_200(): void
    {
        $listResp = $this->getJson('/api/notificaciones');
        $listResp->assertStatus(200);

        $motivosResp = $this->getJson('/api/notificaciones/motivos');
        $motivosResp->assertStatus(200);
    }

    /**
     * 15. Inteligencia Artificial: Status y catálogo de tools
     */
    public function test_ai_rutas_retornan_200(): void
    {
        $toolsResp = $this->getJson('/api/ai/tools');
        $toolsResp->assertStatus(200);
        $toolsResp->assertJson(['success' => true]);
        $this->assertGreaterThan(0, $toolsResp->json('data.total'));
    }

    /**
     * 16. Catálogos auxiliares (zonas delivery, canales, unidades medida)
     */
    public function test_catalogos_auxiliares_retornan_200(): void
    {
        $canalesResp = $this->getJson('/api/canales');
        $canalesResp->assertStatus(200);

        $zonasResp = $this->getJson('/api/zonas-delivery');
        $zonasResp->assertStatus(200);

        $unidadesResp = $this->getJson('/api/unidades-medida');
        $unidadesResp->assertStatus(200);
    }
}
