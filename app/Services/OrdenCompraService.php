<?php

namespace App\Services;

use App\Models\DetalleOrdenCompra;
use App\Models\MovimientoProducto;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use App\Models\Proveedor;
use App\Models\UnidadesMedida;
use App\Repositories\Contracts\OrdenCompraRepositoryInterface;
use App\Support\AuditHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdenCompraService
{
    public function __construct(
        protected OrdenCompraRepositoryInterface $ordenCompraRepository,
        protected InventarioService $inventarioService,
        protected NotificacionService $notificacionService,
        protected RoturaStockService $roturaStockService
    ) {}

    /**
     * Listar órdenes de compra con filtros y paginación.
     */
    public function listarOrdenes(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->ordenCompraRepository->getAll($filters, $perPage);
    }

    /**
     * Obtener una orden de compra por ID.
     */
    public function obtenerOrdenPorId(string $id, bool $includeDeleted = false): OrdenCompra
    {
        $orden = $this->ordenCompraRepository->findById($id, $includeDeleted);

        if (!$orden) {
            throw new Exception("Orden de Compra con ID '{$id}' no encontrada.");
        }

        return $orden;
    }

    /**
     * Obtener el próximo código correlativo sugerido (ej: OC-00001).
     */
    public function obtenerSiguienteCodigo(): string
    {
        return $this->ordenCompraRepository->generateNextId();
    }

    /**
     * Catálogo de estados permitidos para la orden de compra.
     * Módulo formal de compras con impacto oficial en Kárdex durante la recepción.
     */
    public function obtenerEstados(): array
    {
        return [
            ['codigo' => 'BORRADOR', 'nombre' => 'Borrador', 'descripcion' => 'Orden de compra creada pero no enviada al proveedor'],
            ['codigo' => 'ENVIADA', 'nombre' => 'Enviada', 'descripcion' => 'Orden enviada al proveedor, pendiente de confirmación de despacho'],
            ['codigo' => 'PENDIENTE_RECEPCION', 'nombre' => 'Pendiente de Recepción', 'descripcion' => 'Proveedor confirmó despacho; almacén en espera de la mercadería'],
            ['codigo' => 'EN_RECEPCION', 'nombre' => 'En Recepción', 'descripcion' => 'Logístico realizando conteo físico y recepción ítem por ítem en muelle'],
            ['codigo' => 'CERRADA', 'nombre' => 'Cerrada', 'descripcion' => 'Recepción finalizada con el 100% de productos pedidos entregados'],
            ['codigo' => 'CERRADA_CON_FALTANTE', 'nombre' => 'Cerrada con Faltante', 'descripcion' => 'Recepción finalizada con ítems no entregados o faltantes'],
            ['codigo' => 'ANULADA', 'nombre' => 'Anulada', 'descripcion' => 'Orden de compra cancelada o recepción revertida en Kárdex'],
            ['codigo' => 'CANCELADA_PROVEEDOR', 'nombre' => 'Cancelada por Proveedor', 'descripcion' => 'Proveedor no pudo atender o rechazó la orden de compra'],
        ];
    }

    /**
     * Registrar una nueva Orden de Compra con sus Detalles.
     */
    public function crearOrden(array $datos): OrdenCompra
    {
        return DB::transaction(function () use ($datos) {
            $proveedorId = !empty($datos['Orden_Compra_ProveedorId']) 
                ? trim($datos['Orden_Compra_ProveedorId']) 
                : (!empty($datos['proveedor_id']) ? trim($datos['proveedor_id']) : null);
            if (!empty($proveedorId)) {
                $proveedor = Proveedor::where('ProveedorId', $proveedorId)
                    ->where('ProveedorEliminado', 'N')
                    ->first();
                if (!$proveedor) {
                    throw ValidationException::withMessages([
                        'Orden_Compra_ProveedorId' => "El proveedor con ID '{$proveedorId}' no existe o se encuentra inactivo.",
                    ]);
                }
            }

            $detallesInput = $datos['detalles'] ?? [];
            if (empty($detallesInput) || !is_array($detallesInput)) {
                throw ValidationException::withMessages([
                    'detalles' => 'La orden de compra debe contener al menos un producto en el detalle.',
                ]);
            }

            // Validar estado inicial (Por defecto: EMITIDA)
            $rawEstado = strtoupper(trim($datos['Orden_CompraEstado'] ?? $datos['estado'] ?? 'EMITIDA'));
            $mapLegacy = [
                'P' => 'EMITIDA',
                'BORRADOR' => 'EMITIDA',
                'ENVIADA' => 'EMITIDA',
                'PENDIENTE_RECEPCION' => 'EMITIDA',
                'EN_RECEPCION' => 'RECEPCION_PARCIAL',
                'C' => 'CERRADA_CONFORME',
                'CERRADA' => 'CERRADA_CONFORME',
                'A' => 'ANULADA',
                'CANCELADA_PROVEEDOR' => 'ANULADA',
            ];
            $estado = $mapLegacy[$rawEstado] ?? $rawEstado;
            $estadosValidos = ['EMITIDA', 'RECEPCION_PARCIAL', 'CERRADA_CONFORME', 'CERRADA_CON_FALTANTE', 'ANULADA'];
            if (!in_array($estado, $estadosValidos, true)) {
                $estado = 'EMITIDA';
            }

            // Fecha estimada de llegada
            $fechaEstimada = $datos['Orden_CompraFechaEstimadaLlegada'] ?? $datos['fecha_entrega_estimada'] ?? $datos['fecha_estimada_llegada'] ?? null;
            if (empty($fechaEstimada)) {
                $fechaEstimada = now()->addDays(2)->toDateTimeString();
            }

            // Validar y calcular totales de detalles
            $subtotalCalculado = 0.00;
            $detallesProcesados = [];

            foreach ($detallesInput as $index => $item) {
                $productoId = $item['Detalle_ProductoId'] ?? $item['producto_id'] ?? null;
                $unidadId = $item['Detalle_UnidadMedidaId'] ?? $item['unidad_medida_id'] ?? null;
                $cantidad = (float) ($item['Detalle_Orden_CompraCantidad'] ?? $item['cantidad'] ?? 0);
                $precioUnitario = (float) ($item['Detalle_Orden_CompraPrecioUnitario'] ?? $item['precio_unitario'] ?? 0);

                if (empty($productoId)) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.Detalle_ProductoId" => "El producto es obligatorio en el detalle #{$index}.",
                    ]);
                }

                $producto = Producto::with('detalleProductoMedidas')
                    ->where('ProductoId', $productoId)
                    ->where('ProductoEliminado', 'N')
                    ->first();

                if (!$producto) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.Detalle_ProductoId" => "El producto '{$productoId}' no existe o está inactivo.",
                    ]);
                }

                if (empty($unidadId) || !$producto->detalleProductoMedidas->contains('Detalle_Producto_medida_unidades_medidaId', $unidadId)) {
                    $baseDetalle = $producto->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
                        ?? $producto->detalleProductoMedidas->first();
                    $unidadId = $baseDetalle?->Detalle_Producto_medida_unidades_medidaId ?? 'UND-00001';
                }

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.Detalle_Orden_CompraCantidad" => "La cantidad para '{$producto->ProductoNombre}' debe ser mayor a 0.",
                    ]);
                }

                if ($precioUnitario < 0) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.Detalle_Orden_CompraPrecioUnitario" => "El precio unitario no puede ser negativo.",
                    ]);
                }

                $itemSubtotal = round($cantidad * $precioUnitario, 2);
                $subtotalCalculado += $itemSubtotal;

                $detallesProcesados[] = [
                    'Detalle_ProductoId' => $productoId,
                    'Detalle_UnidadMedidaId' => $unidadId,
                    'Detalle_Orden_CompraCantidad' => $cantidad,
                    'Detalle_Orden_CompraPrecioUnitario' => $precioUnitario,
                    'Detalle_Orden_CompraSubtotal' => $itemSubtotal,
                    'Detalle_Orden_CompraCantidadRecibida' => 0.00,
                    'Detalle_Orden_CompraEntregado' => 'N',
                    'Detalle_Orden_CompraFueProductoNuevo' => 'N',
                ];
            }

            // Calcular IGV (18% estándar en Perú) y Total
            $subtotal = round($subtotalCalculado, 2);
            $igv = isset($datos['Orden_CompraIgv']) ? (float) $datos['Orden_CompraIgv'] : round($subtotal * 0.18, 2);
            $total = round($subtotal + $igv, 2);

            $fecha = !empty($datos['Orden_CompraFecha']) ? $datos['Orden_CompraFecha'] : now();
            $observacion = $datos['Orden_CompraObservacion'] ?? null;

            // 1. Crear Cabecera de Orden_Compra
            $ordenData = [
                'Orden_CompraFecha' => $fecha,
                'Orden_CompraSubtotal' => $subtotal,
                'Orden_CompraIgv' => $igv,
                'Orden_CompraTotal' => $total,
                'Orden_CompraEstado' => $estado,
                'Orden_CompraObservacion' => $observacion,
                'Orden_Compra_ProveedorId' => $proveedorId,
                'Orden_CompraFechaEstimadaLlegada' => $fechaEstimada ? \Carbon\Carbon::parse($fechaEstimada) : null,
                'Orden_CompraCerradaConFaltante' => 'N',
            ];

            if (!empty($datos['Orden_CompraId'])) {
                $ordenData['Orden_CompraId'] = $datos['Orden_CompraId'];
            }

            $orden = $this->ordenCompraRepository->create($ordenData);

            // 2. Crear Detalles de la Orden
            $auditDetalle = AuditHelper::getCreationAudit('Detalle_Orden_Compra');
            foreach ($detallesProcesados as $det) {
                $detalleData = array_merge($det, $auditDetalle, [
                    'Detalle_Orden_Compra_Orden_CompraId' => $orden->Orden_CompraId,
                    'Detalle_Orden_CompraEliminado' => 'N',
                ]);
                DetalleOrdenCompra::create($detalleData);
            }

            return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Asignar o actualizar el proveedor a una orden de compra pendiente.
     */
    public function asignarProveedor(string $id, string $proveedorId): OrdenCompra
    {
        $orden = $this->obtenerOrdenPorId($id);

        if ($orden->Orden_CompraEstado !== 'P') {
            throw ValidationException::withMessages([
                'Orden_CompraEstado' => "Solo se puede asignar proveedor a órdenes en estado Pendiente ('P'). Estado actual: '{$orden->Orden_CompraEstado}'.",
            ]);
        }

        $proveedor = Proveedor::where('ProveedorId', $proveedorId)
            ->where('ProveedorEliminado', 'N')
            ->first();

        if (!$proveedor) {
            throw ValidationException::withMessages([
                'proveedor_id' => "El proveedor con ID '{$proveedorId}' no existe o está inactivo.",
            ]);
        }

        $this->ordenCompraRepository->update($id, [
            'Orden_Compra_ProveedorId' => $proveedorId,
        ]);

        return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
    }

    /**
     * Actualizar una Orden de Compra (solo permitido si está en estado 'P').
     */
    public function actualizarOrden(string $id, array $datos): OrdenCompra
    {
        return DB::transaction(function () use ($id, $datos) {
            $orden = $this->obtenerOrdenPorId($id);

            if ($orden->Orden_CompraEstado !== 'P') {
                throw ValidationException::withMessages([
                    'Orden_CompraEstado' => "No se puede editar una orden en estado '{$orden->Orden_CompraEstado}'. Solo las órdenes Pendientes ('P') pueden modificarse.",
                ]);
            }

            $proveedorId = array_key_exists('Orden_Compra_ProveedorId', $datos) 
                ? $datos['Orden_Compra_ProveedorId'] 
                : (array_key_exists('proveedor_id', $datos) ? $datos['proveedor_id'] : $orden->Orden_Compra_ProveedorId);

            if (!empty($proveedorId)) {
                $proveedor = Proveedor::where('ProveedorId', $proveedorId)
                    ->where('ProveedorEliminado', 'N')
                    ->first();
                if (!$proveedor) {
                    throw ValidationException::withMessages([
                        'Orden_Compra_ProveedorId' => "El proveedor '{$proveedorId}' no existe o está inactivo.",
                    ]);
                }
            }

            $updateData = [
                'Orden_CompraObservacion' => $datos['Orden_CompraObservacion'] ?? $orden->Orden_CompraObservacion,
                'Orden_Compra_ProveedorId' => $proveedorId,
            ];

            if (!empty($datos['Orden_CompraFecha'])) {
                $updateData['Orden_CompraFecha'] = $datos['Orden_CompraFecha'];
            }

            // Si se envían nuevos detalles, reemplazarlos
            if (isset($datos['detalles']) && is_array($datos['detalles'])) {
                if (empty($datos['detalles'])) {
                    throw ValidationException::withMessages([
                        'detalles' => 'La orden de compra debe contener al menos un producto en el detalle.',
                    ]);
                }

                // Eliminar detalles previos
                DetalleOrdenCompra::where('Detalle_Orden_Compra_Orden_CompraId', $id)->delete();

                $subtotalCalculado = 0.00;
                $auditDetalle = AuditHelper::getCreationAudit('Detalle_Orden_Compra');

                foreach ($datos['detalles'] as $index => $item) {
                    $productoId = $item['Detalle_ProductoId'] ?? $item['producto_id'] ?? null;
                    $unidadId = $item['Detalle_UnidadMedidaId'] ?? $item['unidad_medida_id'] ?? 'UND-00001';
                    $cantidad = (float) ($item['Detalle_Orden_CompraCantidad'] ?? $item['cantidad'] ?? 0);
                    $precioUnitario = (float) ($item['Detalle_Orden_CompraPrecioUnitario'] ?? $item['precio_unitario'] ?? 0);

                    if ($cantidad <= 0 || $precioUnitario < 0) {
                        throw ValidationException::withMessages([
                            "detalles.{$index}" => 'Cantidad y precio unitario deben ser válidos y mayores a cero.',
                        ]);
                    }

                    $itemSubtotal = round($cantidad * $precioUnitario, 2);
                    $subtotalCalculado += $itemSubtotal;

                    DetalleOrdenCompra::create(array_merge($auditDetalle, [
                        'Detalle_Orden_Compra_Orden_CompraId' => $id,
                        'Detalle_ProductoId' => $productoId,
                        'Detalle_UnidadMedidaId' => $unidadId,
                        'Detalle_Orden_CompraCantidad' => $cantidad,
                        'Detalle_Orden_CompraPrecioUnitario' => $precioUnitario,
                        'Detalle_Orden_CompraSubtotal' => $itemSubtotal,
                        'Detalle_Orden_CompraEliminado' => 'N',
                    ]));
                }

                $subtotal = round($subtotalCalculado, 2);
                $igv = round($subtotal * 0.18, 2);
                $total = round($subtotal + $igv, 2);

                $updateData['Orden_CompraSubtotal'] = $subtotal;
                $updateData['Orden_CompraIgv'] = $igv;
                $updateData['Orden_CompraTotal'] = $total;
            }

            $this->ordenCompraRepository->update($id, $updateData);

            return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Eliminar una Orden de Compra (eliminación lógica).
     * Solo permitido si no tiene mercadería ingresada a Kárdex.
     */
    public function eliminarOrden(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $orden = $this->obtenerOrdenPorId($id);

            // Validar si la orden tiene mercadería ya recepcionada en Kárdex
            $tieneRecepciones = $orden->detalles()
                ->where('Detalle_Orden_CompraCantidadRecibida', '>', 0)
                ->exists();

            if ($tieneRecepciones) {
                throw ValidationException::withMessages([
                    'orden' => "No se puede eliminar la orden {$id} porque ya registra mercadería ingresada a Kárdex. Si desea revertirla, debe anular la recepción desde el módulo correspondiente.",
                ]);
            }

            // Marcar detalles como eliminados
            $auditDetalle = AuditHelper::getDeletionAudit('Detalle_Orden_Compra');
            DetalleOrdenCompra::where('Detalle_Orden_Compra_Orden_CompraId', $id)
                ->update($auditDetalle);

            // Marcar cabecera como eliminada
            return $this->ordenCompraRepository->delete($id);
        });
    }

    /**
     * Iniciar el proceso de recepción física en almacén.
     */
    public function iniciarRecepcion(string $id): OrdenCompra
    {
        return DB::transaction(function () use ($id) {
            $orden = $this->obtenerOrdenPorId($id);

            $estadosPermitidos = ['EMITIDA', 'RECEPCION_PARCIAL', 'PENDIENTE_RECEPCION', 'EN_RECEPCION', 'ENVIADA', 'BORRADOR', 'P'];
            if (!in_array($orden->Orden_CompraEstado, $estadosPermitidos, true)) {
                throw ValidationException::withMessages([
                    'estado' => "No se puede iniciar la recepción de una orden en estado '{$orden->Orden_CompraEstado}'.",
                ]);
            }

            $usuarioId = AuditHelper::getCurrentUser();

            $this->ordenCompraRepository->update($id, [
                'Orden_CompraEstado' => 'RECEPCION_PARCIAL',
                'Orden_CompraUsuarioRecepcionId' => $usuarioId,
            ]);

            return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Recepcionar un ítem individual de la orden de compra.
     * Incrementa stock físico y genera asiento oficial de Entrada ('E') en Kárdex.
     */
    public function recepcionarItem(string $id, array $itemData): array
    {
        return DB::transaction(function () use ($id, $itemData) {
            $orden = $this->obtenerOrdenPorId($id);

            if (!in_array($orden->Orden_CompraEstado, ['RECEPCION_PARCIAL', 'EMITIDA', 'EN_RECEPCION', 'PENDIENTE_RECEPCION', 'ENVIADA', 'P', 'BORRADOR', 'PENDIENTE'], true)) {
                throw ValidationException::withMessages([
                    'estado' => "La orden debe estar disponible para recepción para registrar ingresos de mercadería. Estado actual: '{$orden->Orden_CompraEstado}'.",
                ]);
            }

            // Si la orden aún no figuraba en RECEPCION_PARCIAL, transicionar automáticamente
            if ($orden->Orden_CompraEstado !== 'RECEPCION_PARCIAL') {
                $this->ordenCompraRepository->update($id, [
                    'Orden_CompraEstado' => 'RECEPCION_PARCIAL',
                    'Orden_CompraUsuarioRecepcionId' => AuditHelper::getCurrentUser(),
                ]);
                $orden->refresh();
            }

            $detalleId = $itemData['detalle_id'] ?? $itemData['Detalle_Orden_CompraId'] ?? null;
            if (!$detalleId) {
                throw ValidationException::withMessages([
                    'detalle_id' => 'Se requiere el identificador del ítem de la orden de compra.',
                ]);
            }

            $detalle = DetalleOrdenCompra::where('Detalle_Orden_CompraId', $detalleId)
                ->where('Detalle_Orden_Compra_Orden_CompraId', $id)
                ->where('Detalle_Orden_CompraEliminado', 'N')
                ->first();

            if (!$detalle) {
                throw ValidationException::withMessages([
                    'detalle_id' => "El ítem con ID '{$detalleId}' no pertenece a la orden {$id} o no existe.",
                ]);
            }

            $cantidadRecibidaTurno = (float) ($itemData['cantidad_recibida'] ?? 0);
            if ($cantidadRecibidaTurno <= 0) {
                throw ValidationException::withMessages([
                    'cantidad_recibida' => 'La cantidad recibida debe ser mayor a 0.',
                ]);
            }

            $cantPedida = (float) $detalle->Detalle_Orden_CompraCantidad;
            $cantPrevia = (float) ($detalle->Detalle_Orden_CompraCantidadRecibida ?? 0);
            $nuevaCantRecibidaTotal = round($cantPrevia + $cantidadRecibidaTurno, 2);

            if ($nuevaCantRecibidaTotal > $cantPedida) {
                throw ValidationException::withMessages([
                    'cantidad_recibida' => "La cantidad ingresada ({$cantidadRecibidaTurno}) sumada a lo recibido previamente ({$cantPrevia}) supera la cantidad solicitada en la orden ({$cantPedida}).",
                ]);
            }

            $producto = Producto::with('detalleProductoMedidas')
                ->where('ProductoId', $detalle->Detalle_ProductoId)
                ->where('ProductoEliminado', 'N')
                ->first();

            if (!$producto) {
                throw ValidationException::withMessages([
                    'producto_id' => "El producto asociado al ítem no existe o fue eliminado.",
                ]);
            }

            $stockAnterior = (float) $producto->ProductoStockActual;
            $usuarioId = AuditHelper::getCurrentUser();
            $esNuevo = !empty($itemData['es_producto_nuevo']);

            $unidadId = $detalle->Detalle_UnidadMedidaId;
            $unidadActiva = $producto->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_unidades_medidaId', $unidadId);
            if (!$unidadActiva) {
                $baseDetalle = $producto->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
                    ?? $producto->detalleProductoMedidas->first();
                $unidadId = $baseDetalle?->Detalle_Producto_medida_unidades_medidaId ?? 'UND-00001';
            }

            // 1. Asentar movimiento en Kardex de tipo Entrada 'E' con subtipo 'RECEPCION_OC'
            $movimiento = $this->inventarioService->registrarMovimiento([
                'productoId' => $producto->ProductoId,
                'unidadesMedidaId' => $unidadId,
                'tipoMovimiento' => 'E',
                'subtipo' => 'RECEPCION_OC',
                'referenciaTipo' => 'ORDEN_COMPRA',
                'referenciaId' => $orden->Orden_CompraId,
                'cantidad' => $cantidadRecibidaTurno,
                'documentoOperacionId' => $orden->Orden_CompraId,
                'precioUnitario' => $detalle->Detalle_Orden_CompraPrecioUnitario,
                'motivo' => "Recepción de OC {$orden->Orden_CompraId} - Cant: {$cantidadRecibidaTurno} {$producto->ProductoNombre}",
            ]);

            $stockNuevo = (float) $producto->fresh()->ProductoStockActual;

            // 2. Actualizar Detalle_Orden_Compra
            $estaCompleto = $nuevaCantRecibidaTotal >= $cantPedida;
            $detalle->update([
                'Detalle_Orden_CompraCantidadRecibida' => $nuevaCantRecibidaTotal,
                'Detalle_Orden_CompraEntregado' => $estaCompleto ? 'S' : 'N',
                'Detalle_Orden_CompraFechaRecepcionItem' => now(),
                'Detalle_Orden_CompraUsuarioRecepcionItemId' => $usuarioId,
                'Detalle_Orden_CompraFueProductoNuevo' => $esNuevo ? 'S' : ($detalle->Detalle_Orden_CompraFueProductoNuevo ?? 'N'),
            ]);

            // 3. Notificación en tiempo real
            $this->notificacionService->notificarIngresoStock(
                $producto,
                $cantidadRecibidaTurno,
                $orden->Orden_CompraId,
                $stockAnterior,
                $stockNuevo
            );

            return [
                'orden_id' => $orden->Orden_CompraId,
                'detalle' => $detalle->fresh(['producto', 'unidadMedida']),
                'movimiento' => $movimiento,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'es_completo' => $estaCompleto,
            ];
        });
    }

    /**
     * Rechazar un ítem individual de la orden de compra en recepción.
     * Marca el ítem como rechazado ('R') sin ingresar stock a Kárdex.
     */
    public function rechazarItem(string $id, array $itemData): array
    {
        return DB::transaction(function () use ($id, $itemData) {
            $orden = $this->obtenerOrdenPorId($id);

            if (!in_array($orden->Orden_CompraEstado, ['RECEPCION_PARCIAL', 'EMITIDA', 'EN_RECEPCION', 'PENDIENTE_RECEPCION', 'ENVIADA', 'P', 'BORRADOR', 'PENDIENTE'], true)) {
                throw ValidationException::withMessages([
                    'estado' => "La orden no se encuentra en un estado que permita rechazar productos. Estado actual: '{$orden->Orden_CompraEstado}'.",
                ]);
            }

            // Si la orden aún no figuraba en RECEPCION_PARCIAL, transicionar automáticamente
            if ($orden->Orden_CompraEstado !== 'RECEPCION_PARCIAL') {
                $this->ordenCompraRepository->update($id, [
                    'Orden_CompraEstado' => 'RECEPCION_PARCIAL',
                    'Orden_CompraUsuarioRecepcionId' => AuditHelper::getCurrentUser(),
                ]);
                $orden->refresh();
            }

            $detalleId = $itemData['detalle_id'] ?? $itemData['Detalle_Orden_CompraId'] ?? null;
            if (!$detalleId) {
                throw ValidationException::withMessages([
                    'detalle_id' => 'Se requiere el identificador del ítem de la orden de compra.',
                ]);
            }

            $detalle = DetalleOrdenCompra::where('Detalle_Orden_CompraId', $detalleId)
                ->where('Detalle_Orden_Compra_Orden_CompraId', $id)
                ->where('Detalle_Orden_CompraEliminado', 'N')
                ->first();

            if (!$detalle) {
                throw ValidationException::withMessages([
                    'detalle_id' => "El ítem con ID '{$detalleId}' no pertenece a la orden {$id} o no existe.",
                ]);
            }

            $usuarioId = AuditHelper::getCurrentUser();
            $audit = AuditHelper::getModificationAudit('Detalle_Orden_Compra');
            $detalle->update(array_merge($audit, [
                'Detalle_Orden_CompraEntregado' => 'R', // 'R' = Rechazado
                'Detalle_Orden_CompraFechaRecepcionItem' => now(),
                'Detalle_Orden_CompraUsuarioRecepcionItemId' => $usuarioId,
            ]));

            return [
                'orden_id' => $orden->Orden_CompraId,
                'detalle_id' => $detalle->Detalle_Orden_CompraId,
                'producto_nombre' => $detalle->producto?->ProductoNombre,
                'estado' => 'RECHAZADO',
                'mensaje' => "El producto '{$detalle->producto?->ProductoNombre}' fue marcado como rechazado.",
            ];
        });
    }

    /**
     * Cerrar la recepción de una orden de compra.
     * Clasifica automáticamente como CERRADA o CERRADA_CON_FALTANTE.
     */
    public function cerrarRecepcion(string $id, bool $generarNuevaOcFaltantes = false): OrdenCompra
    {
        return DB::transaction(function () use ($id, $generarNuevaOcFaltantes) {
            $orden = $this->obtenerOrdenPorId($id);

            if (!in_array($orden->Orden_CompraEstado, ['RECEPCION_PARCIAL', 'EMITIDA', 'EN_RECEPCION', 'PENDIENTE_RECEPCION', 'P', 'BORRADOR', 'PENDIENTE', 'ENVIADA'], true)) {
                throw ValidationException::withMessages([
                    'estado' => "No se puede cerrar una orden que no está en proceso de recepción. Estado: '{$orden->Orden_CompraEstado}'.",
                ]);
            }

            $detalles = $orden->detalles()->where('Detalle_Orden_CompraEliminado', 'N')->get();
            if ($detalles->isEmpty()) {
                throw ValidationException::withMessages([
                    'detalles' => 'La orden no contiene ítems para cerrar recepción.',
                ]);
            }

            // Evaluar si existen faltantes
            $hayFaltante = false;
            foreach ($detalles as $det) {
                $recibido = (float) ($det->Detalle_Orden_CompraCantidadRecibida ?? 0);
                $pedido = (float) $det->Detalle_Orden_CompraCantidad;
                if ($recibido < $pedido) {
                    $hayFaltante = true;
                    break;
                }
            }

            $nuevoEstado = $hayFaltante ? 'CERRADA_CON_FALTANTE' : 'CERRADA_CONFORME';
            $ahora = now();

            $this->ordenCompraRepository->update($id, [
                'Orden_CompraEstado' => $nuevoEstado,
                'Orden_CompraFechaRecepcionReal' => $ahora,
                'Orden_CompraCerradaConFaltante' => $hayFaltante ? 'S' : 'N',
                'Orden_CompraUsuarioRecepcionId' => $orden->Orden_CompraUsuarioRecepcionId ?? AuditHelper::getCurrentUser(),
            ]);

            // Vincular productos al proveedor en el catálogo comercial
            $this->vincularProductosProveedor($orden);

            // Generar nueva OC de reposición si se solicitó expresamente
            if ($generarNuevaOcFaltantes && $hayFaltante) {
                $this->generarNuevaOcFaltantes($id);
            }

            return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Anular una recepción de orden de compra revirtiendo el stock mediante contra-movimientos.
     */
    public function anularRecepcion(string $id, string $motivo): OrdenCompra
    {
        $motivoLimpio = trim($motivo);
        if (mb_strlen($motivoLimpio) < 10) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de anulación es obligatorio y debe tener al menos 10 caracteres explicativos.',
            ]);
        }

        return DB::transaction(function () use ($id, $motivoLimpio) {
            $orden = $this->obtenerOrdenPorId($id);

            $estadosPermitidos = ['CERRADA_CONFORME', 'CERRADA_CON_FALTANTE', 'RECEPCION_PARCIAL', 'CERRADA', 'EN_RECEPCION', 'C'];
            if (!in_array($orden->Orden_CompraEstado, $estadosPermitidos, true)) {
                throw ValidationException::withMessages([
                    'estado' => "No se puede anular la recepción de una orden en estado '{$orden->Orden_CompraEstado}'.",
                ]);
            }

            $detalles = $orden->detalles()->where('Detalle_Orden_CompraEliminado', 'N')->get();
            $docOperacion = "ANUL-{$orden->Orden_CompraId}-" . now()->format('YmdHis');

            foreach ($detalles as $det) {
                $cantRecibida = (float) ($det->Detalle_Orden_CompraCantidadRecibida ?? 0);
                if ($cantRecibida > 0) {
                    $producto = Producto::with('detalleProductoMedidas')
                        ->where('ProductoId', $det->Detalle_ProductoId)
                        ->where('ProductoEliminado', 'N')
                        ->first();

                    if ($producto) {
                        $unidadId = $det->Detalle_UnidadMedidaId;
                        $unidadActiva = $producto->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_unidades_medidaId', $unidadId);
                        if (!$unidadActiva) {
                            $baseDetalle = $producto->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
                                ?? $producto->detalleProductoMedidas->first();
                            $unidadId = $baseDetalle?->Detalle_Producto_medida_unidades_medidaId ?? 'UND-00001';
                        }

                        // Registrar contra-movimiento de salida 'S' con subtipo 'ANULACION_RECEPCION'
                        $this->inventarioService->registrarMovimiento([
                            'productoId' => $producto->ProductoId,
                            'unidadesMedidaId' => $unidadId,
                            'tipoMovimiento' => 'S',
                            'subtipo' => 'ANULACION_RECEPCION',
                            'referenciaTipo' => 'ORDEN_COMPRA',
                            'referenciaId' => $orden->Orden_CompraId,
                            'cantidad' => $cantRecibida,
                            'documentoOperacionId' => $docOperacion,
                            'precioUnitario' => $det->Detalle_Orden_CompraPrecioUnitario,
                            'motivo' => "Anulación de recepción OC {$orden->Orden_CompraId}: {$motivoLimpio}",
                        ]);
                    }

                    // Resetear recepciones en el ítem
                    $det->update([
                        'Detalle_Orden_CompraCantidadRecibida' => 0.00,
                        'Detalle_Orden_CompraEntregado' => 'N',
                    ]);
                }
            }

            $obs = trim(($orden->Orden_CompraObservacion ?? '') . " [Recepción Anulada: {$motivoLimpio}]");
            $this->ordenCompraRepository->update($id, [
                'Orden_CompraEstado' => 'ANULADA',
                'Orden_CompraMotivoAnulacion' => $motivoLimpio,
                'Orden_CompraObservacion' => mb_substr($obs, 0, 250),
            ]);

            // Notificación de anulación
            $this->notificacionService->notificarAnulacionRecepcion($orden->Orden_CompraId, $motivoLimpio);

            return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Registrar una compra rápida a PYME Vecina por quiebre de stock.
     */
    public function crearCompraRapida(array $datos): OrdenCompra
    {
        return DB::transaction(function () use ($datos) {
            $pyme = Proveedor::where('ProveedorId', 'PRV-PYME-VECINA')->first();
            if (!$pyme) {
                $pyme = Proveedor::firstOrCreate(
                    ['ProveedorId' => 'PRV-PYME-VECINA'],
                    [
                        'ProveedorRuc' => '20000000001',
                        'ProveedorRazonSocial' => 'PYME Vecina (Comercio Aliado Local)',
                        'ProveedorTelefono' => '999888777',
                        'ProveedorEstado' => 'Activo',
                        'ProveedorEliminado' => 'N',
                    ]
                );
            }

            $detallesInput = $datos['detalles'] ?? [];
            if (empty($detallesInput) || !is_array($detallesInput)) {
                throw ValidationException::withMessages([
                    'detalles' => 'La compra rápida debe contener al menos un producto.',
                ]);
            }

            $pedidoId = $datos['pedido_id'] ?? null;
            $obs = $datos['observaciones'] ?? ($pedidoId ? "Compra rápida para cubrir stockout de Pedido {$pedidoId}" : 'Compra rápida a comercio aliado PYME Vecina');

            // 1. Crear Orden de Compra en estado RECEPCION_PARCIAL
            $ordenDatos = [
                'Orden_Compra_ProveedorId' => $pyme->ProveedorId,
                'Orden_CompraEstado' => 'RECEPCION_PARCIAL',
                'Orden_CompraFechaEstimadaLlegada' => now()->toDateString(),
                'Orden_CompraObservacion' => $obs,
                'detalles' => $detallesInput,
            ];

            $orden = $this->crearOrden($ordenDatos);

            // 2. Recepcionar inmediatamente todos los ítems con subtipo COMPRA_RAPIDA
            foreach ($orden->detalles as $det) {
                $producto = Producto::with('detalleProductoMedidas')
                    ->where('ProductoId', $det->Detalle_ProductoId)
                    ->where('ProductoEliminado', 'N')
                    ->first();

                if ($producto) {
                    $cant = (float) $det->Detalle_Orden_CompraCantidad;
                    $unidadId = $det->Detalle_UnidadMedidaId;
                    $unidadActiva = $producto->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_unidades_medidaId', $unidadId);
                    if (!$unidadActiva) {
                        $baseDetalle = $producto->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
                            ?? $producto->detalleProductoMedidas->first();
                        $unidadId = $baseDetalle?->Detalle_Producto_medida_unidades_medidaId ?? 'UND-00001';
                    }

                    $this->inventarioService->registrarMovimiento([
                        'productoId' => $producto->ProductoId,
                        'unidadesMedidaId' => $unidadId,
                        'tipoMovimiento' => 'E',
                        'subtipo' => 'COMPRA_RAPIDA',
                        'referenciaTipo' => 'ORDEN_COMPRA',
                        'referenciaId' => $orden->Orden_CompraId,
                        'cantidad' => $cant,
                        'documentoOperacionId' => $orden->Orden_CompraId,
                        'precioUnitario' => $det->Detalle_Orden_CompraPrecioUnitario,
                        'motivo' => "Compra rápida en comercio aliado PYME Vecina para OC {$orden->Orden_CompraId}",
                    ]);

                    $det->update([
                        'Detalle_Orden_CompraCantidadRecibida' => $cant,
                        'Detalle_Orden_CompraEntregado' => 'S',
                        'Detalle_Orden_CompraFechaRecepcionItem' => now(),
                        'Detalle_Orden_CompraUsuarioRecepcionItemId' => AuditHelper::getCurrentUser(),
                    ]);

                    if ($pedidoId) {
                        try {
                            $this->roturaStockService->registrarIntento([
                                'pedido_id' => $pedidoId,
                                'producto_id' => $producto->ProductoId,
                                'orden_compra_codigo' => $orden->Orden_CompraId,
                                'cantidad_solicitada' => $cant,
                                'cantidad_disponible' => 0,
                                'cantidad_faltante' => $cant,
                                'observaciones' => "Mitigado con compra rápida {$orden->Orden_CompraId} a PYME Vecina",
                            ]);
                        } catch (\Throwable $e) {}
                    }
                }
            }

            // 3. Cerrar formalmente la orden
            $this->ordenCompraRepository->update($orden->Orden_CompraId, [
                'Orden_CompraEstado' => 'CERRADA_CONFORME',
                'Orden_CompraFechaRecepcionReal' => now(),
                'Orden_CompraCerradaConFaltante' => 'N',
                'Orden_CompraUsuarioRecepcionId' => AuditHelper::getCurrentUser(),
            ]);

            return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Generar una nueva orden de compra solo por los ítems no recibidos.
     */
    public function generarNuevaOcFaltantes(string $id): OrdenCompra
    {
        return DB::transaction(function () use ($id) {
            $ordenOriginal = $this->obtenerOrdenPorId($id);

            $detallesOriginales = $ordenOriginal->detalles()
                ->where('Detalle_Orden_CompraEliminado', 'N')
                ->get();

            $detallesFaltantes = [];
            foreach ($detallesOriginales as $det) {
                $pedido = (float) $det->Detalle_Orden_CompraCantidad;
                $recibido = (float) ($det->Detalle_Orden_CompraCantidadRecibida ?? 0);
                $faltante = round($pedido - $recibido, 2);

                if ($faltante > 0) {
                    $detallesFaltantes[] = [
                        'Detalle_ProductoId' => $det->Detalle_ProductoId,
                        'Detalle_UnidadMedidaId' => $det->Detalle_UnidadMedidaId,
                        'Detalle_Orden_CompraCantidad' => $faltante,
                        'Detalle_Orden_CompraPrecioUnitario' => (float) $det->Detalle_Orden_CompraPrecioUnitario,
                    ];
                }
            }

            if (empty($detallesFaltantes)) {
                throw ValidationException::withMessages([
                    'faltantes' => "La orden {$id} no tiene cantidades pendientes o faltantes.",
                ]);
            }

            $nuevaOrdenDatos = [
                'Orden_Compra_ProveedorId' => $ordenOriginal->Orden_Compra_ProveedorId,
                'Orden_CompraEstado' => 'EMITIDA',
                'Orden_CompraFechaEstimadaLlegada' => now()->addDays(3)->toDateString(),
                'Orden_CompraObservacion' => "Generada automáticamente por faltantes de la OC {$ordenOriginal->Orden_CompraId}",
                'detalles' => $detallesFaltantes,
            ];

            return $this->crearOrden($nuevaOrdenDatos);
        });
    }

    /**
     * Obtener historial y auditoría de recepción de una orden de compra y sus asientos en Kárdex.
     */
    public function obtenerHistorialRecepcion(string $id): array
    {
        $orden = $this->obtenerOrdenPorId($id);
        $orden->loadMissing(['detalles.producto', 'detalles.unidadMedida', 'proveedor']);

        $movimientosKardex = MovimientoProducto::where(function ($q) use ($id) {
            $q->where('Movimiento_productoDocumentoOperacionId', 'LIKE', "%{$id}%")
              ->orWhere('Movimiento_productoReferenciaId', $id);
        })
        ->with(['producto', 'unidadMedida'])
        ->orderBy('Movimiento_productoFecha_Movimiento', 'desc')
        ->get();

        $estadoCanonico = match ($orden->Orden_CompraEstado) {
            'EMITIDA', 'BORRADOR', 'ENVIADA', 'PENDIENTE_RECEPCION', 'P' => 'EMITIDA',
            'RECEPCION_PARCIAL', 'EN_RECEPCION' => 'RECEPCION_PARCIAL',
            'CERRADA_CONFORME', 'CERRADA', 'C' => ($orden->Orden_CompraCerradaConFaltante === 'S' ? 'CERRADA_CON_FALTANTE' : 'CERRADA_CONFORME'),
            'CERRADA_CON_FALTANTE' => 'CERRADA_CON_FALTANTE',
            'ANULADA', 'A', 'CANCELADA_PROVEEDOR' => 'ANULADA',
            default => $orden->Orden_CompraEstado ?? 'EMITIDA'
        };

        $usuarioReceptorNombre = AuditHelper::resolverNombreUsuario($orden->Orden_CompraUsuarioRecepcionId);
        if (!$usuarioReceptorNombre || $usuarioReceptorNombre === 'Desconocido' || $usuarioReceptorNombre === 'CLI-00001') {
            $primerDet = $orden->detalles->firstWhere('Detalle_Orden_CompraUsuarioRecepcionItemId');
            if ($primerDet) {
                $usuarioReceptorNombre = AuditHelper::resolverNombreUsuario($primerDet->Detalle_Orden_CompraUsuarioRecepcionItemId);
            }
            if (!$usuarioReceptorNombre || $usuarioReceptorNombre === 'Desconocido') {
                $usuarioReceptorNombre = AuditHelper::resolverNombreUsuario(AuditHelper::getCurrentUser()) ?: 'Almacenero';
            }
        }

        $fechaEmisionStr = $orden->Orden_CompraFecha?->format('d/m/Y H:i') ?? '-';
        $fechaEstimadaStr = $orden->Orden_CompraFechaEstimadaLlegada?->format('d/m/Y') ?? 'No definida';
        $fechaRecepcionRealStr = $orden->Orden_CompraFechaRecepcionReal?->format('d/m/Y H:i') 
            ?? (in_array($estadoCanonico, ['CERRADA_CONFORME', 'CERRADA_CON_FALTANTE']) ? $orden->Orden_CompraFechaModificacion?->format('d/m/Y H:i') : 'En proceso');

        $ordenData = [
            'id' => $orden->Orden_CompraId,
            'estado' => $estadoCanonico,
            'estado_texto' => match($estadoCanonico) {
                'EMITIDA' => 'Emitida',
                'RECEPCION_PARCIAL' => 'En Recepción Parcial',
                'CERRADA_CONFORME' => 'Cerrada Conforme',
                'CERRADA_CON_FALTANTE' => 'Cerrada con Faltante',
                'ANULADA' => 'Anulada',
                default => $estadoCanonico
            },
            'proveedor' => $orden->proveedor ? [
                'id' => $orden->proveedor->ProveedorId,
                'razon_social' => $orden->proveedor->ProveedorRazonSocial,
                'ruc' => $orden->proveedor->ProveedorRuc,
                'telefono' => $orden->proveedor->ProveedorTelefono,
            ] : null,
            'fecha_emision' => $fechaEmisionStr,
            'fecha_creacion' => $orden->Orden_CompraFecha,
            'fecha_entrega_estimada' => $fechaEstimadaStr,
            'fecha_estimada_llegada' => $fechaEstimadaStr,
            'fecha_recepcion_real' => $fechaRecepcionRealStr,
            'usuario_receptor' => $usuarioReceptorNombre,
            'cerrada_con_faltante' => $orden->Orden_CompraCerradaConFaltante === 'S',
            'motivo_anulacion' => $orden->Orden_CompraMotivoAnulacion,
        ];

        $itemsTransformados = $orden->detalles->map(function ($det) {
            $solicitado = (float) $det->Detalle_Orden_CompraCantidad;
            $recibido = (float) ($det->Detalle_Orden_CompraCantidadRecibida ?? 0);
            $factor = 1;
            if ($det->Detalle_ProductoId && $det->Detalle_UnidadMedidaId) {
                $medida = \App\Models\DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $det->Detalle_ProductoId)
                    ->where('Detalle_Producto_medida_unidades_medidaId', $det->Detalle_UnidadMedidaId)
                    ->where('Detalle_Producto_medidaEliminado', 'N')
                    ->first();
                $factor = $medida?->Detalle_Producto_medida_factor_conversion ?? 1;
            }
            if ($factor <= 0) $factor = 1;

            $abreviatura = $det->unidadMedida?->unidades_medidaAbreviatura ?? 'UND';
            $presentacionCompleta = ($factor > 1) ? "{$abreviatura} ({$factor} UND)" : $abreviatura;

            return [
                'id' => $det->Detalle_Orden_CompraId,
                'producto_id' => $det->Detalle_ProductoId,
                'producto_nombre' => $det->producto?->ProductoNombre,
                'producto_marca' => $det->producto?->ProductoMarca,
                'unidad_medida_id' => $det->Detalle_UnidadMedidaId,
                'unidad_nombre' => $abreviatura,
                'unidad_medida_abreviatura' => $abreviatura,
                'factor_conversion' => (int) $factor,
                'presentacion_completa' => $presentacionCompleta,
                'cantidad_solicitada' => $solicitado,
                'cantidad_recibida' => $recibido,
                'cantidad_pendiente' => max(0.0, round($solicitado - $recibido, 2)),
                'total_unidades_base_solicitadas' => round($solicitado * $factor, 2),
                'total_unidades_base_recibidas' => round($recibido * $factor, 2),
                'total_unidades_base_pendientes' => round(max(0.0, round($solicitado - $recibido, 2)) * $factor, 2),
                'entregado' => $det->Detalle_Orden_CompraEntregado === 'S',
                'estado_recepcion' => ($recibido >= $solicitado) ? 'COMPLETO' : ($recibido > 0 ? 'PARCIAL' : 'PENDIENTE'),
                'fecha_recepcion' => $det->Detalle_Orden_CompraFechaRecepcionItem?->format('d/m/Y H:i'),
                'usuario_recepcion' => AuditHelper::resolverNombreUsuario($det->Detalle_Orden_CompraUsuarioRecepcionItemId),
            ];
        });

        $movsTransformados = $movimientosKardex->map(function ($m) {
            return [
                'id' => $m->Movimiento_productoId,
                'producto_id' => $m->Movimiento_producto_ProductoId,
                'producto_nombre' => $m->producto?->ProductoNombre,
                'unidad_nombre' => $m->unidadMedida?->unidades_medidaAbreviatura ?? 'UND',
                'tipo' => $m->Movimiento_productoTipoMovimiento,
                'tipo_texto' => $m->Movimiento_productoTipoMovimiento === 'E' ? 'Entrada (Almacén)' : 'Salida',
                'subtipo' => $m->Movimiento_productoSubtipo,
                'cantidad_entrada' => (float) $m->Movimiento_productoCantidadEntrada,
                'cantidad_salida' => (float) $m->Movimiento_productoCantidadSalida,
                'saldo' => (float) $m->Movimiento_productoCantidadSaldo,
                'fecha' => $m->Movimiento_productoFecha_Movimiento?->format('d/m/Y H:i'),
                'documento' => $m->Movimiento_productoDocumentoOperacionId,
                'motivo' => $m->Movimiento_productoMotivo,
                'usuario' => AuditHelper::resolverNombreUsuario($m->Movimiento_productoUsuarioCreacion),
            ];
        });

        return [
            'orden_id' => $orden->Orden_CompraId,
            'orden' => $ordenData,
            'items' => $itemsTransformados,
            'detalles' => $itemsTransformados,
            'movimientos_kardex' => $movsTransformados,
            'estado' => $estadoCanonico,
            'fecha_emision' => $fechaEmisionStr,
            'fecha_entrega_estimada' => $fechaEstimadaStr,
            'fecha_recepcion_real' => $fechaRecepcionRealStr,
            'usuario_receptor' => $usuarioReceptorNombre,
            'cerrada_con_faltante' => $orden->Orden_CompraCerradaConFaltante === 'S',
        ];
    }

    /**
     * Cambiar el estado de una orden de compra garantizando consistencia de Kárdex.
     */
    public function cambiarEstado(string $id, string $nuevoEstado, ?string $motivo = null): OrdenCompra
    {
        return DB::transaction(function () use ($id, $nuevoEstado, $motivo) {
            $orden = $this->obtenerOrdenPorId($id);
            $rawEstado = strtoupper(trim($nuevoEstado));

            $mapLegacy = [
                'P' => 'EMITIDA',
                'BORRADOR' => 'EMITIDA',
                'ENVIADA' => 'EMITIDA',
                'PENDIENTE_RECEPCION' => 'EMITIDA',
                'EN_RECEPCION' => 'RECEPCION_PARCIAL',
                'C' => 'CERRADA_CONFORME',
                'CERRADA' => 'CERRADA_CONFORME',
                'A' => 'ANULADA',
                'CANCELADA_PROVEEDOR' => 'ANULADA',
            ];
            $estado = $mapLegacy[$rawEstado] ?? $rawEstado;
            $estadosValidos = ['EMITIDA', 'RECEPCION_PARCIAL', 'CERRADA_CONFORME', 'CERRADA_CON_FALTANTE', 'ANULADA'];

            if (!in_array($estado, $estadosValidos, true)) {
                throw ValidationException::withMessages([
                    'estado' => "Estado '{$nuevoEstado}' no es válido. Opciones permitidas: " . implode(', ', $estadosValidos),
                ]);
            }

            $estadoActual = match ($orden->Orden_CompraEstado) {
                'P', 'BORRADOR', 'ENVIADA', 'PENDIENTE_RECEPCION' => 'EMITIDA',
                'EN_RECEPCION' => 'RECEPCION_PARCIAL',
                'C', 'CERRADA' => 'CERRADA_CONFORME',
                'A', 'CANCELADA_PROVEEDOR' => 'ANULADA',
                default => $orden->Orden_CompraEstado
            };

            if ($estadoActual === $estado) {
                return $orden;
            }

            // 1. Transición a ANULADA
            if ($estado === 'ANULADA') {
                $totalRecibido = (float) $orden->detalles()->sum('Detalle_Orden_CompraCantidadRecibida');
                if ($totalRecibido > 0 || in_array($orden->Orden_CompraEstado, ['CERRADA_CONFORME', 'CERRADA', 'CERRADA_CON_FALTANTE', 'C'], true)) {
                    return $this->anularRecepcion($id, $motivo ?: 'Anulación formal de la orden de compra');
                }

                $obs = $motivo ? trim(($orden->Orden_CompraObservacion ?? '') . " [Anulada: {$motivo}]") : $orden->Orden_CompraObservacion;
                $this->ordenCompraRepository->update($id, [
                    'Orden_CompraEstado' => 'ANULADA',
                    'Orden_CompraMotivoAnulacion' => $motivo,
                    'Orden_CompraObservacion' => mb_substr((string) $obs, 0, 250),
                ]);

                return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
            }

            // 2. Transición a CERRADA_CONFORME (Recepción total directa)
            if ($estado === 'CERRADA_CONFORME') {
                if (in_array($orden->Orden_CompraEstado, ['ANULADA', 'A'], true)) {
                    throw ValidationException::withMessages([
                        'estado' => 'No se puede completar una orden que ha sido previamente anulada.',
                    ]);
                }

                if (empty($orden->Orden_Compra_ProveedorId)) {
                    throw ValidationException::withMessages([
                        'Orden_Compra_ProveedorId' => 'Debe asignar un proveedor a la orden antes de completarla.',
                    ]);
                }

                // Recepcionar ítems pendientes en lote
                foreach ($orden->detalles as $det) {
                    $cantPedida = (float) $det->Detalle_Orden_CompraCantidad;
                    $cantRecibida = (float) ($det->Detalle_Orden_CompraCantidadRecibida ?? 0);
                    $pendiente = round($cantPedida - $cantRecibida, 2);

                    if ($pendiente > 0) {
                        $this->recepcionarItem($id, [
                            'detalle_id' => $det->Detalle_Orden_CompraId,
                            'cantidad_recibida' => $pendiente,
                        ]);
                    }
                }

                return $this->cerrarRecepcion($id, false);
            }

            // 3. Transición a RECEPCION_PARCIAL
            if ($estado === 'RECEPCION_PARCIAL') {
                return $this->iniciarRecepcion($id);
            }

            // 4. Otras transiciones (EMITIDA, etc.)
            $updateData = ['Orden_CompraEstado' => $estado];
            if ($motivo) {
                $obs = trim(($orden->Orden_CompraObservacion ?? '') . " [Estado {$estado}: {$motivo}]");
                $updateData['Orden_CompraObservacion'] = mb_substr($obs, 0, 250);
            }

            $this->ordenCompraRepository->update($id, $updateData);

            return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
        });
    }

    /**
     * Vincula en el catálogo Producto_Proveedor los productos ordenados con el proveedor.
     * No altera stock ni genera movimientos de Kardex.
     */
    protected function vincularProductosProveedor(OrdenCompra $orden): void
    {
        if (empty($orden->Orden_Compra_ProveedorId)) {
            return;
        }

        foreach ($orden->detalles as $det) {
            $existe = ProductoProveedor::where('Producto_Proveedor_ProductoId', $det->Detalle_ProductoId)
                ->where('Producto_Proveedor_ProveedorId', $orden->Orden_Compra_ProveedorId)
                ->first();

            if (!$existe) {
                $auditPP = AuditHelper::getCreationAudit('Producto_Proveedor');
                ProductoProveedor::create(array_merge($auditPP, [
                    'Producto_Proveedor_ProductoId' => $det->Detalle_ProductoId,
                    'Producto_Proveedor_ProveedorId' => $orden->Orden_Compra_ProveedorId,
                    'Producto_ProveedorEliminado' => 'N',
                ]));
            }
        }
    }


    /**
     * Restaurar una orden de compra previamente eliminada.
     */
    public function restaurarOrden(string $id): OrdenCompra
    {
        return DB::transaction(function () use ($id) {
            $orden = $this->obtenerOrdenPorId($id, true);

            // Restaurar detalles
            DetalleOrdenCompra::where('Detalle_Orden_Compra_Orden_CompraId', $id)
                ->update(array_merge(
                    AuditHelper::getModificationAudit('Detalle_Orden_Compra'),
                    ['Detalle_Orden_CompraEliminado' => 'N']
                ));

            $this->ordenCompraRepository->restore($id);

            return $this->obtenerOrdenPorId($id);
        });
    }

    /**
     * Obtener URL directa y pública del PDF de la orden.
     */
    public function obtenerUrlPdf(string $id): array
    {
        $orden = $this->obtenerOrdenPorId($id);
        $url = url("/api/ordenes-compra/{$orden->Orden_CompraId}/pdf");

        return [
            'orden_id' => $orden->Orden_CompraId,
            'pdf_url' => $url,
            'filename' => "Orden_Compra_{$orden->Orden_CompraId}.pdf",
        ];
    }

    /**
     * Generar formato y URL de WhatsApp para enviar pedido al proveedor.
     * Extrae automáticamente el teléfono de la tabla Proveedor sin requerir digitación manual.
     */
    public function generarDatosWhatsApp(string $id): array
    {
        $orden = $this->obtenerOrdenPorId($id);
        $orden->loadMissing(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);

        if (!$orden->proveedor) {
            throw ValidationException::withMessages([
                'proveedor' => 'La orden no tiene un proveedor asignado. Por favor asigne un proveedor antes de enviar por WhatsApp.',
            ]);
        }

        $proveedor = $orden->proveedor;
        $telefonoOriginal = trim($proveedor->ProveedorTelefono ?? '');

        // Sanitizar teléfono (remover guiones, espacios y caracteres no numéricos)
        $telefonoSanitizado = preg_replace('/[^0-9]/', '', $telefonoOriginal);

        if (empty($telefonoSanitizado)) {
            throw ValidationException::withMessages([
                'ProveedorTelefono' => "El proveedor '{$proveedor->ProveedorRazonSocial}' no cuenta con teléfono registrado. Actualícelo en el módulo de proveedores.",
            ]);
        }

        // Si es número celular peruano de 9 dígitos que empieza con 9, anteponer código de país 51
        if (strlen($telefonoSanitizado) === 9 && str_starts_with($telefonoSanitizado, '9')) {
            $telefonoWhatsapp = '51' . $telefonoSanitizado;
        } elseif (str_starts_with($telefonoSanitizado, '51') && strlen($telefonoSanitizado) === 11) {
            $telefonoWhatsapp = $telefonoSanitizado;
        } else {
            $telefonoWhatsapp = str_starts_with($telefonoSanitizado, '51') ? $telefonoSanitizado : ('51' . $telefonoSanitizado);
        }

        $pdfUrl = url("/api/ordenes-compra/{$orden->Orden_CompraId}/pdf");

        $fecha = $orden->Orden_CompraFecha 
            ? \Carbon\Carbon::parse($orden->Orden_CompraFecha)->format('d/m/Y') 
            : date('d/m/Y');

        $empresa = \App\Models\Empresa::getDatos();
        $nombreEmpresa = $empresa?->EmpresaNombreComercial ?: ($empresa?->EmpresaRazonSocial ?: 'Comercial Valencia');

        // Mensaje estructurado completo con ítems, totales y enlace directo al PDF oficial
        $lineas = [];
        $lineas[] = "Estimado(a) *{$proveedor->ProveedorRazonSocial}*:";
        $lineas[] = "Le saludamos de *{$nombreEmpresa}*. Le hacemos entrega de la siguiente Orden de Compra formal:";
        $lineas[] = "";
        $lineas[] = "📦 *ORDEN DE COMPRA:* {$orden->Orden_CompraId}";
        $lineas[] = "📅 *Fecha:* {$fecha}";
        $lineas[] = "";
        $lineas[] = "📋 *Detalle de Productos Solicitados:*";

        if ($orden->detalles && $orden->detalles->isNotEmpty()) {
            foreach ($orden->detalles as $det) {
                $cant = (float) $det->Detalle_Orden_CompraCantidad;
                $unidad = $det->unidadMedida?->unidades_medidaAbreviatura 
                    ?? $det->unidadMedida?->unidades_medidaDescripcionUnidades 
                    ?? 'UND';
                $prodNombre = $det->producto?->ProductoNombre ?? 'Producto';
                $marca = !empty($det->producto?->ProductoMarca) ? " [{$det->producto->ProductoMarca}]" : "";
                $lineas[] = "• {$cant} {$unidad} - {$prodNombre}{$marca}";
            }
        }

        $lineas[] = "";
        $lineas[] = "📦 *Total de productos solicitados:* " . count($orden->detalles);

        if (!empty($orden->Orden_CompraObservacion) && !str_contains($orden->Orden_CompraObservacion, 'WhatsApp')) {
            $lineas[] = "";
            $lineas[] = "📝 *Nota / Instrucción:* {$orden->Orden_CompraObservacion}";
        }

        $lineas[] = "";
        $lineas[] = "📄 *PDF Oficial de la Orden:*";
        $lineas[] = $pdfUrl;
        $lineas[] = "";
        $lineas[] = "Agradecemos confirmar la recepción de este pedido y la fecha estimada de entrega. ¡Muchas gracias!";

        $mensaje = implode("\n", $lineas);
        $url = "https://wa.me/{$telefonoWhatsapp}?text=" . rawurlencode($mensaje);

        return [
            'orden_id' => $orden->Orden_CompraId,
            'proveedor' => [
                'id' => $proveedor->ProveedorId,
                'razon_social' => $proveedor->ProveedorRazonSocial,
                'ruc' => $proveedor->ProveedorRuc,
                'telefono' => $telefonoOriginal,
                'telefono_whatsapp' => $telefonoWhatsapp,
            ],
            'pdf_url' => $pdfUrl,
            'mensaje' => $mensaje,
            'whatsapp_url' => $url,
        ];
    }

    /**
     * Retornar datos y enlace para envío por WhatsApp.
     */
    public function registrarEnvioWhatsApp(string $id): array
    {
        return $this->generarDatosWhatsApp($id);
    }

    /**
     * Generar documento PDF individual de la Orden de Compra.
     */
    public function generarPdf(string $id, bool $download = false)
    {
        $orden = $this->obtenerOrdenPorId($id);
        $empresa = \App\Models\Empresa::getDatos();

        $html = view('pdf.orden_compra', compact('orden', 'empresa'))->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => true, 'defaultFont' => 'sans-serif']);

        $filename = "Orden_Compra_{$orden->Orden_CompraId}.pdf";
        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Generar reporte consolidado de Órdenes de Compra en formato PDF.
     */
    public function generarReporteConsolidadoPdf(array $filtros)
    {
        $query = OrdenCompra::with(['proveedor', 'detalles.producto'])
            ->where('Orden_CompraEliminado', 'N');

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('Orden_CompraFecha', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('Orden_CompraFecha', '<=', $filtros['fecha_hasta']);
        }
        if (!empty($filtros['estado'])) {
            $query->where('Orden_CompraEstado', strtoupper($filtros['estado']));
        }
        if (!empty($filtros['proveedor_id'])) {
            $query->where('Orden_Compra_ProveedorId', $filtros['proveedor_id']);
        }

        $ordenes = $query->orderBy('Orden_CompraFecha', 'desc')->get();

        $totalComprado = (float) $ordenes->sum('Orden_CompraTotal');
        $totalSubtotal = (float) $ordenes->sum('Orden_CompraSubtotal');
        $totalIgv = (float) $ordenes->sum('Orden_CompraIgv');

        $porEstado = [
            'P' => ['cantidad' => 0, 'total' => 0.0],
            'C' => ['cantidad' => 0, 'total' => 0.0],
            'A' => ['cantidad' => 0, 'total' => 0.0],
        ];

        foreach ($ordenes as $o) {
            $est = $o->Orden_CompraEstado ?: 'P';
            if (isset($porEstado[$est])) {
                $porEstado[$est]['cantidad']++;
                $porEstado[$est]['total'] += (float) $o->Orden_CompraTotal;
            }
        }

        // Agrupado por proveedor
        $porProveedor = $ordenes->groupBy('Orden_Compra_ProveedorId')->map(function ($grupo) {
            $primero = $grupo->first();
            return [
                'proveedor_id' => $primero->Orden_Compra_ProveedorId ?: 'S/P',
                'razon_social' => $primero->proveedor->ProveedorRazonSocial ?? 'Sin Proveedor Asignado',
                'ruc' => $primero->proveedor->ProveedorRuc ?? '-',
                'telefono' => $primero->proveedor->ProveedorTelefono ?? '-',
                'cantidad_ordenes' => $grupo->count(),
                'total_soles' => (float) $grupo->sum('Orden_CompraTotal'),
            ];
        })->values()->sortByDesc('total_soles')->all();

        $kpis = [
            'total_ordenes' => $ordenes->count(),
            'total_comprado' => $totalComprado,
            'total_subtotal' => $totalSubtotal,
            'total_igv' => $totalIgv,
            'por_estado' => $porEstado,
            'por_proveedor' => $porProveedor,
        ];

        $empresa = \App\Models\Empresa::getDatos();
        $html = view('pdf.reporte_ordenes_compra', compact('ordenes', 'kpis', 'filtros', 'empresa'))->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => true, 'defaultFont' => 'sans-serif']);

        $filename = 'Reporte_Ordenes_Compra_' . now()->format('Y-m-d_His') . '.pdf';
        return $pdf->stream($filename);
    }
}
