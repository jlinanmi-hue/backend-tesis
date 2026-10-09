<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AjusteInventarioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CanalPedidoController;
use App\Http\Controllers\CategoriaProductoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoUbiController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\PurchasePredictionController;
use App\Http\Controllers\RolesSeguridadController;
use App\Http\Controllers\RoturaStockController;
use App\Http\Controllers\SunatController;
use App\Http\Controllers\UnidadesMedidaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - I.E.P Miguel Ángel Buonarroti / Comercial Valencia
|--------------------------------------------------------------------------
*/

// 1. Rutas de Autenticación con Activación Diferida
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Rutas del Dashboard / Métricas Oficiales de Tesis (PODE, PEOR, PRS, TBPP)
Route::prefix('dashboard')->group(function () {
    Route::get('/ejecutivo', [DashboardController::class, 'ejecutivo']);
    Route::get('/indicators', [DashboardController::class, 'indicators']);
    Route::get('/indicator/{id}', [DashboardController::class, 'detail']);
    Route::get('/compare', [DashboardController::class, 'compare']);
    Route::get('/indicadores', [DashboardController::class, 'indicadores']);
    Route::get('/tokens', [DashboardController::class, 'tokenConsumption']);
    Route::get('/ficha-observacion', [DashboardController::class, 'fichaObservacion']);
    Route::get('/ficha-diaria-detalle', [DashboardController::class, 'fichaDiariaDetalle']);
    Route::get('/reporte-consolidado', [DashboardController::class, 'reporteConsolidado']);
    Route::get('/detalle-diario-semana', [DashboardController::class, 'detalleDiarioPorSemana']);
    Route::get('/semanas-del-anio', [DashboardController::class, 'semanasDelAnio']);
});

// 2. Rutas del Módulo de Gestión de Personal / Usuarios
Route::prefix('personal')->group(function () {
    Route::get('/cargos', [PersonalController::class, 'cargos']);
    Route::get('/', [PersonalController::class, 'index']);
    Route::post('/', [PersonalController::class, 'store']);
    Route::get('/{id}', [PersonalController::class, 'show']);
    Route::put('/{id}', [PersonalController::class, 'update']);
    Route::delete('/{id}', [PersonalController::class, 'destroy']);
    Route::post('/{id}/restore', [PersonalController::class, 'restore']);

    // Nuevos Endpoints: Cambio de Contraseña y Gestión de Estados/Ausencias
    Route::patch('/{id}/password', [PersonalController::class, 'updatePassword']);
    Route::patch('/{id}/estado', [PersonalController::class, 'updateEstado']);
});

// 3. Rutas del Módulo de Inventario y Movimientos (Kardex)
Route::prefix('inventario')->group(function () {
    // Catálogos auxiliares (Desplegables para formularios de inventario)
    Route::get('/categorias', [ProductoController::class, 'categorias']);
    Route::get('/unidades', [ProductoController::class, 'unidades']);
    Route::get('/proveedores', [ProductoController::class, 'proveedores']);
    Route::get('/ubicaciones', [ProductoController::class, 'ubicaciones']);

    // Stock (Consultas Rápidas)
    Route::get('/stock/resumen', [ProductoController::class, 'resumenStock']);
    Route::get('/stock/minimo', [ProductoController::class, 'stockMinimo']);
    Route::get('/stock/producto/{productoId}', [ProductoController::class, 'stockProductoPorUnidades']);

    // Movimientos (Kardex)
    Route::get('/movimientos', [MovimientoController::class, 'index']);
    Route::post('/movimientos', [MovimientoController::class, 'store']);
    Route::post('/movimientos/ajuste-manual', [MovimientoController::class, 'ajusteManual']);
    Route::get('/movimientos/producto/{productoId}', [MovimientoController::class, 'porProducto']);
    Route::get('/movimientos/{id}', [MovimientoController::class, 'show']);

    // Ajustes de Inventario (Mermas, Daños, Vencimientos, etc.)
    Route::prefix('ajustes')->group(function () {
        Route::get('/tipos', [AjusteInventarioController::class, 'tipos']);
        Route::get('/resumen', [AjusteInventarioController::class, 'resumen']);
        Route::get('/impacto-financiero', [AjusteInventarioController::class, 'impactoFinanciero']);
        Route::get('/reporte/pdf', [AjusteInventarioController::class, 'reportePdf']);
        Route::get('/reporte/excel', [AjusteInventarioController::class, 'reporteExcel']);
        Route::get('/', [AjusteInventarioController::class, 'index']);
        Route::post('/', [AjusteInventarioController::class, 'store']);
        Route::get('/{id}', [AjusteInventarioController::class, 'show']);
        Route::delete('/{id}', [AjusteInventarioController::class, 'destroy']);
        Route::post('/{id}/restore', [AjusteInventarioController::class, 'restore']);
    });

    // Productos (CRUD)
    Route::get('/productos-select', [ProductoController::class, 'productosParaSelect']);
    Route::post('/productos/express', [ProductoController::class, 'storeExpress']);
    Route::get('/productos', [ProductoController::class, 'index']);
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::get('/productos/{id}', [ProductoController::class, 'show']);
    Route::put('/productos/{id}', [ProductoController::class, 'update']);
    Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
    Route::post('/productos/{id}/restore', [ProductoController::class, 'restaurar']);
    Route::patch('/productos/{id}/estado', [ProductoController::class, 'updateEstado']);
    Route::post('/productos/{id}/imagen', [ProductoController::class, 'uploadImagen']);
});

