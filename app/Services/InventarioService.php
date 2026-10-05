<?php

namespace App\Services;

use App\Models\CategoriaProducto;
use App\Models\DetalleProductoMedida;
use App\Models\MovimientoProducto;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use App\Models\ProductoUbi;
use App\Models\Proveedor;
use App\Models\UnidadesMedida;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    /**
     * List paginated or filtered products.
     */
    public function listarProductos(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Producto::with([
            'categoria',
            'ubicacion',
            'detalleProductoMedidas.unidadMedida',
            'proveedores',
        ]);

        if (!empty($filters['eliminados']) && ($filters['eliminados'] === 'true' || $filters['eliminados'] === '1' || $filters['eliminados'] === 1 || $filters['eliminados'] === true)) {
            $query->where('ProductoEliminado', 'S');
        } elseif (!empty($filters['todos']) && ($filters['todos'] === 'true' || $filters['todos'] === '1' || $filters['todos'] === 1 || $filters['todos'] === true)) {
            // No filtrar por ProductoEliminado
        } else {
            $query->where('ProductoEliminado', 'N');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ProductoNombre', 'like', "%{$search}%")
                  ->orWhere('ProductoMarca', 'like', "%{$search}%")
                  ->orWhere('producto_descripcion', 'like', "%{$search}%")
                  ->orWhere('ProductoId', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['categoriaId'])) {
            $query->where('Producto_Categoria_ProductoId', $filters['categoriaId']);
        }

        if (!empty($filters['ubicacionId'])) {
            $query->where('Producto_producto_ubi_id', $filters['ubicacionId']);
        }

        if (!empty($filters['marca'])) {
            $query->where('ProductoMarca', 'like', "%{$filters['marca']}%");
        }

        if (!empty($filters['estado'])) {
            $query->where('ProductoEstado', $filters['estado']);
        }

        if (!empty($filters['stockBajo']) && filter_var($filters['stockBajo'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereRaw('CAST(ProductoStockActual AS FLOAT) <= CAST(ProductoStockMinimo AS FLOAT)');
        }

        $query->orderBy('ProductoFechaCreacion', 'desc');

        $result = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        // Enriquecer con campos calculados en backend
        $enrich = function ($p) {
            $stock = (float) $p->ProductoStockActual;
            $minimo = (float) $p->ProductoStockMinimo;

            if ($stock <= 0) {
                $alertaStock = 'AGOTADO';
                $alertaColor = 'rose';
            } elseif ($stock <= $minimo) {
                $alertaStock = 'STOCK_MINIMO';
                $alertaColor = 'amber';
            } else {
                $alertaStock = 'NORMAL';
                $alertaColor = 'emerald';
            }

            $detalleBase = $p->detalleProductoMedidas->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
                ?? $p->detalleProductoMedidas->first();
            $unidadBaseAbrev = $detalleBase?->unidadMedida?->unidades_medidaAbreviatura
                ?: ($detalleBase?->unidadMedida?->unidades_medidaDescripcionUnidades ?: 'UND');

            $p->stock_actual_formateado = number_format($stock, $stock == (int) $stock ? 0 : 2) . ' ' . $unidadBaseAbrev;
            $p->alerta_stock = $alertaStock;
            $p->alerta_color = $alertaColor;
            $p->categoria_nombre = $p->categoria->Categoria_ProductoDescripcion_categoria ?? 'Sin Categoría';
            $p->ubicacion_descripcion = $p->ubicacion?->producto_ubi_descripcion ?? 'Sin Ubicación';
            $p->ubicacion_observacion = $p->ubicacion?->producto_ubi_observacion;
            $p->unidad_base_nombre = $detalleBase?->unidadMedida?->unidades_medidaDescripcionUnidades ?? 'Unidad';
            $p->unidad_base_abreviatura = $unidadBaseAbrev;
            $p->precio_compra_base = (float) ($detalleBase?->Detalle_Producto_medida_precio_compra ?? 0);
            $p->precio_venta_base = (float) ($detalleBase?->Detalle_Producto_medida_precio_venta ?? 0);
            $p->precio_compra_base_formateado = 'S/ ' . number_format($p->precio_compra_base, 2);
            $p->precio_venta_base_formateado = 'S/ ' . number_format($p->precio_venta_base, 2);
            $p->stock_total_vendible = (float) ($p->ProductoStockActual ?? 0);

            return $p;
        };

        if ($result instanceof LengthAwarePaginator) {
            $result->getCollection()->transform($enrich);
        } else {
            $result->transform($enrich);
        }

        return $result;
    }

    /**
     * Get a specific product by ID with details and conversions.
     */
    public function obtenerProductoPorId(string $id): Producto
    {
        $producto = Producto::with([
            'categoria',
            'ubicacion',
            'detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
            },
            'proveedores',
        ])->where('ProductoId', $id)
          ->where('ProductoEliminado', 'N')
          ->first();

        if (!$producto) {
            throw new Exception("Producto con ID '{$id}' no encontrado.");
        }

        return $producto;
    }

    /**
     * Create a new product with required details (at least one with factor=1).
     * Transactional operation.
     */
    public function crearProducto(array $datosProducto, array $detalles, array $proveedorIds = []): Producto
    {
        return DB::transaction(function () use ($datosProducto, $detalles, $proveedorIds) {
            // 1. Validar que exista al menos 1 detalle con factor de conversión = 1 (Unidad Base)
            if (empty($detalles)) {
                throw ValidationException::withMessages([
                    'detalles' => 'El producto debe incluir al menos una unidad de medida.',
                ]);
            }

            $tieneUnidadBase = false;
            foreach ($detalles as $det) {
                $factor = (int) ($det['factor_conversion'] ?? $det['Detalle_Producto_medida_factor_conversion'] ?? 0);
                if ($factor === 1) {
                    $tieneUnidadBase = true;
                    break;
                }
            }

            if (!$tieneUnidadBase) {
                throw ValidationException::withMessages([
                    'detalles' => 'El producto debe incluir obligatoriamente al menos una unidad de medida base con factor de conversión igual a 1.',
                ]);
            }

            // 2. Generar ProductoId correlativo
            $productoId = $this->getNextProductoId();
            $auditProducto = AuditHelper::getCreationAudit('Producto');

            $stockInicial = (float) ($datosProducto['stockInicial'] ?? $datosProducto['ProductoStockActual'] ?? 0);

            $payloadProducto = [
                'ProductoId' => $productoId,
                'ProductoNombre' => $datosProducto['ProductoNombre'],
                'Producto_Categoria_ProductoId' => $datosProducto['Producto_Categoria_ProductoId'],
                'ProductoMarca' => $datosProducto['ProductoMarca'] ?? 'Genérico',
                'producto_descripcion' => !empty(trim($datosProducto['producto_descripcion'] ?? ''))
                    ? trim($datosProducto['producto_descripcion'])
                    : 'No se ingresó descripción',
                'producto_imagen' => !empty(trim($datosProducto['producto_imagen'] ?? ''))
                    ? trim($datosProducto['producto_imagen'])
                    : 'Sin imagenes, ',
                'Producto_producto_ubi_id' => $datosProducto['Producto_producto_ubi_id'] ?? null,
                'ProductoStockActual' => (string) $stockInicial,
                'ProductoStockMinimo' => (string) ($datosProducto['ProductoStockMinimo'] ?? 5),
                'ProductoStockMaximo' => (string) ($datosProducto['ProductoStockMaximo'] ?? 1000),
                'ProductoEstado' => $datosProducto['ProductoEstado'] ?? 'A',
                'ProductoEliminado' => 'N',
            ];
            $payloadProducto = array_merge($payloadProducto, $auditProducto);

            // 3. Crear Producto Padre
            $producto = Producto::create($payloadProducto);

            // 4. Crear Detalles de Unidades de Medida
            $unidadBaseId = null;
            $auditDetalle = AuditHelper::getCreationAudit('Detalle_Producto_medida');

            foreach ($detalles as $det) {
                $unidadesMedidaId = $det['unidades_medidaId'] ?? $det['Detalle_Producto_medida_unidades_medidaId'];
                $factor = (int) ($det['factor_conversion'] ?? $det['Detalle_Producto_medida_factor_conversion']);
                $precioVenta = (string) ($det['precio_venta'] ?? $det['Detalle_Producto_medida_precio_venta'] ?? '0.00');
                $precioCompra = (string) ($det['precio_compra'] ?? $det['Detalle_Producto_medida_precio_compra'] ?? '0.00');

                if ($factor === 1 && !$unidadBaseId) {
                    $unidadBaseId = $unidadesMedidaId;
                }

                $payloadDetalle = [
                    'Detalle_Producto_medida_ProductoId' => $productoId,
                    'Detalle_Producto_medida_unidades_medidaId' => $unidadesMedidaId,
                    'Detalle_Producto_medida_factor_conversion' => $factor,
                    'Detalle_Producto_medida_precio_venta' => $precioVenta,
                    'Detalle_Producto_medida_precio_compra' => $precioCompra,
                    'Detalle_Producto_medidaEliminado' => 'N',
                ];
                $payloadDetalle = array_merge($payloadDetalle, $auditDetalle);

                DetalleProductoMedida::create($payloadDetalle);
            }

            // 5. Asociar Proveedores si se envían
            if (!empty($proveedorIds)) {
                $auditProv = AuditHelper::getCreationAudit('Producto_Proveedor');
                foreach ($proveedorIds as $provId) {
                    ProductoProveedor::create(array_merge([
                        'Producto_Proveedor_ProveedorId' => $provId,
                        'Producto_Proveedor_ProductoId' => $productoId,
                        'Producto_ProveedorEliminado' => 'N',
                    ], $auditProv));
                }
            }

            // 6. Si se definió un stock inicial > 0, registrar automáticamente el movimiento de entrada en el Kardex
            if ($stockInicial > 0 && $unidadBaseId) {
                $movimientoId = $this->getNextMovimientoId();
                $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

                $payloadMov = [
                    'Movimiento_productoId' => $movimientoId,
                    'Movimiento_productoCantidadPresentacion' => (string) $stockInicial,
                    'Movimiento_productoDocumentoOperacionId' => 'INV-INICIAL',
                    'Movimiento_productoTipoMovimiento' => 'E',
                    'Movimiento_productoCostoPrecioUnitario' => $detalles[0]['precio_compra'] ?? '0.00',
                    'Movimiento_productoCantidadEntrada' => (string) $stockInicial,
                    'Movimiento_productoCantidadSalida' => '0',
                    'Movimiento_productoCantidadSaldo' => (string) $stockInicial,
                    'Movimiento_productoFecha_Movimiento' => now(),
                    'Movimiento_producto_ProductoId' => $productoId,
                    'Movimiento_producto_Detalle_Producto_medida_ProductoId' => $productoId,
                    'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $unidadBaseId,
                    'Movimiento_productoEliminado' => 'N',
                ];
                $payloadMov = array_merge($payloadMov, $auditMov);

                MovimientoProducto::create($payloadMov);
            }

            return $producto->fresh([
                'categoria',
                'ubicacion',
                'detalleProductoMedidas.unidadMedida',
                'proveedores',
            ]);
        });
    }

    /**
     * Update an existing product and synchronize its presentation details.
     * Transactional operation.
     */
    public function actualizarProducto(
        string $id,
        array $datosProducto,
        ?array $detalles = null,
        ?array $proveedorIds = null
    ): Producto {
        return DB::transaction(function () use ($id, $datosProducto, $detalles, $proveedorIds) {
            $producto = $this->obtenerProductoPorId($id);

            // 1. Actualizar Producto padre con auditoría
            if (array_key_exists('producto_descripcion', $datosProducto)) {
                $datosProducto['producto_descripcion'] = !empty(trim($datosProducto['producto_descripcion'] ?? ''))
                    ? trim($datosProducto['producto_descripcion'])
                    : 'No se ingresó descripción';
            }
            if (array_key_exists('producto_imagen', $datosProducto)) {
                $datosProducto['producto_imagen'] = !empty(trim($datosProducto['producto_imagen'] ?? ''))
                    ? trim($datosProducto['producto_imagen'])
                    : 'Sin imagenes, ';
            }
            $auditProducto = AuditHelper::getModificationAudit('Producto');
            $payloadProducto = array_merge($datosProducto, $auditProducto);

            $producto->update($payloadProducto);

            // 2. Si se envían detalles, validar y sincronizar
            if ($detalles !== null) {
                if (empty($detalles)) {
                    throw ValidationException::withMessages([
                        'detalles' => 'El producto no puede quedarse sin unidades de medida.',
                    ]);
                }

                $tieneUnidadBase = false;
                foreach ($detalles as $det) {
                    $factor = (int) ($det['factor_conversion'] ?? $det['Detalle_Producto_medida_factor_conversion'] ?? 0);
                    if ($factor === 1) {
                        $tieneUnidadBase = true;
                        break;
                    }
                }

                if (!$tieneUnidadBase) {
                    throw ValidationException::withMessages([
                        'detalles' => 'El producto debe incluir obligatoriamente al menos una unidad de medida base con factor de conversión igual a 1.',
                    ]);
                }

                // Sincronizar o crear cada detalle
                $auditDetalleMod = AuditHelper::getModificationAudit('Detalle_Producto_medida');
                $auditDetalleCre = AuditHelper::getCreationAudit('Detalle_Producto_medida');
                $auditDetalleDel = AuditHelper::getDeletionAudit('Detalle_Producto_medida');

                $medidasEnviadasIds = [];

                foreach ($detalles as $det) {
                    $unidadesMedidaId = $det['unidades_medidaId'] ?? $det['Detalle_Producto_medida_unidades_medidaId'];
                    $factor = (int) ($det['factor_conversion'] ?? $det['Detalle_Producto_medida_factor_conversion']);
                    $precioVenta = (string) ($det['precio_venta'] ?? $det['Detalle_Producto_medida_precio_venta'] ?? '0.00');
                    $precioCompra = (string) ($det['precio_compra'] ?? $det['Detalle_Producto_medida_precio_compra'] ?? '0.00');

                    $medidasEnviadasIds[] = $unidadesMedidaId;

                    $detalleExistente = DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $id)
                        ->where('Detalle_Producto_medida_unidades_medidaId', $unidadesMedidaId)
                        ->first();

                    if ($detalleExistente) {
                        DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $id)
                            ->where('Detalle_Producto_medida_unidades_medidaId', $unidadesMedidaId)
                            ->update(array_merge([
                                'Detalle_Producto_medida_factor_conversion' => $factor,
                                'Detalle_Producto_medida_precio_venta' => $precioVenta,
                                'Detalle_Producto_medida_precio_compra' => $precioCompra,
                                'Detalle_Producto_medidaEliminado' => 'N',
                            ], $auditDetalleMod));
                    } else {
                        DetalleProductoMedida::create(array_merge([
                            'Detalle_Producto_medida_ProductoId' => $id,
                            'Detalle_Producto_medida_unidades_medidaId' => $unidadesMedidaId,
                            'Detalle_Producto_medida_factor_conversion' => $factor,
                            'Detalle_Producto_medida_precio_venta' => $precioVenta,
                            'Detalle_Producto_medida_precio_compra' => $precioCompra,
                            'Detalle_Producto_medidaEliminado' => 'N',
                        ], $auditDetalleCre));
                    }
                }

                // Desactivar / Eliminar lógicamente las presentaciones que NO fueron enviadas
                DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $id)
                    ->whereNotIn('Detalle_Producto_medida_unidades_medidaId', $medidasEnviadasIds)
                    ->where('Detalle_Producto_medidaEliminado', 'N')
                    ->update(array_merge([
                        'Detalle_Producto_medidaEliminado' => 'S',
                    ], $auditDetalleDel));
            }

            // 3. Sincronizar Proveedores si se envían
            if ($proveedorIds !== null) {
                // Eliminar relaciones no incluidas
                ProductoProveedor::where('Producto_Proveedor_ProductoId', $id)->delete();

                $auditProv = AuditHelper::getCreationAudit('Producto_Proveedor');
                foreach ($proveedorIds as $provId) {
                    ProductoProveedor::create(array_merge([
                        'Producto_Proveedor_ProveedorId' => $provId,
                        'Producto_Proveedor_ProductoId' => $id,
                        'Producto_ProveedorEliminado' => 'N',
                    ], $auditProv));
                }
            }

            return $producto->fresh([
                'categoria',
                'ubicacion',
                'detalleProductoMedidas.unidadMedida',
                'proveedores',
            ]);
        });
    }

    /**
     * Soft delete (logical delete) a product and its presentation details.
     */
    public function eliminarProductoLogico(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $producto = $this->obtenerProductoPorId($id);

            $auditProducto = AuditHelper::getDeletionAudit('Producto');
            $producto->update(array_merge([
                'ProductoEliminado' => 'S',
                'ProductoEstado' => 'I',
            ], $auditProducto));

            $auditDetalle = AuditHelper::getDeletionAudit('Detalle_Producto_medida');
            DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $id)
                ->update(array_merge([
                    'Detalle_Producto_medidaEliminado' => 'S',
                ], $auditDetalle));

            return true;
        });
    }

    /**
     * Restore a logically deleted product and its presentation details.
     */
    public function restaurarProducto(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $producto = Producto::where('ProductoId', $id)->first();
            if (!$producto) {
                throw new Exception("Producto con ID '{$id}' no encontrado.");
            }

            $auditProducto = AuditHelper::getModificationAudit('Producto');
            $producto->update(array_merge([
                'ProductoEliminado' => 'N',
                'ProductoEstado' => 'A',
                'ProductoUsuarioEliminacion' => null,
                'ProductoHostEliminacion' => null,
                'ProductoFechaEliminacion' => null,
            ], $auditProducto));

            $auditDetalle = AuditHelper::getModificationAudit('Detalle_Producto_medida');
            DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $id)
                ->update(array_merge([
                    'Detalle_Producto_medidaEliminado' => 'N',
                    'Detalle_Producto_medidaUsuarioEliminacion' => null,
                    'Detalle_Producto_medidaHostEliminacion' => null,
                    'Detalle_Producto_medidaFechaEliminacion' => null,
                ], $auditDetalle));

            return true;
        });
    }

    /**
     * Fast update for product status (Active/Inactive).
     */
    public function cambiarEstadoProducto(string $id, string $nuevoEstado): Producto
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $producto = $this->obtenerProductoPorId($id);

            $auditProducto = AuditHelper::getModificationAudit('Producto');
            $producto->update(array_merge([
                'ProductoEstado' => $nuevoEstado,
            ], $auditProducto));

            return $producto;
        });
    }

    /**
     * Register an inventory movement (Kardex) and automatically update physical stock.
     * Transactional operation.
     */
    public function registrarMovimiento(array $datos): MovimientoProducto
    {
        return DB::transaction(function () use ($datos) {
            $productoId = $datos['productoId'] ?? $datos['ProductoId'] ?? null;
            $unidadesMedidaId = $datos['unidadesMedidaId'] ?? $datos['unidades_medidaId'] ?? null;
            $tipoMovimiento = strtoupper($datos['tipoMovimiento'] ?? $datos['Movimiento_productoTipoMovimiento'] ?? '');
            $cantidadPresentacion = (float) ($datos['cantidad'] ?? $datos['Movimiento_productoCantidadPresentacion'] ?? 0);
            $documentoOperacionId = $datos['documentoOperacionId'] ?? $datos['Movimiento_productoDocumentoOperacionId'] ?? ('DOC-' . strtoupper(uniqid()));

            if (!$productoId || !$unidadesMedidaId) {
                throw ValidationException::withMessages([
                    'productoId' => 'Debe especificar el producto y la unidad de medida.',
                ]);
            }

            if (!in_array($tipoMovimiento, ['E', 'S'], true)) {
                throw ValidationException::withMessages([
                    'tipoMovimiento' => 'El tipo de movimiento debe ser E (Entrada) o S (Salida).',
                ]);
            }

            if ($cantidadPresentacion <= 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La cantidad a mover debe ser un número positivo mayor a 0.',
                ]);
            }

            // 1. Obtener Producto activo
            $producto = $this->obtenerProductoPorId($productoId);

            // 2. Obtener Detalle_Producto_medida para extraer factor de conversión y precios
            $detalle = DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $productoId)
                ->where('Detalle_Producto_medida_unidades_medidaId', $unidadesMedidaId)
                ->where('Detalle_Producto_medidaEliminado', 'N')
                ->first();

            if (!$detalle) {
                throw ValidationException::withMessages([
                    'unidadesMedidaId' => "La unidad de medida seleccionada ('{$unidadesMedidaId}') no está asociada al producto '{$producto->ProductoNombre}'.",
                ]);
            }

            $factor = (int) $detalle->Detalle_Producto_medida_factor_conversion;
            if ($factor <= 0) {
                $factor = 1;
            }

            // 3. Multiplicar la cantidad enviada por el factor para obtener la cantidad real en unidades base
            $cantidadReal = $cantidadPresentacion * $factor;

            // 4. Determinar costo/precio unitario según tipo de movimiento
            if ($tipoMovimiento === 'E') {
                $costoPrecioUnitario = (string) ($datos['precioUnitario'] ?? $detalle->Detalle_Producto_medida_precio_compra ?? '0.00');
            } else {
                $costoPrecioUnitario = (string) ($datos['precioUnitario'] ?? $detalle->Detalle_Producto_medida_precio_venta ?? '0.00');
            }

            // 5. Calcular nuevo stock y validar disponibilidad en caso de salida
            $stockActual = (float) $producto->ProductoStockActual;

            if ($tipoMovimiento === 'E') {
                $cantidadEntrada = $cantidadReal;
                $cantidadSalida = 0;
                $nuevoStock = $stockActual + $cantidadReal;
            } else { // 'S'
                if ($stockActual < $cantidadReal) {
                    throw ValidationException::withMessages([
                        'cantidad' => "Stock insuficiente para realizar la salida. Stock actual: {$stockActual} unidades base. Requerido: {$cantidadReal} unidades base ({$cantidadPresentacion} en la unidad seleccionada).",
                    ]);
                }
                $cantidadEntrada = 0;
                $cantidadSalida = $cantidadReal;
                $nuevoStock = $stockActual - $cantidadReal;
            }

            // 6. Generar MovimientoId correlativo y auditoría
            $movimientoId = $this->getNextMovimientoId();
            $auditMovimiento = AuditHelper::getCreationAudit('Movimiento_producto');

            $rawFechaMov = !empty($datos['fechaMovimiento']) ? $datos['fechaMovimiento'] : null;
            if (!empty($rawFechaMov)) {
                if (is_string($rawFechaMov) && strlen(trim($rawFechaMov)) === 10) {
                    $fechaMovimiento = \Carbon\Carbon::parse(trim($rawFechaMov) . ' ' . now()->format('H:i:s'));
                } else {
                    $fechaMovimiento = \Carbon\Carbon::parse($rawFechaMov);
                }
            } else {
                $fechaMovimiento = now();
            }

            $payloadMovimiento = [
                'Movimiento_productoId' => $movimientoId,
                'Movimiento_productoCantidadPresentacion' => (string) $cantidadPresentacion,
                'Movimiento_productoDocumentoOperacionId' => $documentoOperacionId,
                'Movimiento_productoTipoMovimiento' => $tipoMovimiento,
                'Movimiento_productoCostoPrecioUnitario' => $costoPrecioUnitario,
                'Movimiento_productoCantidadEntrada' => (string) $cantidadEntrada,
                'Movimiento_productoCantidadSalida' => (string) $cantidadSalida,
                'Movimiento_productoCantidadSaldo' => (string) $nuevoStock,
                'Movimiento_productoFecha_Movimiento' => $fechaMovimiento,
                'Movimiento_producto_ProductoId' => $productoId,
                'Movimiento_producto_Detalle_Producto_medida_ProductoId' => $productoId,
                'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $unidadesMedidaId,
                'Movimiento_productoSubtipo' => $datos['subtipo'] ?? $datos['Movimiento_productoSubtipo'] ?? null,
                'Movimiento_productoReferenciaTipo' => $datos['referenciaTipo'] ?? $datos['Movimiento_productoReferenciaTipo'] ?? null,
                'Movimiento_productoReferenciaId' => $datos['referenciaId'] ?? $datos['Movimiento_productoReferenciaId'] ?? null,
                'Movimiento_productoMotivo' => $datos['motivo'] ?? $datos['Movimiento_productoMotivo'] ?? null,
                'Movimiento_productoEliminado' => 'N',
            ];
            $payloadMovimiento = array_merge($payloadMovimiento, $auditMovimiento);

            $movimiento = MovimientoProducto::create($payloadMovimiento);

            // 7. Actualizar ProductoStockActual en la tabla Producto
            $auditProducto = AuditHelper::getModificationAudit('Producto');
            $producto->update(array_merge([
                'ProductoStockActual' => (string) $nuevoStock,
            ], $auditProducto));

            return $movimiento->fresh(['producto.categoria', 'unidadMedida', 'detalleProductoMedida']);
        });
    }

    /**
     * List inventory movements (Kardex) with filters and UI-ready formatting.
     */
    public function listarMovimientos(array $filters = [], int $perPage = 8): LengthAwarePaginator|Collection
    {
        $query = MovimientoProducto::with([
            'producto.categoria',
            'producto.detalleProductoMedidas.unidadMedida',
            'unidadMedida',
        ])->where('Movimiento_productoEliminado', 'N');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('Movimiento_productoDocumentoOperacionId', 'like', "%{$search}%")
                  ->orWhere('Movimiento_productoId', 'like', "%{$search}%")
                  ->orWhereHas('producto', function ($pq) use ($search) {
                      $pq->where('ProductoNombre', 'like', "%{$search}%")
                        ->orWhere('ProductoMarca', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['productoId'])) {
            $query->where('Movimiento_producto_ProductoId', $filters['productoId']);
        }

        if (!empty($filters['tipoMovimiento'])) {
            $query->where('Movimiento_productoTipoMovimiento', strtoupper($filters['tipoMovimiento']));
        }

        if (!empty($filters['subtipo'])) {
            $query->where('Movimiento_productoSubtipo', strtoupper($filters['subtipo']));
        }

        if (!empty($filters['dias']) && is_numeric($filters['dias'])) {
            $dias = (int) $filters['dias'];
            $fechaDesde = \Carbon\Carbon::now()->subDays($dias)->startOfDay();
            $query->where('Movimiento_productoFecha_Movimiento', '>=', $fechaDesde);
        } elseif (!empty($filters['fechaDesde'])) {
            $query->where('Movimiento_productoFecha_Movimiento', '>=', $filters['fechaDesde']);
        }

        if (!empty($filters['fechaHasta'])) {
            $query->where('Movimiento_productoFecha_Movimiento', '<=', $filters['fechaHasta'] . ' 23:59:59');
        }

        $query->orderBy('Movimiento_productoFecha_Movimiento', 'desc');

        $result = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        $enrichMovement = function ($m) {
            $esEntrada = strtoupper($m->Movimiento_productoTipoMovimiento) === 'E';
            $cantPresentacion = (float) $m->Movimiento_productoCantidadPresentacion;
            $cantEntrada = (float) $m->Movimiento_productoCantidadEntrada;
            $cantSalida = (float) $m->Movimiento_productoCantidadSalida;
            $saldo = (float) $m->Movimiento_productoCantidadSaldo;
            $costoUnitario = (float) $m->Movimiento_productoCostoPrecioUnitario;

            // 1. Resolver unidad base del producto
            $detalleBase = $m->producto?->detalleProductoMedidas?->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
                ?? $m->producto?->detalleProductoMedidas?->first();
            $unidadBase = $detalleBase?->unidadMedida;
            $unidadBaseAbrev = $unidadBase?->unidades_medidaAbreviatura ?: ($unidadBase?->unidades_medidaDescripcionUnidades ?: 'UND');
            $unidadBaseDesc = $unidadBase?->unidades_medidaDescripcionUnidades ?: 'Unidad';

            // 2. Resolver unidad asignada a la presentación del movimiento
            $movUnidad = $m->unidadMedida;
            $tieneUnidadEnProducto = $m->producto?->detalleProductoMedidas?->contains('Detalle_Producto_medida_unidades_medidaId', $m->Movimiento_producto_Detalle_Producto_medida_unidades_medidaId);
            if (!$tieneUnidadEnProducto && $unidadBase) {
                $movUnidad = $unidadBase;
            }

            $unidadMovAbrev = $movUnidad?->unidades_medidaAbreviatura ?: ($movUnidad?->unidades_medidaDescripcionUnidades ?: $unidadBaseAbrev);
            $unidadMovDesc = $movUnidad?->unidades_medidaDescripcionUnidades ?: $unidadBaseDesc;

            $cantPresFormateada = ($cantPresentacion == (int) $cantPresentacion)
                ? number_format($cantPresentacion, 0)
                : rtrim(rtrim(number_format($cantPresentacion, 2), '0'), '.');

            $cantEntradaFormateada = ($cantEntrada == (int) $cantEntrada)
                ? number_format($cantEntrada, 0)
                : rtrim(rtrim(number_format($cantEntrada, 2), '0'), '.');

            $cantSalidaFormateada = ($cantSalida == (int) $cantSalida)
                ? number_format($cantSalida, 0)
                : rtrim(rtrim(number_format($cantSalida, 2), '0'), '.');

            $saldoFormateado = ($saldo == (int) $saldo)
                ? number_format($saldo, 0)
                : rtrim(rtrim(number_format($saldo, 2), '0'), '.');

            $doc = $m->Movimiento_productoDocumentoOperacionId ?? '';
            $subtipo = $m->Movimiento_productoSubtipo ?? '';
            $tipoOperacion = 'Movimiento de Inventario';

            if ($subtipo === 'RECEPCION_OC') {
                $tipoOperacion = 'Recepción de Orden de Compra';
            } elseif ($subtipo === 'ANULACION_RECEPCION') {
                $tipoOperacion = 'Contra-Movimiento (Anulación OC)';
            } elseif ($subtipo === 'COMPRA_RAPIDA') {
                $tipoOperacion = 'Compra Rápida a PYME Vecina';
            } elseif ($subtipo === 'AJUSTE_MANUAL') {
                $tipoOperacion = 'Ajuste Manual de Kárdex';
            } elseif ($subtipo === 'DEVOLUCION_CLIENTE') {
                $tipoOperacion = 'Devolución de Pedido Cliente';
            } elseif ($subtipo === 'INVENTARIO_INICIAL') {
                $tipoOperacion = 'Inventario Inicial';
            } elseif (str_starts_with($doc, 'AJU-')) {
                $tipoOperacion = 'Ajuste / Merma de Inventario';
            } elseif (str_starts_with($doc, 'REV-')) {
                $tipoOperacion = 'Reversión de Ajuste';
            } elseif (str_starts_with($doc, 'INV-')) {
                $tipoOperacion = 'Inventario Inicial';
            } elseif (str_starts_with($doc, 'PED-')) {
                $tipoOperacion = 'Salida por Pedido de Venta';
            } elseif (str_starts_with($doc, 'F') || str_starts_with($doc, 'B') || str_starts_with($doc, 'DOC-')) {
                $tipoOperacion = $esEntrada ? 'Recepción / Compra' : 'Salida de Mercadería';
            }

            $m->subtipo = $subtipo;
            $m->referencia_tipo = $m->Movimiento_productoReferenciaTipo;
            $m->referencia_id = $m->Movimiento_productoReferenciaId;
            $m->motivo = $m->Movimiento_productoMotivo;

            $m->producto_id = $m->Movimiento_producto_ProductoId;
            $m->producto_nombre = $m->producto->ProductoNombre ?? 'Producto no disponible';
            $m->producto_marca = $m->producto->ProductoMarca ?? '';
            $m->categoria_nombre = $m->producto->categoria->Categoria_ProductoDescripcion_categoria ?? 'Sin Categoría';
            $m->unidad_base_nombre = $unidadBaseDesc;
            $m->unidad_base_abreviatura = $unidadBaseAbrev;
            $m->unidad_movimiento_nombre = $unidadMovDesc;
            $m->unidad_movimiento_abreviatura = $unidadMovAbrev;
            $m->tipo_operacion_texto = $tipoOperacion;
            $m->tipo_movimiento_badge = $esEntrada ? 'ENTRADA' : 'SALIDA';
            $m->tipo_movimiento_color = $esEntrada ? 'emerald' : 'rose';
            $m->cantidad_presentacion_texto = $cantPresFormateada . ' ' . $unidadMovAbrev;
            $m->cantidad_base_texto = $esEntrada
                ? '+' . $cantEntradaFormateada . ' ' . $unidadBaseAbrev
                : '-' . $cantSalidaFormateada . ' ' . $unidadBaseAbrev;
            $doc = $doc ?: 'S/N';
            $fechaMov = $m->Movimiento_productoFecha_Movimiento;
            if ($fechaMov && \Carbon\Carbon::parse($fechaMov)->format('H:i:s') === '00:00:00' && $m->Movimiento_productoFechaCreacion) {
                $fechaMov = $m->Movimiento_productoFechaCreacion;
            }

            $m->documento_referencia = $doc;
            $m->fecha_movimiento_formateada = $fechaMov
                ? \Carbon\Carbon::parse($fechaMov)->format('Y-m-d H:i')
                : '—';
            $m->fecha_solo = $fechaMov
                ? \Carbon\Carbon::parse($fechaMov)->format('Y-m-d')
                : '—';
            $m->hora_solo = $fechaMov
                ? \Carbon\Carbon::parse($fechaMov)->format('H:i')
                : '';
            $m->saldo_resultante_texto = $saldoFormateado . ' ' . $unidadBaseAbrev;
            $m->costo_precio_formateado = 'S/ ' . number_format($costoUnitario, 2);
            $m->costo_total_estimado = round($cantPresentacion * $costoUnitario, 2);
            $m->costo_total_formateado = 'S/ ' . number_format($m->costo_total_estimado, 2);
            $m->usuario_creacion = $m->Movimiento_productoUsuarioCreacion ?? 'Sistema';

            return $m;
        };

        if ($result instanceof LengthAwarePaginator) {
            $result->getCollection()->transform($enrichMovement);
        } else {
            $result->transform($enrichMovement);
        }

        return $result;
    }

    /**
     * Get detail of a specific movement.
     */
    public function obtenerMovimientoPorId(string $id): MovimientoProducto
    {
        $movimiento = MovimientoProducto::with([
            'producto.categoria',
            'producto.detalleProductoMedidas.unidadMedida',
            'unidadMedida',
        ])->where('Movimiento_productoId', $id)
          ->where('Movimiento_productoEliminado', 'N')
          ->first();

        if (!$movimiento) {
            throw new Exception("Movimiento con ID '{$id}' no encontrado.");
        }

        $esEntrada = strtoupper($movimiento->Movimiento_productoTipoMovimiento) === 'E';
        $cantPresentacion = (float) $movimiento->Movimiento_productoCantidadPresentacion;
        $cantEntrada = (float) $movimiento->Movimiento_productoCantidadEntrada;
        $cantSalida = (float) $movimiento->Movimiento_productoCantidadSalida;
        $saldo = (float) $movimiento->Movimiento_productoCantidadSaldo;
        $costoUnitario = (float) $movimiento->Movimiento_productoCostoPrecioUnitario;

        $detalleBase = $movimiento->producto?->detalleProductoMedidas?->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
            ?? $movimiento->producto?->detalleProductoMedidas?->first();
        $unidadBase = $detalleBase?->unidadMedida;
        $unidadBaseAbrev = $unidadBase?->unidades_medidaAbreviatura ?: ($unidadBase?->unidades_medidaDescripcionUnidades ?: 'UND');
        $unidadBaseDesc = $unidadBase?->unidades_medidaDescripcionUnidades ?: 'Unidad';

        $movUnidad = $movimiento->unidadMedida;
        $tieneUnidadEnProducto = $movimiento->producto?->detalleProductoMedidas?->contains('Detalle_Producto_medida_unidades_medidaId', $movimiento->Movimiento_producto_Detalle_Producto_medida_unidades_medidaId);
        if (!$tieneUnidadEnProducto && $unidadBase) {
            $movUnidad = $unidadBase;
        }

        $unidadMovAbrev = $movUnidad?->unidades_medidaAbreviatura ?: ($movUnidad?->unidades_medidaDescripcionUnidades ?: $unidadBaseAbrev);
        $unidadMovDesc = $movUnidad?->unidades_medidaDescripcionUnidades ?: $unidadBaseDesc;

        $cantPresFormateada = ($cantPresentacion == (int) $cantPresentacion)
            ? number_format($cantPresentacion, 0)
            : rtrim(rtrim(number_format($cantPresentacion, 2), '0'), '.');

        $cantEntradaFormateada = ($cantEntrada == (int) $cantEntrada)
            ? number_format($cantEntrada, 0)
            : rtrim(rtrim(number_format($cantEntrada, 2), '0'), '.');

        $cantSalidaFormateada = ($cantSalida == (int) $cantSalida)
            ? number_format($cantSalida, 0)
            : rtrim(rtrim(number_format($cantSalida, 2), '0'), '.');

        $saldoFormateado = ($saldo == (int) $saldo)
            ? number_format($saldo, 0)
            : rtrim(rtrim(number_format($saldo, 2), '0'), '.');

        $doc = $movimiento->Movimiento_productoDocumentoOperacionId ?? '';
        $subtipo = $movimiento->Movimiento_productoSubtipo ?? '';
        $tipoOperacion = 'Movimiento de Inventario';

        if ($subtipo === 'RECEPCION_OC') {
            $tipoOperacion = 'Recepción de Orden de Compra';
        } elseif ($subtipo === 'ANULACION_RECEPCION') {
            $tipoOperacion = 'Contra-Movimiento (Anulación OC)';
        } elseif ($subtipo === 'COMPRA_RAPIDA') {
            $tipoOperacion = 'Compra Rápida a PYME Vecina';
        } elseif ($subtipo === 'AJUSTE_MANUAL') {
            $tipoOperacion = 'Ajuste Manual de Kárdex';
        } elseif ($subtipo === 'DEVOLUCION_CLIENTE') {
            $tipoOperacion = 'Devolución de Pedido Cliente';
        } elseif ($subtipo === 'INVENTARIO_INICIAL') {
            $tipoOperacion = 'Inventario Inicial';
        } elseif (str_starts_with($doc, 'AJU-')) {
            $tipoOperacion = 'Ajuste / Merma de Inventario';
        } elseif (str_starts_with($doc, 'REV-')) {
            $tipoOperacion = 'Reversión de Ajuste';
        } elseif (str_starts_with($doc, 'INV-')) {
            $tipoOperacion = 'Inventario Inicial';
        } elseif (str_starts_with($doc, 'PED-')) {
            $tipoOperacion = 'Salida por Pedido de Venta';
        } elseif (str_starts_with($doc, 'F') || str_starts_with($doc, 'B') || str_starts_with($doc, 'DOC-')) {
            $tipoOperacion = $esEntrada ? 'Recepción / Compra' : 'Salida de Mercadería';
        }

        $movimiento->producto_id = $movimiento->Movimiento_producto_ProductoId;
        $movimiento->producto_nombre = $movimiento->producto->ProductoNombre ?? 'Producto no disponible';
        $movimiento->producto_marca = $movimiento->producto->ProductoMarca ?? '';
        $movimiento->categoria_nombre = $movimiento->producto->categoria->Categoria_ProductoDescripcion_categoria ?? 'Sin Categoría';
        $movimiento->unidad_base_nombre = $unidadBaseDesc;
        $movimiento->unidad_base_abreviatura = $unidadBaseAbrev;
        $movimiento->unidad_movimiento_nombre = $unidadMovDesc;
        $movimiento->unidad_movimiento_abreviatura = $unidadMovAbrev;
        $movimiento->tipo_operacion_texto = $tipoOperacion;
        $movimiento->tipo_movimiento_badge = $esEntrada ? 'ENTRADA' : 'SALIDA';
        $movimiento->tipo_movimiento_color = $esEntrada ? 'emerald' : 'rose';
        $movimiento->cantidad_presentacion_texto = $cantPresFormateada . ' ' . $unidadMovAbrev;
        $movimiento->cantidad_base_texto = $esEntrada
            ? '+' . $cantEntradaFormateada . ' ' . $unidadBaseAbrev
            : '-' . $cantSalidaFormateada . ' ' . $unidadBaseAbrev;
        $doc = $doc ?: 'S/N';
        $fechaMov = $movimiento->Movimiento_productoFecha_Movimiento;
        if ($fechaMov && \Carbon\Carbon::parse($fechaMov)->format('H:i:s') === '00:00:00' && $movimiento->Movimiento_productoFechaCreacion) {
            $fechaMov = $movimiento->Movimiento_productoFechaCreacion;
        }

        $movimiento->documento_referencia = $doc;
        $movimiento->subtipo = $subtipo;
        $movimiento->referencia_tipo = $movimiento->Movimiento_productoReferenciaTipo;
        $movimiento->referencia_id = $movimiento->Movimiento_productoReferenciaId;
        $movimiento->motivo = $movimiento->Movimiento_productoMotivo;

        // Resolver entidad asociada (Proveedor de la OC o Cliente del Pedido)
        $refOcId = $movimiento->Movimiento_productoReferenciaId;
        if (!$refOcId && preg_match('/OC-\d+/', $doc, $matches)) {
            $refOcId = $matches[0];
        }
        if ($refOcId && ($movimiento->Movimiento_productoReferenciaTipo === 'ORDEN_COMPRA' || str_contains($doc, 'OC-') || in_array($subtipo, ['RECEPCION_OC', 'ANULACION_RECEPCION', 'COMPRA_RAPIDA']))) {
            $orden = \App\Models\OrdenCompra::with('proveedor')->find($refOcId);
            if ($orden && $orden->proveedor) {
                $movimiento->proveedor = [
                    'id' => $orden->proveedor->ProveedorId,
                    'razon_social' => $orden->proveedor->ProveedorRazonSocial,
                    'ruc' => $orden->proveedor->ProveedorRuc,
                    'direccion' => $orden->proveedor->ProveedorDireccion,
                    'telefono' => $orden->proveedor->ProveedorTelefono,
                ];
            }
        }

        $refPedId = $movimiento->Movimiento_productoReferenciaId;
        if (!$refPedId && preg_match('/PED-\d+/', $doc, $matchesPed)) {
            $refPedId = $matchesPed[0];
        }
        if ($refPedId && ($movimiento->Movimiento_productoReferenciaTipo === 'PEDIDO' || str_contains($doc, 'PED-') || $subtipo === 'DEVOLUCION_CLIENTE')) {
            $pedido = \App\Models\Pedido::with('cliente')->find($refPedId);
            if ($pedido && $pedido->cliente) {
                $movimiento->cliente = [
                    'id' => $pedido->cliente->ClienteId,
                    'nombre' => $pedido->cliente->ClienteNombre,
                    'ruc' => $pedido->cliente->ClienteRuc,
                    'direccion' => $pedido->cliente->ClienteDireccion,
                    'telefono' => $pedido->cliente->ClienteNumero,
                ];
            }
        }

        $movimiento->fecha_movimiento_formateada = $fechaMov
            ? \Carbon\Carbon::parse($fechaMov)->format('Y-m-d H:i')
            : '—';
        $movimiento->fecha_solo = $fechaMov
            ? \Carbon\Carbon::parse($fechaMov)->format('Y-m-d')
            : '—';
        $movimiento->hora_solo = $fechaMov
            ? \Carbon\Carbon::parse($fechaMov)->format('H:i')
            : '';
        $movimiento->saldo_resultante_texto = $saldoFormateado . ' ' . $unidadBaseAbrev;
        $movimiento->costo_precio_formateado = 'S/ ' . number_format($costoUnitario, 2);
        $movimiento->costo_total_estimado = round($cantPresentacion * $costoUnitario, 2);
        $movimiento->costo_total_formateado = 'S/ ' . number_format($movimiento->costo_total_estimado, 2);
        $movimiento->usuario_creacion = $movimiento->Movimiento_productoUsuarioCreacion ?? 'Sistema';

        return $movimiento;
    }

    /**
     * Get movements specifically for a given product.
     */
    public function obtenerMovimientosPorProducto(string $productoId, int $perPage = 15): LengthAwarePaginator
    {
        return MovimientoProducto::with(['unidadMedida'])
            ->where('Movimiento_producto_ProductoId', $productoId)
            ->where('Movimiento_productoEliminado', 'N')
            ->orderBy('Movimiento_productoFecha_Movimiento', 'desc')
            ->paginate($perPage);
    }

    /**
     * General inventory stock summary.
     */
    public function resumenStock(): array
    {
        $totalProductos = Producto::where('ProductoEliminado', 'N')->count();
        $productosActivos = Producto::where('ProductoEliminado', 'N')->where('ProductoEstado', 'A')->count();

        $productos = Producto::where('ProductoEliminado', 'N')
            ->with(['detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medida_factor_conversion', 1);
            }])
            ->get();

        $stockFisicoTotal = 0;
        $productosStockBajo = 0;
        $valoracionInventarioEstimada = 0;

        foreach ($productos as $p) {
            $stock = (float) $p->ProductoStockActual;
            $minimo = (float) $p->ProductoStockMinimo;
            $stockFisicoTotal += $stock;

            if ($stock <= $minimo) {
                $productosStockBajo++;
            }

            // Calcular valoración según precio de compra de la unidad base
            $detalleBase = $p->detalleProductoMedidas->first();
            $costoBase = $detalleBase ? (float) $detalleBase->Detalle_Producto_medida_precio_compra : 0;
            $valoracionInventarioEstimada += ($stock * $costoBase);
        }

        return [
            'total_productos_registrados' => $totalProductos,
            'total_productos_activos' => $productosActivos,
            'total_unidades_fisicas_stock' => $stockFisicoTotal,
            'productos_con_stock_minimo' => $productosStockBajo,
            'valoracion_estimada_inventario' => round($valoracionInventarioEstimada, 2),
        ];
    }

    /**
     * Products below minimum stock alert.
     */
    public function productosStockMinimo(): Collection
    {
        return Producto::with(['categoria', 'detalleProductoMedidas.unidadMedida'])
            ->where('ProductoEliminado', 'N')
            ->where('ProductoEstado', 'A')
            ->whereRaw('CAST(ProductoStockActual AS FLOAT) <= CAST(ProductoStockMinimo AS FLOAT)')
            ->get();
    }

    /**
     * Calculate stock of a product broken down across all its configured presentation units.
     */
    public function consultarStockProductoPorUnidades(string $productoId): array
    {
        $producto = $this->obtenerProductoPorId($productoId);
        $stockBase = (float) $producto->ProductoStockActual;

        $desglose = [];
        foreach ($producto->detalleProductoMedidas as $det) {
            $factor = (int) $det->Detalle_Producto_medida_factor_conversion;
            if ($factor <= 0) {
                $factor = 1;
            }

            $unidadesEnteras = floor($stockBase / $factor);
            $residuo = $stockBase % $factor;
            $equivalenteDecimal = round($stockBase / $factor, 2);

            $desglose[] = [
                'unidad_id' => $det->Detalle_Producto_medida_unidades_medidaId,
                'unidad_descripcion' => $det->unidadMedida->unidades_medidaDescripcionUnidades ?? 'N/A',
                'factor_conversion' => $factor,
                'precio_compra' => $det->Detalle_Producto_medida_precio_compra,
                'precio_venta' => $det->Detalle_Producto_medida_precio_venta,
                'equivalente_decimal' => $equivalenteDecimal,
                'presentaciones_completas' => (int) $unidadesEnteras,
                'unidades_sueltas_sobrantes' => (int) $residuo,
            ];
        }

        return [
            'producto_id' => $producto->ProductoId,
            'producto_nombre' => $producto->ProductoNombre,
            'categoria' => $producto->categoria->Categoria_ProductoDescripcion_categoria ?? null,
            'stock_actual_unidades_base' => $stockBase,
            'stock_minimo' => (float) $producto->ProductoStockMinimo,
            'stock_maximo' => (float) $producto->ProductoStockMaximo,
            'desglose_por_unidades' => $desglose,
        ];
    }

    /**
     * Catalog lists.
     */
    public function obtenerCategoriasActivas(): Collection
    {
        return CategoriaProducto::where('Categoria_ProductoEliminado', 'N')
            ->where('Categoria_ProductoEstado', 'A')
            ->orderBy('Categoria_ProductoDescripcion_categoria', 'asc')
            ->get();
    }

    public function obtenerUnidadesMedidaActivas(): Collection
    {
        return UnidadesMedida::where('unidades_medidaEliminado', 'N')
            ->where('unidades_medidaEstadoUnidades', 'A')
            ->orderBy('unidades_medidaDescripcionUnidades', 'asc')
            ->get();
    }

    public function obtenerProveedoresActivos(): Collection
    {
        return Proveedor::where('ProveedorEliminado', 'N')
            ->where('ProveedorEstado', 'A')
            ->orderBy('ProveedorRazonSocial', 'asc')
            ->get();
    }

    public function obtenerUbicacionesActivas(): Collection
    {
        return ProductoUbi::where('producto_ubi_eliminado', 'N')
            ->where('producto_ubi_estado', 'A')
            ->orderBy('producto_ubi_descripcion', 'asc')
            ->get();
    }

    /**
     * Subir imagen para un producto y almacenar la ruta en producto_imagen.
     */
    public function subirImagenProducto(string $id, $file): Producto
    {
        $producto = $this->obtenerProductoPorId($id);

        $path = $file->store('productos', 'public');
        $url = '/storage/' . $path;

        $auditProducto = AuditHelper::getModificationAudit('Producto');
        $producto->update(array_merge([
            'producto_imagen' => $url,
        ], $auditProducto));

        return $producto->fresh([
            'categoria',
            'ubicacion',
            'detalleProductoMedidas.unidadMedida',
            'proveedores',
        ]);
    }

    /**
     * Generar siguiente ProductoId correlativo (ej. PROD-00001).
     */
    public function getNextProductoId(): string
    {
        $last = DB::table('Producto')
            ->select('ProductoId')
            ->where('ProductoId', 'like', 'PROD-%')
            ->orderByRaw('LEN(ProductoId) DESC, ProductoId DESC')
            ->first();

        if (!$last) {
            return 'PROD-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->ProductoId);
        $nextNumber = ((int) $numberPart) + 1;

        return 'PROD-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generar siguiente Movimiento_productoId correlativo (ej. MOV-00001).
     */
    public function getNextMovimientoId(): string
    {
        $last = DB::table('Movimiento_producto')
            ->select('Movimiento_productoId')
            ->where('Movimiento_productoId', 'like', 'MOV-%')
            ->orderByRaw('LEN(Movimiento_productoId) DESC, Movimiento_productoId DESC')
            ->first();

        if (!$last) {
            return 'MOV-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->Movimiento_productoId);
        $nextNumber = ((int) $numberPart) + 1;

        return 'MOV-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Productos con stock bajo / crítico para reposición de órdenes de compra.
     */
    public function obtenerProductosStockBajo(int $limit = 50): array
    {
        $productos = Producto::with([
            'categoria',
            'proveedores',
            'detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
            }
        ])
        ->where('ProductoEliminado', 'N')
        ->where('ProductoEstado', 'A')
        ->whereRaw('CAST(ProductoStockActual AS FLOAT) <= CAST(ProductoStockMinimo AS FLOAT)')
        ->orderByRaw('(CAST(ProductoStockMinimo AS FLOAT) - CAST(ProductoStockActual AS FLOAT)) DESC')
        ->take($limit)
        ->get();

        return $productos->map(function ($p) {
            $stockActual = (float) $p->ProductoStockActual;
            $stockMinimo = (float) $p->ProductoStockMinimo;
            $stockMaximo = (float) ($p->ProductoStockMaximo ?? ($stockMinimo * 2));
            $deficit = max(0, round($stockMinimo - $stockActual, 2));
            $sugerido = max(0, round($stockMaximo - $stockActual, 2));

            $unidades = $p->detalleProductoMedidas->map(function ($det) {
                return [
                    'unidades_medidaId' => $det->Detalle_Producto_medida_unidades_medidaId,
                    'descripcion' => $det->unidadMedida->unidades_medidaDescripcionUnidades ?? 'Unidad',
                    'abreviatura' => $det->unidadMedida->unidades_medidaAbreviatura ?? 'UND',
                    'factor_conversion' => (int) $det->Detalle_Producto_medida_factor_conversion,
                    'precio_compra' => (float) $det->Detalle_Producto_medida_precio_compra,
                    'precio_venta' => (float) $det->Detalle_Producto_medida_precio_venta,
                ];
            });

            $unidadBase = $unidades->first(fn ($u) => $u['factor_conversion'] === 1) ?? $unidades->first();

            $proveedores = $p->proveedores->map(function ($prv) {
                return [
                    'id' => $prv->ProveedorId,
                    'razon_social' => $prv->ProveedorRazonSocial,
                    'ruc' => $prv->ProveedorRuc,
                    'telefono' => $prv->ProveedorTelefono,
                ];
            });

            return [
                'producto_id' => $p->ProductoId,
                'nombre' => $p->ProductoNombre,
                'marca' => $p->ProductoMarca,
                'categoria' => $p->categoria->Categoria_ProductoDescripcion_categoria ?? null,
                'stock_actual' => $stockActual,
                'stock_minimo' => $stockMinimo,
                'stock_maximo' => $stockMaximo,
                'deficit' => $deficit,
                'sugerido_reposicion' => $sugerido,
                'unidad_base' => $unidadBase['abreviatura'] ?? 'UND',
                'precio_compra_estimado' => $unidadBase['precio_compra'] ?? 0,
                'impacto_reposicion_soles' => round($sugerido * ($unidadBase['precio_compra'] ?? 0), 2),
                'proveedores' => $proveedores,
            ];
        })->all();
    }

    /**
     * Normaliza un texto eliminando tildes, diacríticos y espacios redundantes para comparaciones insensibles.
     */
    public function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
            'ñ' => 'n', 'Ñ' => 'n'
        ];
        $texto = strtr($texto, $reemplazos);
        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Búsqueda rápida e inteligente de productos:
     * 1. Insensible a tildes (COLLATE Modern_Spanish_CI_AI).
     * 2. Tolerante a espacios múltiples o diferencias de formato (búsqueda por tokens).
     * 3. Fallback por similitud difusa (Fuzzy Matching / similar_text / levenshtein) ante errores tipográficos.
     */
    public function buscarProductosRapido(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if (empty($term)) {
            return [];
        }

        $norm = $this->normalizarTexto($term);
        $words = array_filter(explode(' ', $norm), fn($w) => strlen($w) >= 2);

        $query = Producto::with([
            'categoria',
            'proveedores',
            'detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
            }
        ])
        ->where('ProductoEliminado', 'N')
        ->where('ProductoEstado', 'A');

        // Fase 1: Búsqueda SQL directa e insensible a tildes / mayúsculas
        if (count($words) > 1) {
            $query->where(function ($q) use ($words, $term) {
                $q->where('ProductoId', 'like', "%{$term}%")
                  ->orWhere(function ($sub) use ($words) {
                      foreach ($words as $w) {
                          $sub->where(function ($wSub) use ($w) {
                              $wSub->whereRaw("ProductoNombre COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$w}%"])
                                   ->orWhereRaw("ProductoMarca COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$w}%"]);
                          });
                      }
                  });
            });
        } else {
            $query->where(function ($q) use ($term, $norm) {
                $q->whereRaw("ProductoNombre COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$term}%"])
                  ->orWhereRaw("ProductoNombre COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$norm}%"])
                  ->orWhere('ProductoId', 'like', "%{$term}%")
                  ->orWhereRaw("ProductoMarca COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$term}%"])
                  ->orWhereRaw("ProductoMarca COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$norm}%"])
                  ->orWhereHas('categoria', function ($qc) use ($term, $norm) {
                      $qc->whereRaw("Categoria_ProductoDescripcion_categoria COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$term}%"])
                         ->orWhereRaw("Categoria_ProductoDescripcion_categoria COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$norm}%"]);
                  });
            });
        }

        $productos = $query->take($limit)->get();

        // Fase 2: Fallback por similitud difusa (Fuzzy Matching) si no hubo coincidencia exacta
        if ($productos->isEmpty() && strlen($norm) >= 2) {
            $todos = Producto::with([
                'categoria',
                'proveedores',
                'detalleProductoMedidas' => function ($q) {
                    $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
                }
            ])
            ->where('ProductoEliminado', 'N')
            ->where('ProductoEstado', 'A')
            ->get();

            $scored = [];
            foreach ($todos as $p) {
                $pNorm = $this->normalizarTexto($p->ProductoNombre);
                $marcaNorm = $this->normalizarTexto($p->ProductoMarca ?? '');

                similar_text($norm, $pNorm, $pctName);
                similar_text($norm, $marcaNorm, $pctMarca);

                $maxWordPct = 0;
                foreach (explode(' ', $pNorm) as $pw) {
                    similar_text($norm, $pw, $wpct);
                    if ($wpct > $maxWordPct) $maxWordPct = $wpct;
                }

                $score = max($pctName, $pctMarca, $maxWordPct);

                // Recompensa para typos cortos (levenshtein <= 2 solo para palabras de 4 o más caracteres)
                $firstWord = explode(' ', $pNorm)[0] ?? $pNorm;
                if (strlen($norm) >= 4 && levenshtein($norm, $firstWord) <= 2) {
                    $score = max($score, 80);
                }

                // Umbral calibrado en 70% para evitar falsos positivos (ej. "leche" vs "lechuga")
                if ($score >= 70) {
                    $scored[] = ['prod' => $p, 'score' => $score];
                }
            }

            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $productos = collect(array_slice(array_column($scored, 'prod'), 0, $limit));
        }

        return $productos->map(function ($p) {
            $unidades = $p->detalleProductoMedidas->map(function ($det) {
                $costo = (float) $det->Detalle_Producto_medida_precio_compra;
                $venta = (float) $det->Detalle_Producto_medida_precio_venta;

                return [
                    'unidades_medidaId' => $det->Detalle_Producto_medida_unidades_medidaId,
                    'descripcion' => $det->unidadMedida->unidades_medidaDescripcionUnidades ?? 'Unidad',
                    'abreviatura' => $det->unidadMedida->unidades_medidaAbreviatura ?? 'UND',
                    'factor_conversion' => (int) $det->Detalle_Producto_medida_factor_conversion,
                    'precio_compra' => $costo,
                    'precio_venta' => $venta,
                    'precio_compra_formateado' => 'S/ ' . number_format($costo, 2),
                ];
            });

            $unidadBase = $unidades->first(fn ($u) => $u['factor_conversion'] === 1) ?? $unidades->first();

            return [
                'producto_id' => $p->ProductoId,
                'nombre' => $p->ProductoNombre,
                'marca' => $p->ProductoMarca,
                'categoria' => $p->categoria->Categoria_ProductoDescripcion_categoria ?? null,
                'stock_actual' => (float) $p->ProductoStockActual,
                'stock_minimo' => (float) $p->ProductoStockMinimo,
                'unidad_base' => $unidadBase['abreviatura'] ?? 'UND',
                'precio_compra_base' => $unidadBase['precio_compra'] ?? 0,
                'unidades_medida' => $unidades->toArray(),
                'proveedores' => $p->proveedores->map(fn ($prv) => [
                    'id' => $prv->ProveedorId,
                    'razon_social' => $prv->ProveedorRazonSocial,
                    'telefono' => $prv->ProveedorTelefono,
                ])->toArray(),
            ];
        })->all();
    }

    /**
     * Obtener el último precio de compra y costo actual de un producto.
     */
    public function obtenerUltimoPrecioProducto(string $productoId): array
    {
        $producto = Producto::where('ProductoId', $productoId)
            ->where('ProductoEliminado', 'N')
            ->first();

        if (!$producto) {
            throw new Exception("Producto con ID '{$productoId}' no encontrado.");
        }

        // Buscar última compra en Detalle_Orden_Compra + Orden_Compra
        $ultimaCompra = DB::table('Detalle_Orden_Compra as doc')
            ->join('Orden_Compra as oc', 'doc.Detalle_Orden_Compra_Orden_CompraId', '=', 'oc.Orden_CompraId')
            ->leftJoin('Proveedor as prv', 'oc.Orden_Compra_ProveedorId', '=', 'prv.ProveedorId')
            ->leftJoin('unidades_medida as um', 'doc.Detalle_UnidadMedidaId', '=', 'um.unidades_medidaId')
            ->where('doc.Detalle_ProductoId', $productoId)
            ->where('doc.Detalle_Orden_CompraEliminado', 'N')
            ->where('oc.Orden_CompraEliminado', 'N')
            ->whereIn('oc.Orden_CompraEstado', ['P', 'C'])
            ->orderBy('oc.Orden_CompraFecha', 'desc')
            ->select([
                'doc.Detalle_Orden_CompraPrecioUnitario as precio_unitario',
                'doc.Detalle_Orden_CompraCantidad as cantidad',
                'doc.Detalle_UnidadMedidaId as unidad_id',
                'um.unidades_medidaAbreviatura as unidad_abreviatura',
                'um.unidades_medidaDescripcionUnidades as unidad_descripcion',
                'oc.Orden_CompraId as orden_id',
                'oc.Orden_CompraFecha as orden_fecha',
                'prv.ProveedorId as proveedor_id',
                'prv.ProveedorRazonSocial as proveedor_nombre',
                'prv.ProveedorTelefono as proveedor_telefono',
            ])
            ->first();

        // Buscar precios configurados en Detalle_Producto_medida
        $preciosConfigurados = DB::table('Detalle_Producto_medida as dpm')
            ->leftJoin('unidades_medida as um', 'dpm.Detalle_Producto_medida_unidades_medidaId', '=', 'um.unidades_medidaId')
            ->where('dpm.Detalle_Producto_medida_ProductoId', $productoId)
            ->where('dpm.Detalle_Producto_medidaEliminado', 'N')
            ->select([
                'dpm.Detalle_Producto_medida_unidades_medidaId as unidad_id',
                'um.unidades_medidaAbreviatura as unidad_abreviatura',
                'dpm.Detalle_Producto_medida_factor_conversion as factor_conversion',
                'dpm.Detalle_Producto_medida_precio_compra as precio_compra',
                'dpm.Detalle_Producto_medida_precio_venta as precio_venta',
            ])
            ->get();

        // Historial de compras recientes
        $historialCompras = DB::table('Detalle_Orden_Compra as doc')
            ->join('Orden_Compra as oc', 'doc.Detalle_Orden_Compra_Orden_CompraId', '=', 'oc.Orden_CompraId')
            ->leftJoin('Proveedor as prv', 'oc.Orden_Compra_ProveedorId', '=', 'prv.ProveedorId')
            ->leftJoin('unidades_medida as um', 'doc.Detalle_UnidadMedidaId', '=', 'um.unidades_medidaId')
            ->where('doc.Detalle_ProductoId', $productoId)
            ->where('doc.Detalle_Orden_CompraEliminado', 'N')
            ->where('oc.Orden_CompraEliminado', 'N')
            ->orderBy('oc.Orden_CompraFecha', 'desc')
            ->take(5)
            ->select([
                'oc.Orden_CompraId as orden_id',
                'oc.Orden_CompraFecha as fecha',
                'doc.Detalle_Orden_CompraCantidad as cantidad',
                'doc.Detalle_Orden_CompraPrecioUnitario as precio_unitario',
                'um.unidades_medidaAbreviatura as unidad',
                'prv.ProveedorRazonSocial as proveedor',
            ])
            ->get();

        $costoBase = $preciosConfigurados->firstWhere('factor_conversion', 1)->precio_compra 
            ?? ($preciosConfigurados->first()->precio_compra ?? 0);

        return [
            'producto_id' => $producto->ProductoId,
            'nombre' => $producto->ProductoNombre,
            'stock_actual' => (float) $producto->ProductoStockActual,
            'precio_costo_actual' => (float) $costoBase,
            'ultimo_precio_compra' => $ultimaCompra ? (float) $ultimaCompra->precio_unitario : (float) $costoBase,
            'ultima_compra' => $ultimaCompra ? [
                'orden_id' => $ultimaCompra->orden_id,
                'fecha' => $ultimaCompra->orden_fecha,
                'precio_unitario' => (float) $ultimaCompra->precio_unitario,
                'cantidad' => (float) $ultimaCompra->cantidad,
                'unidad' => $ultimaCompra->unidad_abreviatura ?? 'UND',
                'proveedor' => [
                    'id' => $ultimaCompra->proveedor_id,
                    'razon_social' => $ultimaCompra->proveedor_nombre,
                    'telefono' => $ultimaCompra->proveedor_telefono,
                ],
            ] : null,
            'precios_por_unidad' => $preciosConfigurados,
            'historial_compras_recientes' => $historialCompras,
        ];
    }

    /**
     * Crear un producto rápido desde el modal express de recepción de OC.
     */
    public function crearProductoExpress(array $datos): Producto
    {
        return DB::transaction(function () use ($datos) {
            $nombre = trim($datos['nombre'] ?? $datos['ProductoNombre'] ?? '');
            if (empty($nombre)) {
                throw ValidationException::withMessages([
                    'nombre' => 'El nombre del producto es obligatorio.',
                ]);
            }

            // Categoría fallback a la primera existente si no se proporciona
            $categoriaId = $datos['categoria_id'] ?? $datos['Producto_Categoria_ProductoId'] ?? null;
            if (!$categoriaId) {
                $primeraCat = CategoriaProducto::where('Categoria_ProductoEliminado', 'N')->first();
                $categoriaId = $primeraCat ? $primeraCat->Categoria_ProductoId : 'CAT-00001';
            }

            // Unidad de medida base fallback
            $unidadMedidaId = $datos['unidad_base_id'] ?? $datos['unidades_medidaId'] ?? null;
            if (!$unidadMedidaId) {
                $primeraUnidad = UnidadesMedida::where('unidades_medidaEliminado', 'N')->first();
                $unidadMedidaId = $primeraUnidad ? $primeraUnidad->unidades_medidaId : 'UND-00001';
            }

            $precioCompra = (float) ($datos['precio_compra'] ?? 0.0);
            $precioVenta = (float) ($datos['precio_venta'] ?? ($precioCompra > 0 ? $precioCompra * 1.25 : 1.0));

            $detalles = [
                [
                    'unidades_medidaId' => $unidadMedidaId,
                    'Detalle_Producto_medida_factor_conversion' => 1,
                    'Detalle_Producto_medida_precio_compra' => $precioCompra,
                    'Detalle_Producto_medida_precio_venta' => $precioVenta,
                ],
            ];

            $datosProd = [
                'ProductoNombre' => $nombre,
                'Producto_Categoria_ProductoId' => $categoriaId,
                'ProductoMarca' => $datos['marca'] ?? 'Genérico',
                'producto_descripcion' => $datos['descripcion'] ?? 'Producto registrado desde recepción express de OC',
                'stockInicial' => 0.0,
                'ProductoStockMinimo' => $datos['stock_minimo'] ?? 5,
                'ProductoStockMaximo' => $datos['stock_maximo'] ?? 500,
            ];

            $proveedores = !empty($datos['proveedor_id']) ? [$datos['proveedor_id']] : [];

            return $this->crearProducto($datosProd, $detalles, $proveedores);
        });
    }

    /**
     * Ajuste manual de Kárdex con motivo obligatorio y registro formal.
     */
    public function ajusteManual(array $datos): MovimientoProducto
    {
        $motivo = trim($datos['motivo'] ?? '');
        if (mb_strlen($motivo) < 10) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo del ajuste manual es obligatorio y debe tener al menos 10 caracteres.',
            ]);
        }

        $tipo = strtoupper(trim($datos['tipo'] ?? 'E'));
        if (!in_array($tipo, ['E', 'S'])) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de ajuste debe ser E (Entrada) o S (Salida).',
            ]);
        }

        $datos['tipoMovimiento'] = $tipo;
        $datos['subtipo'] = 'AJUSTE_MANUAL';
        $datos['referenciaTipo'] = 'AJUSTE_MANUAL';
        $datos['motivo'] = $motivo;
        $datos['documentoOperacionId'] = 'AJUSTE-' . now()->format('YmdHis');

        return $this->registrarMovimiento($datos);
    }
}
