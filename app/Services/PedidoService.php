<?php

namespace App\Services;

use App\Models\AuditoriaRoturaStock;
use App\Models\CanalPedido;
use App\Models\Cliente;
use App\Models\DetallePedidoProductos;
use App\Models\MovimientoProducto;
use App\Models\Pedido;
use App\Models\PedidoHistorialCorreccion;
use App\Models\Producto;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    public function __construct(
        protected PedidoRepositoryInterface $pedidoRepository,
        protected InventarioService $inventarioService,
        protected NotificacionService $notificacionService
    ) {}

    /**
     * Listar pedidos de clientes con filtros y paginación.
     */
    public function listarPedidos(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->pedidoRepository->getAll($filters, $perPage);
    }

    /**
     * Obtener un pedido por ID con detalles y relaciones.
     */
    public function obtenerPedidoPorId(string $id, bool $includeDeleted = false): Pedido
    {
        $pedido = $this->pedidoRepository->findById($id, $includeDeleted);

        if (!$pedido) {
            throw new Exception("Pedido con ID '{$id}' no encontrado.");
        }

        return $pedido;
    }

    /**
     * Crear un nuevo pedido de cliente.
     * REGLA DE NEGOCIO CRÍTICA:
     * - Valida existencia de stock suficiente.
     * - Resta automáticamente el stock físico en Producto.
     * - Registra el movimiento de salida 'S' en el Kárdex (Movimiento_producto).
     * - Registra el pedido en estado 'P' (Pendiente).
     */
    public function crearPedido(array $datos): Pedido
    {
        return DB::transaction(function () use ($datos) {
            // 1. Validar cliente
            $clienteId = !empty($datos['cliente_id']) 
                ? trim($datos['cliente_id']) 
                : (!empty($datos['Pedido_ClienteId']) ? trim($datos['Pedido_ClienteId']) : null);

            $cliente = Cliente::where('ClienteId', $clienteId)
                ->where('ClienteEliminado', 'N')
                ->first();

            if (!$cliente) {
                throw ValidationException::withMessages([
                    'cliente_id' => "El cliente con ID '{$clienteId}' no existe o se encuentra inactivo.",
                ]);
            }

            // 2. Resolver canal de pedido (por defecto Tienda Presencial CNL-00001)
            $canalId = !empty($datos['canal_id']) 
                ? trim($datos['canal_id']) 
                : (!empty($datos['Pedido_canal_pedidoId']) ? trim($datos['Pedido_canal_pedidoId']) : config('orders.default_canal_id', 'CNL-00001'));

            $canal = CanalPedido::where('Canal_pedidoId', $canalId)
                ->where('Canal_pedidoEliminado', 'N')
                ->first();

            if (!$canal) {
                // Fallback seguro a CNL-00001
                $canalId = 'CNL-00001';
            }

            // 3. Validar detalles de productos
            $detallesInput = $datos['detalles'] ?? [];
            if (empty($detallesInput) || !is_array($detallesInput)) {
                throw ValidationException::withMessages([
                    'detalles' => 'El pedido debe contener al menos un producto en el detalle.',
                ]);
            }

            $pedidoId = $this->pedidoRepository->generateNextId();
            $subtotalGeneral = 0.00;
            $itemsParaProcesar = [];

            // Primera pasada: Bloqueo y validación de stock físico con Factor de Conversión
            foreach ($detallesInput as $idx => $item) {
                $productoId = trim($item['producto_id'] ?? '');
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $unidadId = !empty($item['unidad_id']) ? trim($item['unidad_id']) : (!empty($item['unidades_medidaId']) ? trim($item['unidades_medidaId']) : null);
                $factor = !empty($item['factor_conversion']) ? (float) $item['factor_conversion'] : 0.0;

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        "detalles.{$idx}.cantidad" => "La cantidad para el producto '{$productoId}' debe ser mayor a 0.",
                    ]);
                }

                $producto = Producto::where('ProductoId', $productoId)
                    ->where('ProductoEliminado', 'N')
                    ->lockForUpdate()
                    ->first();

                if (!$producto) {
                    throw ValidationException::withMessages([
                        "detalles.{$idx}.producto_id" => "El producto con ID '{$productoId}' no existe o está inactivo.",
                    ]);
                }

                // Resolver factor de conversión desde Detalle_Producto_medida si existe la relación
                $detMedida = null;
                if ($unidadId) {
                    $detMedida = \App\Models\DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $productoId)
                        ->where('Detalle_Producto_medida_unidades_medidaId', $unidadId)
                        ->where('Detalle_Producto_medidaEliminado', 'N')
                        ->first();
                    if ($detMedida) {
                        $factor = (float) $detMedida->Detalle_Producto_medida_factor_conversion;
                    }
                }
                if ($factor <= 0) {
                    $factor = 1.0;
                }
                if (!$unidadId) {
                    $unidadId = 'UND-00001';
                }

                // Cantidad total requerida en unidades base de inventario (ej. 10 paquetes * 12 = 120 unidades)
                $cantidadBase = round($cantidad * $factor, 2);

                $stockFisico = max(0.0, (float) $producto->ProductoStockActual);

                if ($cantidadBase > $stockFisico) {
                    try {
                        AuditoriaRoturaStock::create([
                            'pedido_id'                  => null,
                            'cantidad_solicitada'        => $cantidadBase,
                            'cantidad_disponible_fisica' => $stockFisico,
                            'deficit_unidades'           => round($cantidadBase - $stockFisico, 2),
                            'tipo_rotura'                => 'VENTA_PERDIDA',
                            'rompe_stock_seguridad'      => true,
                            'categoria_id'               => $producto->Producto_Categoria_ProductoId,
                            'usuario'                    => $datos['usuario_registro'] ?? auth()->user()?->UsuarioUsername ?? 'ADMIN',
                            'created_at'                 => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    throw ValidationException::withMessages([
                        'stock' => "Stock insuficiente para '{$producto->ProductoNombre}'. Solicitado: {$cantidad} ({$cantidadBase} unidades base), Stock Físico disponible: {$stockFisico}.",
                    ]);
                }

                $cantFisica = $cantidadBase;

                // Determinar precio unitario de venta (precio por la presentación vendida)
                $precioUnitario = isset($item['precio_unitario']) && (float)$item['precio_unitario'] > 0
                    ? (float)$item['precio_unitario']
                    : (($detMedida && (float)$detMedida->Detalle_Producto_medida_precio_venta > 0)
                        ? (float)$detMedida->Detalle_Producto_medida_precio_venta
                        : ((float)($producto->ProductoPrecioVenta ?? 0.00)));

                $subtotalItem = round($cantidad * $precioUnitario, 2);
                $subtotalGeneral += $subtotalItem;

                $itemsParaProcesar[] = [
                    'producto' => $producto,
                    'producto_id' => $productoId,
                    'unidad_id' => $unidadId,
                    'factor_conversion' => $factor,
                    'cantidad' => $cantidad,
                    'cantidad_base' => $cantidadBase,
                    'cant_fisica' => $cantFisica,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotalItem,
                ];
            }

            // 4. Calcular IGV (18%) y Total
            $igvGeneral = round($subtotalGeneral * 0.18, 2);
            $totalGeneral = round($subtotalGeneral + $igvGeneral, 2);

            // 5. Crear el encabezado del Pedido con Telemetría e Indicadores de Tesis
            $acuerdoComercial = !empty($datos['acuerdo_comercial']) 
                ? trim($datos['acuerdo_comercial']) 
                : (!empty($datos['PedidoAcuerdo_Comercial']) ? trim($datos['PedidoAcuerdo_Comercial']) : 'Contado');

            $usuarioRegistro = $datos['usuario_registro'] 
                ?? auth()->user()?->UsuarioUsername 
                ?? 'ADMIN';

            $origenIA = 'N';
            if (isset($datos['origen_ia'])) {
                $origenIA = filter_var($datos['origen_ia'], FILTER_VALIDATE_BOOLEAN) ? 'S' : 'N';
            } elseif (!empty($datos['PedidoOrigenIA'])) {
                $origenIA = in_array(strtoupper((string) $datos['PedidoOrigenIA']), ['S', '1', 'TRUE']) ? 'S' : 'N';
            }

            // Telemetría para Indicador 4 (TBPP)
            $esRecurrente = DB::table('Pedido')
                ->where('Pedido_ClienteId', $clienteId)
                ->where('PedidoEliminado', 'N')
                ->whereIn('PedidoEstado_pedido', ['C', 'P'])
                ->exists();

            $telemetria = $datos['telemetria'] ?? [];
            $tipoCliente = !empty($datos['tipo_cliente_registro']) 
                ? $datos['tipo_cliente_registro'] 
                : (!empty($telemetria['tipo_cliente']) ? $telemetria['tipo_cliente'] : ($esRecurrente ? 'RECURRENTE' : 'NUEVO'));

            $tiempoRegistroSeg = isset($datos['tiempo_registro_segundos']) 
                ? (int)$datos['tiempo_registro_segundos'] 
                : (isset($telemetria['tiempo_registro_seg']) ? (int)$telemetria['tiempo_registro_seg'] : 0);

            $tiempoEfectivoSeg = isset($datos['tiempo_activo_segundos']) 
                ? (int)$datos['tiempo_activo_segundos'] 
                : (isset($telemetria['tiempo_efectivo_seg']) ? (int)$telemetria['tiempo_efectivo_seg'] : $tiempoRegistroSeg);

            $tiempoSelClienteMs = isset($datos['tiempo_seleccion_cliente_ms']) 
                ? (int)$datos['tiempo_seleccion_cliente_ms'] 
                : (isset($telemetria['tiempo_seleccion_cliente_ms']) ? (int)$telemetria['tiempo_seleccion_cliente_ms'] : 0);

            $tiempoCargaProdMs = isset($datos['tiempo_carga_productos_ms']) 
                ? (int)$datos['tiempo_carga_productos_ms'] 
                : (isset($telemetria['tiempo_carga_productos_ms']) ? (int)$telemetria['tiempo_carga_productos_ms'] : 0);

            $tiempoValStockMs = isset($datos['tiempo_validacion_stock_ms']) 
                ? (int)$datos['tiempo_validacion_stock_ms'] 
                : (isset($telemetria['tiempo_validacion_stock_ms']) ? (int)$telemetria['tiempo_validacion_stock_ms'] : 0);

            $tiempoConfPagoMs = isset($datos['tiempo_confirmacion_pago_ms']) 
                ? (int)$datos['tiempo_confirmacion_pago_ms'] 
                : (isset($telemetria['tiempo_confirmacion_pago_ms']) ? (int)$telemetria['tiempo_confirmacion_pago_ms'] : 0);

            $dispositivo = $datos['dispositivo_registro'] ?? $telemetria['dispositivo'] ?? 'DESKTOP';
            $intentosCorreccion = isset($datos['intentos_correccion_formulario']) 
                ? (int)$datos['intentos_correccion_formulario'] 
                : (isset($telemetria['intentos_correccion']) ? (int)$telemetria['intentos_correccion'] : 0);

            $scoreConfianzaIA = isset($datos['origen_ia_confianza']) 
                ? (float)$datos['origen_ia_confianza'] 
                : (isset($telemetria['score_confianza_ia']) ? (float)$telemetria['score_confianza_ia'] : ($origenIA === 'S' ? 95.0 : null));

            $pedido = $this->pedidoRepository->create([
                'PedidoId' => $pedidoId,
                'PedidoFecha_pedido' => now(),
                'PedidoTotal' => $totalGeneral,
                'PedidoIgv' => $igvGeneral,
                'PedidoUsuarioRegistro' => $usuarioRegistro,
                'PedidoEstado_pedido' => 'P', // P = Pendiente
                'PedidoAcuerdo_Comercial' => $acuerdoComercial,
                'Pedido_ClienteId' => $clienteId,
                'Pedido_canal_pedidoId' => $canalId,
                'PedidoOrigenIA' => $origenIA,
                // KPI Telemetría y Despacho
                'PedidoEstadoDespacho'           => 'PENDIENTE',
                'PedidoFechaInicioPreparacion'   => now(),
                'PedidoTiempoRegistroSeg'        => $tiempoRegistroSeg,
                'PedidoTiempoEfectivoSeg'        => $tiempoEfectivoSeg,
                'PedidoTiempoSeleccionClienteMs' => $tiempoSelClienteMs,
                'PedidoTiempoCargaProductosMs'   => $tiempoCargaProdMs,
                'PedidoTiempoValidacionStockMs'  => $tiempoValStockMs,
                'PedidoTiempoConfirmacionPagoMs' => $tiempoConfPagoMs,
                'PedidoDispositivo'              => $dispositivo,
                'PedidoTipoCliente'              => $tipoCliente,
                'PedidoIntentosCorreccion'       => $intentosCorreccion,
                'PedidoScoreConfianzaIA'         => $scoreConfianzaIA,
            ]);

            // 6. Procesar cada detalle: Descontar stock e insertar Kárdex (Salida 'S')
            foreach ($itemsParaProcesar as $item) {
                $producto = $item['producto'];
                $cantidad = $item['cantidad'];
                $cantidadBase = $item['cantidad_base'];
                $cantFisica = $item['cant_fisica'];
                $unidadId = $item['unidad_id'];
                $factor = $item['factor_conversion'];
                $precioUnitario = $item['precio_unitario'];
                $subtotal = $item['subtotal'];

                // 1. Descontar stock físico en unidades base atómicamente si hubo porción física
                if ($cantFisica > 0) {
                    $producto->decrement('ProductoStockActual', $cantFisica);
                }
                // Asegurar que el stock físico no sea negativo
                if ((float) $producto->fresh()->ProductoStockActual < 0) {
                    $producto->update(['ProductoStockActual' => 0.00]);
                }
                $nuevoStockFisico = (float) $producto->fresh()->ProductoStockActual;

                // 2. Registrar salida en Kárdex (Movimiento_producto) con factor y unidad real
                $movimientoId = $this->inventarioService->getNextMovimientoId();
                $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

                MovimientoProducto::create(array_merge([
                    'Movimiento_productoId' => $movimientoId,
                    'Movimiento_productoCantidadPresentacion' => (string) $cantidad,
                    'Movimiento_productoDocumentoOperacionId' => $pedidoId,
                    'Movimiento_productoTipoMovimiento' => 'S', // S = Salida por Venta / Pedido
                    'Movimiento_productoCostoPrecioUnitario' => (string) $precioUnitario,
                    'Movimiento_productoCantidadEntrada' => '0',
                    'Movimiento_productoCantidadSalida' => (string) $cantFisica,
                    'Movimiento_productoCantidadSaldo' => (string) $nuevoStockFisico,
                    'Movimiento_productoFecha_Movimiento' => now(),
                    'Movimiento_producto_ProductoId' => $producto->ProductoId,
                    'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $unidadId,
                    'Movimiento_productoEliminado' => 'N',
                ], $auditMov));

                // 3. Registrar Detalle_Pedido_Productos con unidad, factor y desglose físico
                $auditDetalle = AuditHelper::getCreationAudit('Detalle_Pedido_Productos');
                DetallePedidoProductos::create(array_merge([
                    'Detalle_Pedido_Productos_PedidoId' => $pedidoId,
                    'Detalle_Pedido_Productos_ProductoId' => $producto->ProductoId,
                    'Detalle_Pedido_Productos_unidades_medidaId' => $unidadId,
                    'Detalle_Pedido_Productos_factor_conversion' => $factor,
                    'Detalle_Pedido_Productos_cantidad' => $cantidad,
                    'Detalle_Pedido_Productos_cantidad_base' => $cantidadBase,
                    'Detalle_Pedido_Productos_cantidad_fisica' => $cantFisica,
                    'Detalle_Pedido_Productos_precio_unitario_venta' => $precioUnitario,
                    'Detalle_Pedido_Productos_subtotal' => $subtotal,
                    'Detalle_Pedido_ProductosEliminado' => 'N',
                ], $auditDetalle));
            }

            return $pedido->fresh(['cliente', 'canalPedido', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Actualizar campos administrativos y agregar/modificar productos de un pedido (solo si está Pendiente).
     */
    public function actualizarPedido(string $id, array $datos): Pedido
    {
        return DB::transaction(function () use ($id, $datos) {
            $pedido = $this->obtenerPedidoPorId($id);

            if (!$pedido->estaPendiente()) {
                throw ValidationException::withMessages([
                    'estado' => "Solo se pueden editar pedidos en estado Pendiente. El pedido actual está '{$pedido->nombre_estado}'.",
                ]);
            }

            $camposActualizar = [];

            if (isset($datos['acuerdo_comercial']) || isset($datos['PedidoAcuerdo_Comercial'])) {
                $camposActualizar['PedidoAcuerdo_Comercial'] = $datos['acuerdo_comercial'] ?? $datos['PedidoAcuerdo_Comercial'];
            }

            if (isset($datos['canal_id']) || isset($datos['Pedido_canal_pedidoId'])) {
                $canalId = $datos['canal_id'] ?? $datos['Pedido_canal_pedidoId'];
                $canal = CanalPedido::where('Canal_pedidoId', $canalId)->where('Canal_pedidoEliminado', 'N')->first();
                if ($canal) {
                    $camposActualizar['Pedido_canal_pedidoId'] = $canalId;
                }
            }

            if (!empty($camposActualizar)) {
                $this->pedidoRepository->update($id, $camposActualizar);
            }

            // Auditoría en Historial de Correcciones para Indicador 2 (PEOR)
            $usuarioMod = auth()->user()?->UsuarioUsername ?? 'ADMIN';
            $motivoCorrec = $datos['motivo_correccion'] ?? $datos['motivo'] ?? 'Corrección de orden';

            foreach ($camposActualizar as $campo => $nuevoValor) {
                $valorAnterior = $pedido->{$campo} ?? '';
                if ((string)$valorAnterior !== (string)$nuevoValor) {
                    try {
                        PedidoHistorialCorreccion::create([
                            'pedido_id'        => $id,
                            'campo_modificado' => $campo,
                            'valor_anterior'   => (string)$valorAnterior,
                            'valor_nuevo'      => (string)$nuevoValor,
                            'motivo'           => $motivoCorrec,
                            'usuario'          => $usuarioMod,
                            'created_at'       => now(),
                        ]);
                    } catch (\Throwable $e) {}
                }
            }

            // Normalizar acciones de productos
            $acciones = $datos['acciones'] ?? [];
            if (!empty($datos['producto_id']) && !empty($datos['cantidad'])) {
                $acciones[] = [
                    'tipo'              => $datos['accion'] ?? $datos['tipo_accion'] ?? 'agregar',
                    'producto_id'       => $datos['producto_id'],
                    'cantidad'          => (float)$datos['cantidad'],
                    'unidad_id'         => $datos['unidad_id'] ?? null,
                    'factor_conversion' => $datos['factor_conversion'] ?? null,
                    'precio_unitario'   => $datos['precio_unitario'] ?? null,
                ];
            }

            if (!empty($acciones)) {
                foreach ($acciones as $acc) {
                    $tipo = strtolower(trim($acc['tipo'] ?? 'agregar'));
                    $prodId = trim($acc['producto_id'] ?? '');
                    $cant = (float)($acc['cantidad'] ?? 0);
                    $unidadId = $acc['unidad_id'] ?? null;
                    $factor = !empty($acc['factor_conversion']) ? (float)$acc['factor_conversion'] : null;
                    $precio = !empty($acc['precio_unitario']) ? (float)$acc['precio_unitario'] : null;

                    if ($tipo === 'agregar' && $cant > 0 && !empty($prodId)) {
                        $this->agregarProductoAlPedido($pedido, $prodId, $cant, $unidadId, $factor, $precio);
                    } elseif ($tipo === 'eliminar' && !empty($prodId)) {
                        $this->eliminarProductoDelPedido($pedido, $prodId);
                    }
                }

                try {
                    PedidoHistorialCorreccion::create([
                        'pedido_id'        => $id,
                        'campo_modificado' => 'DetalleProductos',
                        'valor_anterior'   => 'Líneas de producto previas',
                        'valor_nuevo'      => count($acciones) . ' acción(es) ejecutada(s)',
                        'motivo'           => $motivoCorrec,
                        'usuario'          => $usuarioMod,
                        'created_at'       => now(),
                    ]);
                } catch (\Throwable $e) {}

                $this->recalcularTotalesPedido($pedido);
            }

            // Actualizar métricas de corrección y error
            $tiempoCorreccionMin = $pedido->PedidoFechaCreacion ? max(1, (int)round(now()->diffInMinutes($pedido->PedidoFechaCreacion))) : 0;
            $intentos = (int)($pedido->PedidoIntentosCorreccion ?? 0) + 1;
            
            $camposKpi = [
                'PedidoTiempoCorreccionMin' => $tiempoCorreccionMin,
                'PedidoIntentosCorreccion'  => $intentos,
            ];

            if (!empty($datos['tiene_error']) && in_array(strtoupper((string)$datos['tiene_error']), ['S', '1', 'TRUE'])) {
                $camposKpi['PedidoTieneError'] = 'S';
                if (!empty($datos['tipo_error'])) {
                    $camposKpi['PedidoTipoError'] = $datos['tipo_error'];
                }
                if (!empty($datos['origen_error'])) {
                    $camposKpi['PedidoOrigenError'] = $datos['origen_error'];
                }
                if (!empty($datos['severidad_error']) || !empty($datos['gravedad_error'])) {
                    $camposKpi['PedidoGravedadError'] = $datos['gravedad_error'] ?? $datos['severidad_error'];
                }
                if (isset($datos['costo_error'])) {
                    $camposKpi['PedidoCostoError'] = (float)$datos['costo_error'];
                }
            }

            $this->pedidoRepository->update($id, $camposKpi);

            return $pedido->fresh(['cliente', 'canalPedido', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    protected function agregarProductoAlPedido(Pedido $pedido, string $prodId, float $cant, ?string $unidadId, ?float $factor, ?float $precio): void
    {
        $producto = Producto::where('ProductoId', $prodId)->where('ProductoEliminado', 'N')->lockForUpdate()->first();
        if (!$producto) {
            throw ValidationException::withMessages(['producto_id' => "El producto '{$prodId}' no existe o está inactivo."]);
        }

        $detMedida = null;
        if ($unidadId) {
            $detMedida = \App\Models\DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $prodId)
                ->where('Detalle_Producto_medida_unidades_medidaId', $unidadId)
                ->where('Detalle_Producto_medidaEliminado', 'N')
                ->first();
            if ($detMedida) {
                $factor = (float)$detMedida->Detalle_Producto_medida_factor_conversion;
            }
        }
        if (!$detMedida && $factor !== null && $factor > 1) {
            $detMedida = \App\Models\DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $prodId)
                ->where('Detalle_Producto_medida_factor_conversion', (int)$factor)
                ->where('Detalle_Producto_medidaEliminado', 'N')
                ->first();
            if ($detMedida) {
                $unidadId = $detMedida->Detalle_Producto_medida_unidades_medidaId;
            }
        }
        if (!$factor || $factor <= 0) $factor = 1.0;
        if (!$unidadId) $unidadId = 'UND-00001';

        $precioUnitario = ($precio !== null && $precio > 0)
            ? $precio
            : (($detMedida && (float)$detMedida->Detalle_Producto_medida_precio_venta > 0)
                ? (float)$detMedida->Detalle_Producto_medida_precio_venta
                : (float)($producto->ProductoPrecioVenta ?? 0.0));

        $cantidadBase = round($cant * $factor, 2);
        $stockFisico = max(0.0, (float)$producto->ProductoStockActual);
        $stockTotalDisp = $producto->stock_total_vendible;

        if ($cantidadBase > $stockFisico) {
            throw ValidationException::withMessages([
                'stock' => "Stock insuficiente para '{$producto->ProductoNombre}'. Requerido: {$cant} ({$cantidadBase} unidades base), Stock Físico disponible: {$stockFisico}.",
            ]);
        }

        $cantFisica = $cantidadBase;

        // Descontar inventario
        if ($cantFisica > 0) {
            $producto->decrement('ProductoStockActual', $cantFisica);
        }

        $nuevoStockFisico = (float)$producto->fresh()->ProductoStockActual;

        // Registrar Kárdex Salida
        $movimientoId = $this->inventarioService->getNextMovimientoId();
        $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');
        MovimientoProducto::create(array_merge([
            'Movimiento_productoId' => $movimientoId,
            'Movimiento_productoCantidadPresentacion' => (string)$cant,
            'Movimiento_productoDocumentoOperacionId' => $pedido->PedidoId,
            'Movimiento_productoTipoMovimiento' => 'S',
            'Movimiento_productoCostoPrecioUnitario' => (string)$precioUnitario,
            'Movimiento_productoCantidadEntrada' => '0',
            'Movimiento_productoCantidadSalida' => (string)$cantFisica,
            'Movimiento_productoCantidadSaldo' => (string)$nuevoStockFisico,
            'Movimiento_productoFecha_Movimiento' => now(),
            'Movimiento_producto_ProductoId' => $producto->ProductoId,
            'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $unidadId,
            'Movimiento_productoEliminado' => 'N',
        ], $auditMov));

        // Actualizar o crear detalle
        $detalleExistente = DetallePedidoProductos::where('Detalle_Pedido_Productos_PedidoId', $pedido->PedidoId)
            ->where('Detalle_Pedido_Productos_ProductoId', $prodId)
            ->where('Detalle_Pedido_Productos_unidades_medidaId', $unidadId)
            ->where('Detalle_Pedido_ProductosEliminado', 'N')
            ->first();

        if ($detalleExistente) {
            $nuevaCant = (float)$detalleExistente->Detalle_Pedido_Productos_cantidad + $cant;
            $nuevaCantBase = round($nuevaCant * $factor, 2);
            $nuevoSubtotal = round($nuevaCant * $precioUnitario, 2);
            $nuevaFisica = (float)$detalleExistente->Detalle_Pedido_Productos_cantidad_fisica + $cantFisica;

            $detalleExistente->update(array_merge([
                'Detalle_Pedido_Productos_cantidad' => $nuevaCant,
                'Detalle_Pedido_Productos_cantidad_base' => $nuevaCantBase,
                'Detalle_Pedido_Productos_cantidad_fisica' => $nuevaFisica,
                'Detalle_Pedido_Productos_subtotal' => $nuevoSubtotal,
            ], AuditHelper::getModificationAudit('Detalle_Pedido_Productos')));
        } else {
            $subtotal = round($cant * $precioUnitario, 2);
            $auditDetalle = AuditHelper::getCreationAudit('Detalle_Pedido_Productos');
            DetallePedidoProductos::create(array_merge([
                'Detalle_Pedido_Productos_PedidoId' => $pedido->PedidoId,
                'Detalle_Pedido_Productos_ProductoId' => $producto->ProductoId,
                'Detalle_Pedido_Productos_unidades_medidaId' => $unidadId,
                'Detalle_Pedido_Productos_factor_conversion' => $factor,
                'Detalle_Pedido_Productos_cantidad' => $cant,
                'Detalle_Pedido_Productos_cantidad_base' => $cantidadBase,
                'Detalle_Pedido_Productos_cantidad_fisica' => $cantFisica,
                'Detalle_Pedido_Productos_precio_unitario_venta' => $precioUnitario,
                'Detalle_Pedido_Productos_subtotal' => $subtotal,
                'Detalle_Pedido_ProductosEliminado' => 'N',
            ], $auditDetalle));
        }
    }

    protected function eliminarProductoDelPedido(Pedido $pedido, string $prodId): void
    {
        $detalles = DetallePedidoProductos::where('Detalle_Pedido_Productos_PedidoId', $pedido->PedidoId)
            ->where('Detalle_Pedido_Productos_ProductoId', $prodId)
            ->where('Detalle_Pedido_ProductosEliminado', 'N')
            ->get();

        foreach ($detalles as $det) {
            $cantFisica = (float)$det->Detalle_Pedido_Productos_cantidad_fisica;

            $producto = Producto::where('ProductoId', $det->Detalle_Pedido_Productos_ProductoId)->lockForUpdate()->first();
            if ($producto) {
                if ($cantFisica > 0) {
                    $producto->increment('ProductoStockActual', $cantFisica);
                }

                $nuevoSaldo = (float)$producto->fresh()->ProductoStockActual;

                // Registrar devolución en Kárdex
                $movimientoId = $this->inventarioService->getNextMovimientoId();
                MovimientoProducto::create(array_merge([
                    'Movimiento_productoId' => $movimientoId,
                    'Movimiento_productoCantidadPresentacion' => (string)$det->Detalle_Pedido_Productos_cantidad,
                    'Movimiento_productoDocumentoOperacionId' => $pedido->PedidoId,
                    'Movimiento_productoTipoMovimiento' => 'E',
                    'Movimiento_productoCostoPrecioUnitario' => (string)$det->Detalle_Pedido_Productos_precio_unitario_venta,
                    'Movimiento_productoCantidadEntrada' => (string)$cantFisica,
                    'Movimiento_productoCantidadSalida' => '0',
                    'Movimiento_productoCantidadSaldo' => (string)$nuevoSaldo,
                    'Movimiento_productoFecha_Movimiento' => now(),
                    'Movimiento_producto_ProductoId' => $producto->ProductoId,
                    'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $det->Detalle_Pedido_Productos_unidades_medidaId,
                    'Movimiento_productoEliminado' => 'N',
                ], AuditHelper::getCreationAudit('Movimiento_producto')));
            }

            $det->update(array_merge([
                'Detalle_Pedido_ProductosEliminado' => 'Y',
            ], AuditHelper::getDeletionAudit('Detalle_Pedido_Productos')));
        }
    }

    protected function recalcularTotalesPedido(Pedido $pedido): void
    {
        $subtotal = (float) DetallePedidoProductos::where('Detalle_Pedido_Productos_PedidoId', $pedido->PedidoId)
            ->where('Detalle_Pedido_ProductosEliminado', 'N')
            ->sum('Detalle_Pedido_Productos_subtotal');

        $igv = round($subtotal * 0.18, 2);
        $total = round($subtotal + $igv, 2);

        $pedido->update([
            'PedidoTotal' => $total,
            'PedidoIgv'   => $igv,
        ]);
    }

    /**
     * Marcar pedido como COMPLETADO (no modifica stock).
     */
    public function completarPedido(string $id, array $datos = []): Pedido
    {
        $pedido = $this->obtenerPedidoPorId($id);

        if ($pedido->estaCompletada()) {
            return $pedido;
        }

        if (!$pedido->estaPendiente()) {
            throw ValidationException::withMessages([
                'estado' => "Solo se pueden completar pedidos en estado Pendiente (P). El pedido {$id} está '{$pedido->nombre_estado}'.",
            ]);
        }

        $ahora = now();
        $estadoDespacho = $datos['estado_despacho'] ?? 'ENTREGADO_COMPLETO';
        $usuarioDespacho = $datos['usuario_despacho'] ?? auth()->user()?->UsuarioUsername ?? 'OPERARIO_CENTRAL';

        $pedido = $this->pedidoRepository->update($id, [
            'PedidoEstado_pedido'  => 'C',
            'PedidoEstadoDespacho' => $estadoDespacho,
            'PedidoFechaDespacho'  => $ahora,
            'PedidoUsuarioDespacho'=> $usuarioDespacho,
        ]);

        return $pedido;
    }

    /**
     * Cancelar pedido manualmente (Devuelve stock al inventario y Kárdex).
     */
    public function cancelarPedido(string $id, ?string $motivo = null, array $datos = []): Pedido
    {
        return DB::transaction(function () use ($id, $motivo, $datos) {
            $pedido = $this->obtenerPedidoPorId($id);

            if ($pedido->estaCancelada()) {
                return $pedido;
            }

            if (!$pedido->estaPendiente()) {
                throw ValidationException::withMessages([
                    'estado' => "Solo se pueden cancelar pedidos en estado Pendiente. El pedido {$id} está '{$pedido->nombre_estado}'.",
                ]);
            }

            // 1. Revertir el stock de cada producto y asentar contra-movimiento de entrada en Kárdex
            $pedido->loadMissing('detalles.producto');

            foreach ($pedido->detalles as $detalle) {
                $producto = $detalle->producto;
                $cantidad = (float) $detalle->Detalle_Pedido_Productos_cantidad;
                $cantFisica = (float) ($detalle->Detalle_Pedido_Productos_cantidad_fisica ?? $cantidad);

                if ($producto && $cantidad > 0) {
                    // 1. Revertir la porción física consumida
                    if ($cantFisica > 0) {
                        $producto->increment('ProductoStockActual', $cantFisica);
                    }

                    $nuevoStock = (float) $producto->fresh()->ProductoStockActual;
                    $unidadId = $detalle->Detalle_Pedido_Productos_unidades_medidaId ?? 'UND-00001';

                    // 2. Registrar contra-movimiento de entrada compensatoria en Kárdex
                    $movimientoId = $this->inventarioService->getNextMovimientoId();
                    $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

                    MovimientoProducto::create(array_merge([
                        'Movimiento_productoId' => $movimientoId,
                        'Movimiento_productoCantidadPresentacion' => (string) $cantidad,
                        'Movimiento_productoDocumentoOperacionId' => 'DEV-' . $pedido->PedidoId,
                        'Movimiento_productoTipoMovimiento' => 'E', // E = Entrada compensatoria
                        'Movimiento_productoCostoPrecioUnitario' => (string) $detalle->Detalle_Pedido_Productos_precio_unitario_venta,
                        'Movimiento_productoCantidadEntrada' => (string) $cantFisica,
                        'Movimiento_productoCantidadSalida' => '0',
                        'Movimiento_productoCantidadSaldo' => (string) $nuevoStock,
                        'Movimiento_productoFecha_Movimiento' => now(),
                        'Movimiento_producto_ProductoId' => $producto->ProductoId,
                        'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $unidadId,
                        'Movimiento_productoEliminado' => 'N',
                    ], $auditMov));
                }
            }

            // 2. Marcar pedido como cancelado (A) y guardar metadatos de KPI
            $esFaltaStock = str_contains(strtoupper((string)$motivo), 'STOCK');
            $causaFallo = $datos['causa_fallo_despacho'] ?? ($esFaltaStock ? 'FALTA_STOCK' : ($datos['causa_fallo'] ?? 'RECHAZO_CLIENTE'));
            $estadoDespacho = $datos['estado_despacho'] ?? ($esFaltaStock ? 'RECHAZADO' : 'DEVUELTO');
            $tieneError = $datos['tiene_error'] ?? ($esFaltaStock ? 'N' : 'S');
            $origenError = $datos['origen_error'] ?? ($esFaltaStock ? 'ERROR_DATOS_MAESTROS' : 'ERROR_HUMANO');
            $gravedad = $datos['gravedad_error'] ?? $datos['severidad_error'] ?? 'MODERADO';
            $costoError = isset($datos['costo_error']) ? (float)$datos['costo_error'] : 0.00;
            $momentoDeteccion = $datos['momento_deteccion_error'] ?? 'PRE_DESPACHO';
            $origenDeteccion = $datos['origen_deteccion_error'] ?? 'DETECTADO_HUMANO';

            $datosUpdate = [
                'PedidoEstado_pedido'         => 'A',
                'PedidoEstadoDespacho'        => $estadoDespacho,
                'PedidoCausaFalloDespacho'    => $causaFallo,
                'PedidoMotivoAnulacion'       => $motivo ?: $causaFallo,
                'PedidoTieneError'            => in_array(strtoupper((string)$tieneError), ['S', '1', 'TRUE']) ? 'S' : 'N',
                'PedidoTipoError'             => $origenError,
                'PedidoOrigenError'           => $origenError,
                'PedidoGravedadError'         => $gravedad,
                'PedidoCostoError'            => $costoError,
                'PedidoMomentoDeteccionError' => $momentoDeteccion,
                'PedidoOrigenDeteccionError'  => $origenDeteccion,
            ];

            if (!empty($motivo)) {
                $datosUpdate['PedidoAcuerdo_Comercial'] = substr(
                    trim(($pedido->PedidoAcuerdo_Comercial ? $pedido->PedidoAcuerdo_Comercial . ' | ' : '') . 'Cancelación: ' . $motivo),
                    0,
                    45
                );
            }

            $pedido = $this->pedidoRepository->update($id, $datosUpdate);

            // Si es falta de stock, auditar en auditoria_roturas_stock
            if ($esFaltaStock || $causaFallo === 'FALTA_STOCK') {
                foreach ($pedido->detalles as $det) {
                    try {
                        AuditoriaRoturaStock::create([
                            'pedido_id'                  => $pedido->PedidoId,
                            'cantidad_solicitada'        => (float) $det->Detalle_Pedido_Productos_cantidad,
                            'cantidad_disponible_fisica' => 0.00,
                            'deficit_unidades'           => (float) $det->Detalle_Pedido_Productos_cantidad,
                            'tipo_rotura'                => 'VENTA_PERDIDA',
                            'rompe_stock_seguridad'      => true,
                            'categoria_id'               => $det->producto?->Producto_Categoria_ProductoId,
                            'usuario'                    => auth()->user()?->UsuarioUsername ?? 'ADMIN',
                            'created_at'                 => now(),
                        ]);
                    } catch (\Throwable $e) {}
                }
            }

            return $pedido;
        });
    }

    /**
     * Cambiar estado genérico de la orden con validación y reversión de stock.
     */
    public function cambiarEstado(string $id, string $nuevoEstado, ?string $motivo = null, array $datos = []): Pedido
    {
        $estado = strtoupper(trim($nuevoEstado));

        // Mapeo de sinónimos
        if (in_array($estado, ['COMPLETADA', 'COMPLETADO', 'C'])) {
            return $this->completarPedido($id, $datos);
        }

        if (in_array($estado, ['CANCELADA', 'CANCELADO', 'ANULADA', 'ANULADO', 'A'])) {
            return $this->cancelarPedido($id, $motivo, $datos);
        }

        if ($estado === 'P' || $estado === 'PENDIENTE') {
            throw ValidationException::withMessages([
                'estado' => "No se puede retroceder una orden completada o cancelada a Pendiente.",
            ]);
        }

        throw ValidationException::withMessages([
            'estado' => "Estado '{$nuevoEstado}' no reconocido. Utilice 'C' (Completada) o 'A' (Cancelada).",
        ]);
    }

    /**
     * Eliminación lógica (solo si está pendiente). Reintegra stock y Kárdex.
     */
    public function eliminarPedido(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $pedido = $this->obtenerPedidoPorId($id);

            if (!$pedido->estaPendiente()) {
                throw ValidationException::withMessages([
                    'estado' => "Solo se pueden eliminar pedidos en estado Pendiente.",
                ]);
            }

            // Devolver stock
            $this->cancelarPedido($id, 'Eliminación lógica de pedido');

            return $this->pedidoRepository->delete($id);
        });
    }

    /**
     * Rutina de Timeout: Cancela automáticamente órdenes con más de X horas de antigüedad y devuelve stock.
     */
    public function cancelarPedidosPorTimeout(?int $horas = null): array
    {
        $horas = $horas ?? (int) config('orders.timeout_hours', 12);
        $expirados = $this->pedidoRepository->getPendingExpired($horas);

        $procesados = [];
        $totalItemsDevueltos = 0;

        foreach ($expirados as $pedido) {
            try {
                $this->cancelarPedido($pedido->PedidoId, "Expiración automática por inactividad (> {$horas} horas)");
                $procesados[] = $pedido->PedidoId;
                $totalItemsDevueltos += $pedido->detalles->count();
            } catch (Exception $e) {
                // Registrar log y continuar con el siguiente
            }
        }

        return [
            'total_cancelados' => count($procesados),
            'horas_limite' => $horas,
            'pedidos_cancelados' => $procesados,
            'total_items_devueltos' => $totalItemsDevueltos,
        ];
    }

    /**
     * Notificar órdenes que están próximas a expirar (< 2 horas restantes).
     */
    public function notificarPedidosPorExpirar(?int $horas = null, int $ventanaAlerta = 2): array
    {
        $timeoutHoras = $horas ?? (int) config('orders.timeout_hours', 12);
        $proximas = $this->pedidoRepository->getPendingExpiringSoon($timeoutHoras, $ventanaAlerta);

        $notificacionesCreadas = [];

        foreach ($proximas as $pedido) {
            $horasTranscurridas = round($pedido->PedidoFechaCreacion->diffInMinutes(now()) / 60, 1);
            $horasRestantes = max(0, round($timeoutHoras - $horasTranscurridas, 1));
            $clienteNombre = $pedido->cliente?->ClienteNombre ?? 'Cliente sin identificar';

            $notif = $this->notificacionService->crear([
                'titulo' => "⚠️ Orden {$pedido->PedidoId} por expirar",
                'mensaje' => "La orden {$pedido->PedidoId} ({$clienteNombre}) expira en {$horasRestantes} horas. Complete el cobro o confirme el pedido para no perder la reserva de stock.",
                'tipo' => 'warning',
                'badge_texto' => 'Por Expirar',
                'badge_tipo' => 'warning',
                'badge_color' => '#F59E0B',
                'referencia_id' => $pedido->PedidoId,
                'flujo_origen' => 'Órdenes de Cliente',
                'flujo_destino' => 'Ventas y Mostrador',
                'flujo_indicador' => "Resta: {$horasRestantes}h",
                'accion_texto' => 'Gestionar Pedido',
                'accion_url' => "/pedidos/{$pedido->PedidoId}",
            ]);

            $notificacionesCreadas[] = [
                'pedido_id' => $pedido->PedidoId,
                'cliente' => $clienteNombre,
                'horas_restantes' => $horasRestantes,
                'notificacion_id' => $notif->NotificacionId,
            ];
        }

        return [
            'total_notificados' => count($notificacionesCreadas),
            'timeout_horas' => $timeoutHoras,
            'ventana_alerta_horas' => $ventanaAlerta,
            'ordenes' => $notificacionesCreadas,
        ];
    }

    /**
     * Historial de órdenes de un cliente específico.
     */
    public function obtenerHistorialPorCliente(string $clienteId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->pedidoRepository->getByCliente($clienteId, $perPage);
    }

    /**
     * Consulta de stock físico, reservado y disponible para la venta.
     */
    public function obtenerStockProductos(?string $productoId = null, array $filtros = []): array
    {
        $query = Producto::query()
            ->where('ProductoEliminado', 'N');

        if (!empty($productoId)) {
            $query->where('ProductoId', $productoId);
        }

        if (!empty($filtros['search'])) {
            $search = trim($filtros['search']);
            $query->where(function ($q) use ($search) {
                $q->where('ProductoNombre', 'LIKE', "%{$search}%")
                  ->orWhere('ProductoCodigo', 'LIKE', "%{$search}%");
            });
        }

        $productos = $query->get();

        // Obtener stock reservado (suma de cantidades en pedidos con estado 'P')
        $reservas = DB::table('Detalle_Pedido_Productos')
            ->join('Pedido', 'Detalle_Pedido_Productos.Detalle_Pedido_Productos_PedidoId', '=', 'Pedido.PedidoId')
            ->where('Pedido.PedidoEstado_pedido', 'P')
            ->where('Pedido.PedidoEliminado', 'N')
            ->where('Detalle_Pedido_Productos.Detalle_Pedido_ProductosEliminado', 'N')
            ->select(
                'Detalle_Pedido_Productos.Detalle_Pedido_Productos_ProductoId as producto_id',
                DB::raw('SUM(Detalle_Pedido_Productos.Detalle_Pedido_Productos_cantidad) as total_reservado')
            )
            ->groupBy('Detalle_Pedido_Productos.Detalle_Pedido_Productos_ProductoId')
            ->pluck('total_reservado', 'producto_id');

        $resultado = [];

        foreach ($productos as $p) {
            $stockFisico = (float) $p->ProductoStockActual;
            $stockReservado = (float) ($reservas[$p->ProductoId] ?? 0);
            $stockDisponible = max(0, $stockFisico - $stockReservado);

            $resultado[] = [
                'producto_id' => $p->ProductoId,
                'codigo' => $p->ProductoCodigo,
                'nombre' => $p->ProductoNombre,
                'marca' => $p->ProductoMarca,
                'precio_venta' => (float) $p->ProductoPrecioVenta,
                'stock_fisico' => $stockFisico,
                'stock_reservado' => $stockReservado,
                'stock_disponible' => $stockDisponible,
                'tiene_stock' => $stockDisponible > 0,
            ];
        }

        if (!empty($productoId)) {
            return $resultado[0] ?? [];
        }

        return $resultado;
    }

    /**
     * Resumen estadístico de órdenes con filtros opcionales.
     */
    public function obtenerEstadisticas(array $filters = []): array
    {
        return $this->pedidoRepository->getStats($filters);
    }

    /**
     * Órdenes de la jornada actual.
     */
    public function obtenerPedidosDelDia(?string $fecha = null, int $perPage = 15)
    {
        $fecha = $fecha ? \Carbon\Carbon::parse($fecha)->toDateString() : now()->toDateString();
        return $this->pedidoRepository->getDaily($fecha, $perPage);
    }
}