// 4. Rutas del Módulo de Gestión de Proveedores
Route::prefix('proveedores')->group(function () {
    Route::get('/', [ProveedorController::class, 'index']);
    Route::get('/resumen', [ProveedorController::class, 'resumen']);
    Route::get('/cumplimiento', [ProveedorController::class, 'cumplimiento']);
    Route::get('/ranking-cumplimiento', [ProveedorController::class, 'rankingCumplimiento']);
    Route::get('/buscar', [ProveedorController::class, 'buscar']);
    Route::post('/', [ProveedorController::class, 'store']);
    Route::get('/{id}', [ProveedorController::class, 'show']);
    Route::get('/{id}/productos', [ProveedorController::class, 'productos']);
    Route::put('/{id}', [ProveedorController::class, 'update']);
    Route::delete('/{id}', [ProveedorController::class, 'destroy']);
    Route::patch('/{id}/estado', [ProveedorController::class, 'updateEstado']);
    Route::post('/{id}/restore', [ProveedorController::class, 'restore']);
});

// 5. Rutas del Módulo de Categorías de Producto
Route::prefix('categorias')->group(function () {
    Route::get('/', [CategoriaProductoController::class, 'index']);
    Route::post('/', [CategoriaProductoController::class, 'store']);
    Route::get('/{id}', [CategoriaProductoController::class, 'show']);
    Route::put('/{id}', [CategoriaProductoController::class, 'update']);
    Route::delete('/{id}', [CategoriaProductoController::class, 'destroy']);
    Route::patch('/{id}/estado', [CategoriaProductoController::class, 'updateEstado']);
    Route::post('/{id}/restore', [CategoriaProductoController::class, 'restore']);
});

// 6. Rutas del Módulo de Unidades de Medida
Route::prefix('unidades-medida')->group(function () {
    Route::get('/', [UnidadesMedidaController::class, 'index']);
    Route::post('/', [UnidadesMedidaController::class, 'store']);
    Route::get('/{id}', [UnidadesMedidaController::class, 'show']);
    Route::put('/{id}', [UnidadesMedidaController::class, 'update']);
    Route::delete('/{id}', [UnidadesMedidaController::class, 'destroy']);
    Route::patch('/{id}/estado', [UnidadesMedidaController::class, 'updateEstado']);
    Route::post('/{id}/restore', [UnidadesMedidaController::class, 'restore']);
});

// 7. Rutas del Módulo de Canales de Pedido
Route::prefix('canales-pedido')->group(function () {
    Route::get('/', [CanalPedidoController::class, 'index']);
    Route::post('/', [CanalPedidoController::class, 'store']);
    Route::get('/{id}', [CanalPedidoController::class, 'show']);
    Route::put('/{id}', [CanalPedidoController::class, 'update']);
    Route::delete('/{id}', [CanalPedidoController::class, 'destroy']);
    Route::patch('/{id}/estado', [CanalPedidoController::class, 'updateEstado']);
    Route::post('/{id}/restore', [CanalPedidoController::class, 'restore']);
});

// 7.1. Rutas del Módulo de Zonas de Delivery (Catálogos)
$registerZonaRoutes = function () {
    Route::get('/', [DeliveryController::class, 'index']);
    Route::get('/activas', [DeliveryController::class, 'activas']);
    Route::post('/', [DeliveryController::class, 'store']);
    Route::get('/{id}', [DeliveryController::class, 'show']);
    Route::put('/{id}', [DeliveryController::class, 'update']);
    Route::patch('/{id}/estado', [DeliveryController::class, 'cambiarEstado']);
};
Route::prefix('zonas-delivery')->group($registerZonaRoutes);
Route::prefix('catalogos/zonas-delivery')->group($registerZonaRoutes);

