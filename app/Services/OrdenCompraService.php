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
        protected OrdenCompraRepositoryInterface $ordenCompraRepository
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
     * NOTA: Este módulo es exclusivamente comercial y administrativo (gestión con el proveedor).
     * No altera stock físico ni genera registros en el Kardex.
     */
    public function obtenerEstados(): array
    {
        return [
            ['codigo' => 'P', 'nombre' => 'Pendiente', 'descripcion' => 'Orden emitida y pendiente de atención por el proveedor'],
            ['codigo' => 'C', 'nombre' => 'Completada', 'descripcion' => 'Orden de compra atendida por el proveedor'],
            ['codigo' => 'A', 'nombre' => 'Anulada', 'descripcion' => 'Orden de compra cancelada / sin efecto'],
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

                $producto = Producto::where('ProductoId', $productoId)
                    ->where('ProductoEliminado', 'N')
                    ->first();

                if (!$producto) {
                    throw ValidationException::withMessages([
                        "detalles.{$index}.Detalle_ProductoId" => "El producto '{$productoId}' no existe o está inactivo.",
                    ]);
                }

                if (empty($unidadId)) {
                    // Fallback a la primera unidad de medida del sistema si no se especificó
                    $unidad = UnidadesMedida::where('unidades_medidaEliminado', 'N')->first();
                    $unidadId = $unidad ? $unidad->unidades_medidaId : 'UND-00001';
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
                ];
            }

            // Calcular IGV (18% estándar en Perú) y Total
            $subtotal = round($subtotalCalculado, 2);
            $igv = isset($datos['Orden_CompraIgv']) ? (float) $datos['Orden_CompraIgv'] : round($subtotal * 0.18, 2);
            $total = round($subtotal + $igv, 2);

            $fecha = !empty($datos['Orden_CompraFecha']) ? $datos['Orden_CompraFecha'] : now();
            $observacion = $datos['Orden_CompraObservacion'] ?? null;
            $estado = strtoupper($datos['Orden_CompraEstado'] ?? 'P');
            if (!in_array($estado, ['P', 'C', 'A'])) {
                $estado = 'P';
            }

            // 1. Crear Cabecera de Orden_Compra
            $ordenData = [
                'Orden_CompraFecha' => $fecha,
                'Orden_CompraSubtotal' => $subtotal,
                'Orden_CompraIgv' => $igv,
                'Orden_CompraTotal' => $total,
                'Orden_CompraEstado' => $estado,
                'Orden_CompraObservacion' => $observacion,
                'Orden_Compra_ProveedorId' => $proveedorId,
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
     * Cambiar el estado de una orden de compra ('P', 'C', 'A').
     * NOTA DE ARQUITECTURA:
     * Las órdenes de compra NO impactan el Kardex ni suman stock automáticamente.
     * La recepción física real con verificación de cantidades se maneja separadamente en almacén.
     */
    public function cambiarEstado(string $id, string $nuevoEstado, ?string $motivo = null): OrdenCompra
    {
        return DB::transaction(function () use ($id, $nuevoEstado, $motivo) {
            $orden = $this->obtenerOrdenPorId($id);
            $nuevoEstado = strtoupper($nuevoEstado);

            if (!in_array($nuevoEstado, ['P', 'C', 'A'])) {
                throw ValidationException::withMessages([
                    'estado' => "Estado '{$nuevoEstado}' no es válido. Opciones permitidas: 'P' (Pendiente), 'C' (Completada), 'A' (Anulada).",
                ]);
            }

            $estadoActual = $orden->Orden_CompraEstado;

            if ($estadoActual === $nuevoEstado) {
                return $orden;
            }

            // Transición a 'C' (Completada / Atendida por el proveedor - Ingreso a almacén)
            if ($nuevoEstado === 'C') {
                if ($estadoActual === 'A') {
                    throw ValidationException::withMessages([
                        'estado' => 'No se puede completar una orden que ha sido previamente anulada.',
                    ]);
                }

                if (empty($orden->Orden_Compra_ProveedorId)) {
                    throw ValidationException::withMessages([
                        'Orden_Compra_ProveedorId' => 'Debe asignar un proveedor a la orden antes de darla por completada.',
                    ]);
                }

                // Vinculación comercial de catálogo Producto_Proveedor
                $this->vincularProductosProveedor($orden);

                // Ingresar mercadería a almacén y registrar movimientos de Entrada 'E' en Kardex
                foreach ($orden->detalles as $detalle) {
                    $producto = Producto::where('ProductoId', $detalle->Detalle_ProductoId)
                        ->where('ProductoEliminado', 'N')
                        ->first();
                    if ($producto) {
                        $cantidadComprada = (float) $detalle->Detalle_Orden_CompraCantidad;
                        $precioUnitario = (float) $detalle->Detalle_Orden_CompraPrecioUnitario;

                        // Toda la cantidad ingresa a stock físico
                        $producto->increment('ProductoStockActual', $cantidadComprada);

                        $nuevoSaldo = (float) $producto->fresh()->ProductoStockActual;

                        // Registrar movimiento de Entrada en Kárdex
                        $movId = app(InventarioService::class)->getNextMovimientoId();
                        $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

                        MovimientoProducto::create(array_merge([
                            'Movimiento_productoId' => $movId,
                            'Movimiento_productoCantidadPresentacion' => (string) $cantidadComprada,
                            'Movimiento_productoDocumentoOperacionId' => $orden->Orden_CompraId,
                            'Movimiento_productoTipoMovimiento' => 'E', // E = Entrada por Orden de Compra
                            'Movimiento_productoCostoPrecioUnitario' => (string) $precioUnitario,
                            'Movimiento_productoCantidadEntrada' => (string) $cantidadComprada,
                            'Movimiento_productoCantidadSalida' => '0',
                            'Movimiento_productoCantidadSaldo' => (string) $nuevoSaldo,
                            'Movimiento_productoFecha_Movimiento' => now(),
                            'Movimiento_producto_ProductoId' => $producto->ProductoId,
                            'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $detalle->Detalle_UnidadMedidaId ?? 'UND-00001',
                            'Movimiento_productoEliminado' => 'N',
                        ], $auditMov));
                    }
                }

                $this->ordenCompraRepository->update($id, ['Orden_CompraEstado' => 'C']);
            }
            // Transición a 'A' (Anulada)
            elseif ($nuevoEstado === 'A') {
                // Si la orden ya había sido completada e ingresada a almacén, revertir el stock en Kárdex
                if ($estadoActual === 'C') {
                    foreach ($orden->detalles as $detalle) {
                        $producto = Producto::where('ProductoId', $detalle->Detalle_ProductoId)
                            ->where('ProductoEliminado', 'N')
                            ->first();
                        if ($producto) {
                            $cantidad = (float) $detalle->Detalle_Orden_CompraCantidad;
                            $producto->decrement('ProductoStockActual', $cantidad);
                            $nuevoSaldo = (float) $producto->fresh()->ProductoStockActual;

                            $movId = app(InventarioService::class)->getNextMovimientoId();
                            $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

                            MovimientoProducto::create(array_merge([
                                'Movimiento_productoId' => $movId,
                                'Movimiento_productoCantidadPresentacion' => (string) $cantidad,
                                'Movimiento_productoDocumentoOperacionId' => "ANUL-{$orden->Orden_CompraId}",
                                'Movimiento_productoTipoMovimiento' => 'S', // Salida por anulación de compra
                                'Movimiento_productoCostoPrecioUnitario' => (string) $detalle->Detalle_Orden_CompraPrecioUnitario,
                                'Movimiento_productoCantidadEntrada' => '0',
                                'Movimiento_productoCantidadSalida' => (string) $cantidad,
                                'Movimiento_productoCantidadSaldo' => (string) $nuevoSaldo,
                                'Movimiento_productoFecha_Movimiento' => now(),
                                'Movimiento_producto_ProductoId' => $producto->ProductoId,
                                'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $detalle->Detalle_UnidadMedidaId ?? 'UND-00001',
                                'Movimiento_productoEliminado' => 'N',
                            ], $auditMov));
                        }
                    }
                }

                $updateData = ['Orden_CompraEstado' => 'A'];
                if ($motivo) {
                    $obs = trim(($orden->Orden_CompraObservacion ?? '') . " [Anulada: {$motivo}]");
                    $updateData['Orden_CompraObservacion'] = mb_substr($obs, 0, 250);
                }

                $this->ordenCompraRepository->update($id, $updateData);
            }
            // Revertir a Pendiente 'P'
            elseif ($nuevoEstado === 'P') {
                if ($estadoActual === 'C') {
                    // Revertir el stock ingresado previamente
                    foreach ($orden->detalles as $detalle) {
                        $producto = Producto::where('ProductoId', $detalle->Detalle_ProductoId)
                            ->where('ProductoEliminado', 'N')
                            ->first();
                        if ($producto) {
                            $cantidad = (float) $detalle->Detalle_Orden_CompraCantidad;
                            $producto->decrement('ProductoStockActual', $cantidad);
                            $nuevoSaldo = (float) $producto->fresh()->ProductoStockActual;

                            $movId = app(InventarioService::class)->getNextMovimientoId();
                            $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

                            MovimientoProducto::create(array_merge([
                                'Movimiento_productoId' => $movId,
                                'Movimiento_productoCantidadPresentacion' => (string) $cantidad,
                                'Movimiento_productoDocumentoOperacionId' => "REV-{$orden->Orden_CompraId}",
                                'Movimiento_productoTipoMovimiento' => 'S',
                                'Movimiento_productoCostoPrecioUnitario' => (string) $detalle->Detalle_Orden_CompraPrecioUnitario,
                                'Movimiento_productoCantidadEntrada' => '0',
                                'Movimiento_productoCantidadSalida' => (string) $cantidad,
                                'Movimiento_productoCantidadSaldo' => (string) $nuevoSaldo,
                                'Movimiento_productoFecha_Movimiento' => now(),
                                'Movimiento_producto_ProductoId' => $producto->ProductoId,
                                'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $detalle->Detalle_UnidadMedidaId ?? 'UND-00001',
                                'Movimiento_productoEliminado' => 'N',
                            ], $auditMov));
                        }
                    }
                }

                $this->ordenCompraRepository->update($id, ['Orden_CompraEstado' => 'P']);
            }

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
     * Eliminación lógica (Soft-Delete) de una orden de compra.
     */
    public function eliminarOrden(string $id): bool
    {
        $orden = $this->obtenerOrdenPorId($id);

        // Marcar detalles como eliminados
        DetalleOrdenCompra::where('Detalle_Orden_Compra_Orden_CompraId', $id)
            ->update(AuditHelper::getDeletionAudit('Detalle_Orden_Compra'));

        return $this->ordenCompraRepository->delete($id);
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
                $pu = number_format((float) $det->Detalle_Orden_CompraPrecioUnitario, 2);
                $subtotal = number_format((float) $det->Detalle_Orden_CompraSubtotal, 2);
                $lineas[] = "• {$cant} {$unidad} - {$prodNombre} (P.U: S/ {$pu} | Total: S/ {$subtotal})";
            }
        }

        $lineas[] = "";
        $lineas[] = "💰 *Total de la Orden:* S/ " . number_format((float) $orden->Orden_CompraTotal, 2);

        if (!empty($orden->Orden_CompraObservacion) && !str_contains($orden->Orden_CompraObservacion, 'WhatsApp')) {
            $lineas[] = "";
            $lineas[] = "📝 *Nota:* {$orden->Orden_CompraObservacion}";
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