// 8. Rutas del Módulo de Clientes
Route::prefix('clientes')->group(function () {
    Route::get('/', [ClienteController::class, 'index']);
    Route::get('/resumen', [ClienteController::class, 'resumen']);
    Route::get('/inactivos', [ClienteController::class, 'inactivos']);
    Route::get('/buscar', [ClienteController::class, 'buscar']);
    Route::post('/sincronizar-compras', [ClienteController::class, 'sincronizarCompras']);
    Route::post('/', [ClienteController::class, 'store']);
    Route::get('/{id}', [ClienteController::class, 'show']);
    Route::get('/{id}/pedidos', [ClienteController::class, 'historialPedidos']);
    Route::put('/{id}', [ClienteController::class, 'update']);
    Route::delete('/{id}', [ClienteController::class, 'destroy']);
    Route::patch('/{id}/estado', [ClienteController::class, 'updateEstado']);
    Route::post('/{id}/restore', [ClienteController::class, 'restore']);
});

// 9. Rutas de Roles y Seguridad
Route::prefix('seguridad')->group(function () {
    // Roles de Usuario
    Route::get('/roles', [RolesSeguridadController::class, 'roles']);
    Route::post('/roles', [RolesSeguridadController::class, 'storeRol']);
    Route::put('/roles/{id}', [RolesSeguridadController::class, 'updateRol']);
    Route::delete('/roles/{id}', [RolesSeguridadController::class, 'destroyRol']);
    Route::post('/roles/{id}/restore', [RolesSeguridadController::class, 'restoreRol']);

    // Cargos de Personal
    Route::get('/cargos', [RolesSeguridadController::class, 'cargos']);
    Route::post('/cargos', [RolesSeguridadController::class, 'storeCargo']);
    Route::put('/cargos/{id}', [RolesSeguridadController::class, 'updateCargo']);
    Route::delete('/cargos/{id}', [RolesSeguridadController::class, 'destroyCargo']);
    Route::post('/cargos/{id}/restore', [RolesSeguridadController::class, 'restoreCargo']);
});

// 10. Rutas del Módulo de Inteligencia Artificial (Google Gemini)
Route::prefix('ai')->group(function () {
    Route::post('/generate', [AiController::class, 'generate']);
    Route::post('/chat', [AiController::class, 'chat']);
    Route::match(['get', 'post'], '/chat-stream', [AiController::class, 'chatStream']);
    Route::post('/transcribe-audio', [AiController::class, 'transcribeAudio'])->middleware('throttle:30,1');
    Route::get('/voice-notes/{uuid}', [AiController::class, 'getVoiceNote']);
    Route::get('/voice-status', [AiController::class, 'voiceStatus']);
    Route::post('/tool', [AiController::class, 'executeTool']); // Debug: ejecutar tool directamente
    Route::get('/status', [AiController::class, 'status']);
    Route::get('/tools', [AiController::class, 'tools']);
    Route::get('/health', [AiController::class, 'health'])->middleware('throttle:10,1');
    Route::post('/feedback', [AiController::class, 'recordFeedback']);
    Route::get('/feedback/stats', [AiController::class, 'feedbackStats']);
});

// 11. Rutas del Módulo de Ubicaciones de Almacén (producto_ubi)
Route::prefix('ubicaciones')->group(function () {
    Route::get('/', [ProductoUbiController::class, 'index']);
    Route::post('/', [ProductoUbiController::class, 'store']);
    Route::get('/{id}', [ProductoUbiController::class, 'show']);
    Route::put('/{id}', [ProductoUbiController::class, 'update']);
    Route::delete('/{id}', [ProductoUbiController::class, 'destroy']);
    Route::patch('/{id}/estado', [ProductoUbiController::class, 'updateEstado']);
    Route::post('/{id}/restore', [ProductoUbiController::class, 'restore']);
});

// 12. Rutas de Consulta RUC SUNAT (Integración Python / Clientes y Proveedores)
Route::prefix('sunat')->group(function () {
    Route::get('/ruc/{ruc}', [SunatController::class, 'consultarRuc']);
});

// 13. Rutas del Módulo de Órdenes de Compra (Orden_Compra & Detalle_Orden_Compra)
Route::prefix('ordenes-compra')->group(function () {
    Route::get('/siguiente-codigo', [OrdenCompraController::class, 'siguienteCodigo']);
    Route::get('/estados', [OrdenCompraController::class, 'estados']);
    Route::get('/reporte/pdf', [OrdenCompraController::class, 'reportePdf']);
    Route::get('/', [OrdenCompraController::class, 'index']);
    Route::post('/', [OrdenCompraController::class, 'store']);
    Route::post('/compra-rapida', [OrdenCompraController::class, 'compraRapida']);
    Route::get('/{id}', [OrdenCompraController::class, 'show']);
    Route::put('/{id}', [OrdenCompraController::class, 'update']);
    Route::delete('/{id}', [OrdenCompraController::class, 'destroy']);
    Route::post('/{id}/restore', [OrdenCompraController::class, 'restore']);
    Route::patch('/{id}/proveedor', [OrdenCompraController::class, 'asignarProveedor']);
    Route::patch('/{id}/estado', [OrdenCompraController::class, 'cambiarEstado']);
    Route::post('/{id}/iniciar-recepcion', [OrdenCompraController::class, 'iniciarRecepcion']);
    Route::post('/{id}/recepcionar-item', [OrdenCompraController::class, 'recepcionarItem']);
    Route::post('/{id}/rechazar-item', [OrdenCompraController::class, 'rechazarItem']);
    Route::post('/{id}/cerrar-recepcion', [OrdenCompraController::class, 'cerrarRecepcion']);
    Route::post('/{id}/anular-recepcion', [OrdenCompraController::class, 'anularRecepcion']);
    Route::get('/{id}/historial-recepcion', [OrdenCompraController::class, 'historialRecepcion']);
    Route::match(['get', 'post'], '/{id}/whatsapp', [OrdenCompraController::class, 'whatsapp']);
    Route::get('/{id}/mensaje-whatsapp', [OrdenCompraController::class, 'mensajeWhatsapp']);
    Route::post('/{id}/enviar-whatsapp', [OrdenCompraController::class, 'enviarWhatsapp']);
    Route::get('/{id}/pdf', [OrdenCompraController::class, 'pdf']);
    Route::get('/{id}/pdf-url', [OrdenCompraController::class, 'pdfUrl']);
});

// Rutas de Auditoría y Control de Roturas de Stock (Indicador PRS)
Route::prefix('roturas-stock')->group(function () {
    Route::get('/', [RoturaStockController::class, 'index']);
    Route::get('/resumen', [RoturaStockController::class, 'resumen']);
    Route::post('/intento', [RoturaStockController::class, 'intento']);
    Route::post('/confirmar', [RoturaStockController::class, 'confirmar']);
});

// 14. Alias Directo para Ajustes de Inventario y Mermas (/api/ajustes)
Route::prefix('ajustes')->group(function () {
    Route::get('/tipos', [AjusteInventarioController::class, 'tipos']);
    Route::get('/resumen', [AjusteInventarioController::class, 'resumen']);
    Route::get('/impacto-financiero', [AjusteInventarioController::class, 'impactoFinanciero']);
    Route::get('/reporte/pdf', [AjusteInventarioController::class, 'reportePdf']);
    Route::get('/reporte/excel', [AjusteInventarioController::class, 'reporteExcel']);
    Route::get('/', [AjusteInventarioController::class, 'index']);
    Route::post('/', [AjusteInventarioController::class, 'store']);
    Route::get('/{id}', [AjusteInventarioController::class, 'show']);
    Route::delete('/{id}', [AjusteInventarioController::class, 'destroy']);
    Route::post('/{id}/restore', [AjusteInventarioController::class, 'restore']);
});

// 15. Rutas de Notificaciones en Tiempo Real (SSE + REST con tarjeta UI y modo silencioso)
Route::prefix('notificaciones')->group(function () {
    Route::get('/stream', [NotificacionController::class, 'stream']);
    Route::get('/', [NotificacionController::class, 'index']);
    Route::patch('/{id}/leer', [NotificacionController::class, 'marcarLeida']);
    Route::post('/marcar-todas', [NotificacionController::class, 'marcarTodas']);
    Route::delete('/{id}', [NotificacionController::class, 'destroy']);
    Route::delete('/', [NotificacionController::class, 'destroyAll']);
    Route::post('/test', [NotificacionController::class, 'test']);
});

// 16. Rutas de Catálogo de Productos y Precios (Stock Bajo, Autocompletado y Precios)
Route::prefix('productos')->group(function () {
    Route::get('/stock-bajo', [ProductoController::class, 'stockBajo']);
    Route::get('/buscar', [ProductoController::class, 'buscar']);
    Route::get('/{id}/ultimo-precio', [ProductoController::class, 'ultimoPrecio']);
    Route::get('/', [ProductoController::class, 'index']);
    Route::get('/{id}', [ProductoController::class, 'show']);
});

// 17. Rutas de Alertas de Stock Bajo y Reposición Inmediata
Route::prefix('alertas')->group(function () {
    Route::get('/stock-bajo', [ProductoController::class, 'alertasStockBajo']);
    Route::patch('/{id}/leer', [NotificacionController::class, 'marcarLeida']);
});

// 18. Rutas del Módulo de Órdenes de Cliente (Pedidos de Venta con Kárdex y Stock)
Route::prefix('pedidos')->group(function () {
    Route::get('/stats', [PedidoController::class, 'stats']);
    Route::get('/daily', [PedidoController::class, 'daily']);
    Route::post('/check-timeout', [PedidoController::class, 'checkTimeout']);
    Route::post('/notify-expiring', [PedidoController::class, 'notifyExpiring']);
    Route::get('/stock', [PedidoController::class, 'stock']);
    Route::get('/stock/{id}', [PedidoController::class, 'stockProducto']);
    Route::get('/', [PedidoController::class, 'index']);
    Route::post('/', [PedidoController::class, 'store']);
    Route::get('/{id}', [PedidoController::class, 'show']);
    Route::put('/{id}', [PedidoController::class, 'update']);
    Route::delete('/{id}', [PedidoController::class, 'destroy']);
    Route::patch('/{id}/complete', [PedidoController::class, 'complete']);
    Route::patch('/{id}/cancel', [PedidoController::class, 'cancel']);
    Route::patch('/{id}/status', [PedidoController::class, 'status']);
});

// 19. Alias en Inglés para Órdenes de Cliente (/api/orders/client)
Route::prefix('orders/client')->group(function () {
    Route::get('/stats', [PedidoController::class, 'stats']);
    Route::get('/daily', [PedidoController::class, 'daily']);
    Route::post('/check-timeout', [PedidoController::class, 'checkTimeout']);
    Route::post('/notify-expiring', [PedidoController::class, 'notifyExpiring']);
    Route::get('/', [PedidoController::class, 'index']);
    Route::post('/', [PedidoController::class, 'store']);
    Route::get('/{id}', [PedidoController::class, 'show']);
    Route::put('/{id}', [PedidoController::class, 'update']);
    Route::delete('/{id}', [PedidoController::class, 'destroy']);
    Route::patch('/{id}/complete', [PedidoController::class, 'complete']);
    Route::patch('/{id}/cancel', [PedidoController::class, 'cancel']);
    Route::patch('/{id}/status', [PedidoController::class, 'status']);
});

// 20. Rutas de Disponibilidad de Stock (/api/products/stock y /api/productos/stock)
Route::prefix('products/stock')->group(function () {
    Route::get('/', [PedidoController::class, 'stock']);
    Route::get('/{id}', [PedidoController::class, 'stockProducto']);
});
Route::get('productos/stock', [PedidoController::class, 'stock']);
Route::get('productos/{id}/stock', [PedidoController::class, 'stockProducto']);

// 21. Alias y Rutas de Clientes (/api/clients y /api/clients/{id}/orders)
Route::prefix('clients')->group(function () {
    Route::get('/', [ClienteController::class, 'index']);
    Route::post('/', [ClienteController::class, 'store']);
    Route::get('/{id}', [ClienteController::class, 'show']);
    Route::get('/{id}/orders', [PedidoController::class, 'porCliente']);
});
Route::get('clientes/{id}/orders', [PedidoController::class, 'porCliente']);

// 22. Rutas del Módulo de Datos de la Empresa (Comercial Valencia)
Route::prefix('empresa')->group(function () {
    Route::get('/', [EmpresaController::class, 'show']);
    Route::put('/', [EmpresaController::class, 'update']);
    Route::post('/', [EmpresaController::class, 'update']);
    Route::post('/sincronizar-sunat', [EmpresaController::class, 'sincronizarSunat']);
});

// 23. Módulo de Sugerencias de Reabastecimiento / Predicciones de Compra
// GET /api/ai/predicciones-compra
// GET /api/ai/predicciones-compra/pdf/categoria/{categoriaId}
// GET /api/ai/predicciones-compra/pdf/general
// GET /api/ai/predicciones-compra/pdf/producto/{productoId}
// GET /api/ai/predicciones-compra/csv
Route::prefix('ai/predicciones-compra')->group(function () {
    Route::get('/',                        [PurchasePredictionController::class, 'index']);
    Route::get('/pdf/categoria/{catId}',   [PurchasePredictionController::class, 'pdfCategoria']);
    Route::get('/pdf/general',             [PurchasePredictionController::class, 'pdfGeneral']);
    Route::get('/pdf/producto/{prodId}',   [PurchasePredictionController::class, 'pdfProducto']);
    Route::get('/csv',                     [PurchasePredictionController::class, 'csv']);
});

