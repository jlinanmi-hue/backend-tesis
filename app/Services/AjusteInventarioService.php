<?php

namespace App\Services;

use App\Models\AjusteInventario;
use App\Models\MovimientoProducto;
use App\Models\Producto;
use App\Repositories\Contracts\AjusteInventarioRepositoryInterface;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AjusteInventarioService
{
    public function __construct(
        protected AjusteInventarioRepositoryInterface $ajusteRepository,
        protected InventarioService $inventarioService,
        protected NotificacionService $notificacionService
    ) {}

    public function listarAjustes(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $resultado = $this->ajusteRepository->getAll($filters, $perPage);

        // Enriquecer cada ajuste con su impacto financiero
        if ($resultado instanceof LengthAwarePaginator) {
            $resultado->getCollection()->transform(function ($ajuste) {
                return $this->enriquecerAjusteConFinanzas($ajuste);
            });
        } elseif ($resultado instanceof Collection) {
            $resultado->transform(function ($ajuste) {
                return $this->enriquecerAjusteConFinanzas($ajuste);
            });
        }

        return $resultado;
    }

    public function obtenerAjustePorId(string $id, bool $includeDeleted = false): AjusteInventario
    {
        $ajuste = $this->ajusteRepository->findById($id, $includeDeleted);

        if (!$ajuste) {
            throw new Exception("Ajuste de inventario con ID '{$id}' no encontrado.");
        }

        return $this->enriquecerAjusteConFinanzas($ajuste);
    }

    /**
     * Catálogo de tipos de ajuste de inventario permitidos
     */
    public function obtenerTiposAjuste(): array
    {
        return [
            ['codigo' => 'Merma', 'descripcion' => 'Merma por derrame, evaporación o residuo'],
            ['codigo' => 'Dañado', 'descripcion' => 'Producto con empaque o contenido deteriorado'],
            ['codigo' => 'Vencido', 'descripcion' => 'Producto cuya fecha de caducidad expiró'],
            ['codigo' => 'Rotura', 'descripcion' => 'Producto quebrado o roto durante manipulación'],
            ['codigo' => 'Pérdida', 'descripcion' => 'Diferencia no justificada o extravío'],
            ['codigo' => 'Ajuste Físico', 'descripcion' => 'Ajuste por conteo periódico de inventario'],
        ];
    }

    /**
     * Resumen estadístico de ajustes de inventario
     */
    public function obtenerResumen(): array
    {
        return $this->obtenerImpactoFinanciero();
    }

    /**
     * Cálculo integral de Impacto Financiero de Mermas y Desperdicios
     */
    public function obtenerImpactoFinanciero(array $filtros = []): array
    {
        $ajustes = $this->obtenerAjustesParaReporte($filtros);

        $totalAjustes = count($ajustes);
        $totalUnidades = 0.0;
        $costoTotalPerdido = 0.0;
        $ventaTotalPerdida = 0.0;

        $porTipo = [];
        $porProducto = [];

        foreach ($ajustes as $a) {
            $cant = (float) $a->Ajuste_inventario_cantidad;
            $costoU = (float) ($a->costo_unitario ?? 0);
            $ventaU = (float) ($a->precio_venta_unitario ?? 0);

            $costoPerd = round($cant * $costoU, 2);
            $ventaPerd = round($cant * $ventaU, 2);

            $totalUnidades += $cant;
            $costoTotalPerdido += $costoPerd;
            $ventaTotalPerdida += $ventaPerd;

            // Agrupar por Tipo
            $tipo = $a->Ajuste_inventario_tipo;
            if (!isset($porTipo[$tipo])) {
                $porTipo[$tipo] = [
                    'tipo' => $tipo,
                    'total_registros' => 0,
                    'total_unidades' => 0.0,
                    'costo_perdido' => 0.0,
                    'venta_perdida' => 0.0,
                ];
            }
            $porTipo[$tipo]['total_registros']++;
            $porTipo[$tipo]['total_unidades'] += $cant;
            $porTipo[$tipo]['costo_perdido'] += $costoPerd;
            $porTipo[$tipo]['venta_perdida'] += $ventaPerd;

            // Agrupar por Producto para Top Pérdidas
            $prodId = $a->Ajuste_inventario_ProductoId;
            $prodNom = $a->producto->ProductoNombre ?? $prodId;
            if (!isset($porProducto[$prodId])) {
                $porProducto[$prodId] = [
                    'producto_id' => $prodId,
                    'producto_nombre' => $prodNom,
                    'total_unidades' => 0.0,
                    'costo_perdido' => 0.0,
                ];
            }
            $porProducto[$prodId]['total_unidades'] += $cant;
            $porProducto[$prodId]['costo_perdido'] += $costoPerd;
        }

        usort($porProducto, fn($a, $b) => $b['costo_perdido'] <=> $a['costo_perdido']);
        $topProductos = array_slice($porProducto, 0, 5);

        return [
            'total_ajustes' => $totalAjustes,
            'total_unidades' => round($totalUnidades, 2),
            'costo_total_perdido' => round($costoTotalPerdido, 2),
            'venta_total_perdida' => round($ventaTotalPerdida, 2),
            'moneda' => 'S/',
            'por_tipo' => array_values($porTipo),
            'top_productos_perdida' => $topProductos,
            'periodo' => [
                'desde' => $filtros['fechaDesde'] ?? null,
                'hasta' => $filtros['fechaHasta'] ?? null,
            ],
        ];
    }

    /**
     * Registrar un nuevo Ajuste de Inventario / Merma.
     * REGLA DE NEGOCIO:
     * 1. Valida existencia del producto.
     * 2. Verifica stock suficiente para no generar saldos negativos.
     * 3. Descuenta el stock en Producto.
     * 4. Registra el movimiento de salida en Kardex (Movimiento_producto).
     * 5. Registra el Ajuste_inventario con su auditoría.
     * 6. Calcula impacto financiero y emite Notificación en Tiempo Real (SSE / Tarjeta UI).
     */
    public function crearAjuste(array $datos): AjusteInventario
    {
        return DB::transaction(function () use ($datos) {
            $productoId = $datos['Ajuste_inventario_ProductoId'] ?? $datos['productoId'] ?? null;
            $cantidad = (float) ($datos['Ajuste_inventario_cantidad'] ?? $datos['Ajuste_inventarioCantidad'] ?? $datos['cantidad'] ?? 0);
            $tipo = $datos['Ajuste_inventario_tipo'] ?? $datos['Ajuste_inventarioTipo'] ?? $datos['tipo'] ?? 'Merma';
            $motivo = $datos['Ajuste_inventario_motivo'] ?? $datos['Ajuste_inventarioMotivo'] ?? $datos['motivo'] ?? 'Ajuste de inventario';
            $rawFecha = !empty($datos['Ajuste_inventario_fecha']) ? $datos['Ajuste_inventario_fecha'] : (!empty($datos['fecha']) ? $datos['fecha'] : null);
            if (!empty($rawFecha)) {
                if (is_string($rawFecha) && strlen(trim($rawFecha)) === 10) {
                    $fecha = \Carbon\Carbon::parse(trim($rawFecha) . ' ' . now()->format('H:i:s'));
                } else {
                    $fecha = \Carbon\Carbon::parse($rawFecha);
                }
            } else {
                $fecha = now();
            }
            $silenciosa = !empty($datos['silenciosa']);

            if ($cantidad <= 0) {
                throw ValidationException::withMessages([
                    'Ajuste_inventario_cantidad' => 'La cantidad del ajuste debe ser un número mayor a cero.',
                ]);
            }

            // 1. Buscar producto bloqueando para actualización
            $producto = Producto::where('ProductoId', $productoId)
                ->where('ProductoEliminado', 'N')
                ->lockForUpdate()
                ->first();

            if (!$producto) {
                throw ValidationException::withMessages([
                    'Ajuste_inventario_ProductoId' => "El producto con ID '{$productoId}' no existe o se encuentra inactivo.",
                ]);
            }

            $stockActual = (float) $producto->ProductoStockActual;

            // 2. VALIDACIÓN ESTRICTA DE STOCK DISPONIBLE (Impide stock negativo)
            if ($cantidad > $stockActual) {
                throw ValidationException::withMessages([
                    'Ajuste_inventario_cantidad' => "Stock insuficiente para registrar el ajuste. El producto cuenta con un stock disponible de {$stockActual}, y se intentó descontar {$cantidad}.",
                ]);
            }

            // Obtener unidad base del producto para costos y Kardex
            $medidaBase = DB::table('Detalle_Producto_medida as dpm')
                ->join('unidades_medida as um', 'um.unidades_medidaId', '=', 'dpm.Detalle_Producto_medida_unidades_medidaId')
                ->where('dpm.Detalle_Producto_medida_ProductoId', $productoId)
                ->where('dpm.Detalle_Producto_medidaEliminado', 'N')
                ->where(function ($q) {
                    $q->where('dpm.Detalle_Producto_medida_factor_conversion', 1)
                      ->orWhere('um.unidades_medidaEsBase', 1);
                })
                ->select('dpm.*', 'um.unidades_medidaAbreviatura', 'um.unidades_medidaDescripcionUnidades')
                ->first();

            if (!$medidaBase) {
                $medidaBase = DB::table('Detalle_Producto_medida as dpm')
                    ->join('unidades_medida as um', 'um.unidades_medidaId', '=', 'dpm.Detalle_Producto_medida_unidades_medidaId')
                    ->where('dpm.Detalle_Producto_medida_ProductoId', $productoId)
                    ->where('dpm.Detalle_Producto_medidaEliminado', 'N')
                    ->select('dpm.*', 'um.unidades_medidaAbreviatura', 'um.unidades_medidaDescripcionUnidades')
                    ->first();
            }

            $costoUnitario = $medidaBase ? (float) $medidaBase->Detalle_Producto_medida_precio_compra : 0.0;
            $precioVentaUnitario = $medidaBase ? (float) $medidaBase->Detalle_Producto_medida_precio_venta : 0.0;
            $unidadMedidaId = $medidaBase ? $medidaBase->Detalle_Producto_medida_unidades_medidaId : 'UND-00001';
            $costoTotalPerdido = round($cantidad * $costoUnitario, 2);
            $ventaTotalPerdida = round($cantidad * $precioVentaUnitario, 2);

            // 3. Descontar stock del producto
            $nuevoStock = $stockActual - $cantidad;
            $auditProducto = AuditHelper::getModificationAudit('Producto');
            $producto->update(array_merge([
                'ProductoStockActual' => (string) $nuevoStock,
            ], $auditProducto));

            // 4. Generar ID y registrar Ajuste_inventario
            $ajusteId = $this->ajusteRepository->getNextId();
            $auditAjuste = AuditHelper::getCreationAudit('Ajuste_inventario_');

            $payloadAjuste = array_merge([
                'Ajuste_inventario_id' => $ajusteId,
                'Ajuste_inventario_ProductoId' => $productoId,
                'Ajuste_inventario_tipo' => $tipo,
                'Ajuste_inventario_cantidad' => $cantidad,
                'Ajuste_inventario_motivo' => $motivo,
                'Ajuste_inventario_fecha' => $fecha,
                'Ajuste_inventario_Eliminado' => 'N',
            ], $auditAjuste);

            $ajuste = $this->ajusteRepository->create($payloadAjuste);

            // 5. Registrar Movimiento de Salida en Kardex (Movimiento_producto)
            $movimientoId = $this->inventarioService->getNextMovimientoId();
            $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

            $payloadMov = array_merge([
                'Movimiento_productoId' => $movimientoId,
                'Movimiento_productoCantidadPresentacion' => (string) $cantidad,
                'Movimiento_productoDocumentoOperacionId' => $ajusteId,
                'Movimiento_productoTipoMovimiento' => 'S',
                'Movimiento_productoCostoPrecioUnitario' => (string) $costoUnitario,
                'Movimiento_productoCantidadEntrada' => '0',
                'Movimiento_productoCantidadSalida' => (string) $cantidad,
                'Movimiento_productoCantidadSaldo' => (string) $nuevoStock,
                'Movimiento_productoFecha_Movimiento' => $fecha,
                'Movimiento_producto_ProductoId' => $productoId,
                'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => $unidadMedidaId,
                'Movimiento_productoEliminado' => 'N',
            ], $auditMov);

            MovimientoProducto::create($payloadMov);

            // 6. Emitir Notificación en Tiempo Real con datos de tarjeta UI
            try {
                $this->notificacionService->notificarMermaRegistrada(
                    $ajuste,
                    $producto,
                    $stockActual,
                    $nuevoStock,
                    [
                        'costo_unitario' => $costoUnitario,
                        'precio_venta_unitario' => $precioVentaUnitario,
                        'costo_total_perdido' => $costoTotalPerdido,
                        'venta_total_perdida' => $ventaTotalPerdida,
                    ],
                    $silenciosa
                );
            } catch (Exception $e) {
                // Log de aviso sin interrumpir transacción
            }

            $ajusteLoaded = $ajuste->fresh(['producto']);
            return $this->enriquecerAjusteConFinanzas($ajusteLoaded, $costoUnitario, $precioVentaUnitario);
        });
    }

    /**
     * Anulación lógica de un Ajuste de Inventario con reversión de stock y notificación
     */
    public function eliminarAjuste(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $ajuste = $this->obtenerAjustePorId($id);

            $productoId = $ajuste->Ajuste_inventario_ProductoId;
            $cantidad = (float) $ajuste->Ajuste_inventario_cantidad;

            // 1. Revertir stock en Producto
            $producto = Producto::where('ProductoId', $productoId)->lockForUpdate()->first();
            if ($producto) {
                $stockActual = (float) $producto->ProductoStockActual;
                $nuevoStock = $stockActual + $cantidad;

                $auditProducto = AuditHelper::getModificationAudit('Producto');
                $producto->update(array_merge([
                    'ProductoStockActual' => (string) $nuevoStock,
                ], $auditProducto));

                // 2. Contra-movimiento en Kardex para registrar la reversión
                $movimientoId = $this->inventarioService->getNextMovimientoId();
                $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');

                $payloadMov = array_merge([
                    'Movimiento_productoId' => $movimientoId,
                    'Movimiento_productoCantidadPresentacion' => (string) $cantidad,
                    'Movimiento_productoDocumentoOperacionId' => 'REV-' . $ajuste->Ajuste_inventario_id,
                    'Movimiento_productoTipoMovimiento' => 'E',
                    'Movimiento_productoCostoPrecioUnitario' => '0.00',
                    'Movimiento_productoCantidadEntrada' => (string) $cantidad,
                    'Movimiento_productoCantidadSalida' => '0',
                    'Movimiento_productoCantidadSaldo' => (string) $nuevoStock,
                    'Movimiento_productoFecha_Movimiento' => now(),
                    'Movimiento_producto_ProductoId' => $productoId,
                    'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => 'UND-00001',
                    'Movimiento_productoEliminado' => 'N',
                ], $auditMov);

                MovimientoProducto::create($payloadMov);

                // 3. Notificación de Reversión en tiempo real
                try {
                    $this->notificacionService->notificarReversionMerma(
                        $ajuste,
                        $producto,
                        $stockActual,
                        $nuevoStock,
                        $cantidad
                    );
                } catch (Exception $e) {}
            }

            // 4. Eliminar lógicamente el ajuste
            $auditAjuste = AuditHelper::getDeletionAudit('Ajuste_inventario_');

            return $this->ajusteRepository->deleteLogico($id, $auditAjuste);
        });
    }

    /**
     * Restaurar un ajuste previamente anulado (reactiva el descuento si hay stock).
     */
    public function restaurarAjuste(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $ajuste = $this->obtenerAjustePorId($id, true);

            $productoId = $ajuste->Ajuste_inventario_ProductoId;
            $cantidad = (float) $ajuste->Ajuste_inventario_cantidad;

            $producto = Producto::where('ProductoId', $productoId)->lockForUpdate()->first();
            if (!$producto) {
                throw ValidationException::withMessages([
                    'producto' => 'El producto asociado no existe.',
                ]);
            }

            $stockActual = (float) $producto->ProductoStockActual;
            if ($cantidad > $stockActual) {
                throw ValidationException::withMessages([
                    'stock' => "No se puede restaurar el ajuste porque el stock disponible actual ({$stockActual}) es menor a la merma a descontar ({$cantidad}).",
                ]);
            }

            // Descontar nuevamente
            $nuevoStock = $stockActual - $cantidad;
            $auditProducto = AuditHelper::getModificationAudit('Producto');
            $producto->update(array_merge([
                'ProductoStockActual' => (string) $nuevoStock,
            ], $auditProducto));

            // Kardex salida
            $movimientoId = $this->inventarioService->getNextMovimientoId();
            $auditMov = AuditHelper::getCreationAudit('Movimiento_producto');
            MovimientoProducto::create(array_merge([
                'Movimiento_productoId' => $movimientoId,
                'Movimiento_productoCantidadPresentacion' => (string) $cantidad,
                'Movimiento_productoDocumentoOperacionId' => 'REST-' . $ajuste->Ajuste_inventario_id,
                'Movimiento_productoTipoMovimiento' => 'S',
                'Movimiento_productoCostoPrecioUnitario' => '0.00',
                'Movimiento_productoCantidadEntrada' => '0',
                'Movimiento_productoCantidadSalida' => (string) $cantidad,
                'Movimiento_productoCantidadSaldo' => (string) $nuevoStock,
                'Movimiento_productoFecha_Movimiento' => now(),
                'Movimiento_producto_ProductoId' => $productoId,
                'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId' => 'UND-00001',
                'Movimiento_productoEliminado' => 'N',
            ], $auditMov));

            $auditRestore = AuditHelper::getModificationAudit('Ajuste_inventario_');

            return $this->ajusteRepository->restore($id, $auditRestore);
        });
    }

    /**
     * Enriquecer un modelo de ajuste con sus métricas financieras calculadas
     */
    public function enriquecerAjusteConFinanzas(AjusteInventario $ajuste, ?float $costo = null, ?float $venta = null): AjusteInventario
    {
        $medidaBase = DB::table('Detalle_Producto_medida as dpm')
            ->join('unidades_medida as um', 'um.unidades_medidaId', '=', 'dpm.Detalle_Producto_medida_unidades_medidaId')
            ->where('dpm.Detalle_Producto_medida_ProductoId', $ajuste->Ajuste_inventario_ProductoId)
            ->where('dpm.Detalle_Producto_medidaEliminado', 'N')
            ->where(function ($q) {
                $q->where('dpm.Detalle_Producto_medida_factor_conversion', 1)
                  ->orWhere('um.unidades_medidaEsBase', 1);
            })
            ->select('dpm.*', 'um.unidades_medidaAbreviatura', 'um.unidades_medidaDescripcionUnidades')
            ->first();

        if (!$medidaBase) {
            $medidaBase = DB::table('Detalle_Producto_medida as dpm')
                ->join('unidades_medida as um', 'um.unidades_medidaId', '=', 'dpm.Detalle_Producto_medida_unidades_medidaId')
                ->where('dpm.Detalle_Producto_medida_ProductoId', $ajuste->Ajuste_inventario_ProductoId)
                ->where('dpm.Detalle_Producto_medidaEliminado', 'N')
                ->select('dpm.*', 'um.unidades_medidaAbreviatura', 'um.unidades_medidaDescripcionUnidades')
                ->first();
        }

        if ($costo === null || $venta === null) {
            $costo = $medidaBase ? (float) $medidaBase->Detalle_Producto_medida_precio_compra : 0.0;
            $venta = $medidaBase ? (float) $medidaBase->Detalle_Producto_medida_precio_venta : 0.0;
        }

        $cantidad = (float) $ajuste->Ajuste_inventario_cantidad;

        $ajuste->costo_unitario = $costo;
        $ajuste->precio_venta_unitario = $venta;
        $ajuste->impacto_financiero_costo = round($cantidad * $costo, 2);
        $ajuste->impacto_financiero_venta = round($cantidad * $venta, 2);
        $ajuste->moneda = 'S/';
        $ajuste->unidad_medida = $medidaBase ? ($medidaBase->unidades_medidaAbreviatura ?: $medidaBase->unidades_medidaDescripcionUnidades) : 'UND';
        $ajuste->unidad_medida_nombre = $medidaBase ? $medidaBase->unidades_medidaDescripcionUnidades : 'Unidad';

        return $ajuste;
    }

    /**
     * Obtener listado completo de ajustes enriquecidos con finanzas para reportes
     */
    public function obtenerAjustesParaReporte(array $filtros = []): Collection
    {
        $ajustes = $this->ajusteRepository->getAll($filtros, 0); // 0 = sin paginar
        $ajustes->transform(function ($a) {
            return $this->enriquecerAjusteConFinanzas($a);
        });
        return $ajustes;
    }

    /**
     * Generar contenido del reporte en formato Excel (.xls con formato HTML / XML compatible con Excel)
     */
    public function generarReporteExcel(array $filtros = []): string
    {
        $resumen = $this->obtenerImpactoFinanciero($filtros);
        $ajustes = $this->obtenerAjustesParaReporte($filtros);

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="utf-8">';
        $html .= '<style>
            table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial; font-size: 11pt; }
            th { background-color: #1e3a8a; color: #ffffff; font-weight: bold; border: 1px solid #94a3b8; padding: 6px 10px; text-align: left; }
            td { border: 1px solid #cbd5e1; padding: 5px 8px; }
            .header-title { font-size: 16pt; font-weight: bold; color: #1e3a8a; }
            .header-sub { font-size: 10pt; color: #64748b; }
            .kpi-title { background-color: #f1f5f9; font-weight: bold; }
            .kpi-val { font-weight: bold; color: #b91c1c; }
            .total-row { background-color: #e2e8f0; font-weight: bold; }
            .num { text-align: right; mso-number-format: "\#,\#\#0\.00"; }
            .currency { text-align: right; mso-number-format: "\S\/\ \#\,\#\#0\.00"; color: #dc2626; font-weight: bold; }
        </style></head><body>';

        $html .= '<table>';
        $html .= '<tr><td colspan="9" class="header-title">COMERCIAL VALENCIA S.A.C. - CONTROL DE INVENTARIO</td></tr>';
        $html .= '<tr><td colspan="9" class="header-sub">REPORTE CONSOLIDADO DE MERMAS, DESPERDICIOS E IMPACTO FINANCIERO</td></tr>';
        $html .= '<tr><td colspan="9" class="header-sub">Fecha de Emisión: ' . now()->format('d/m/Y H:i:s') . '</td></tr>';
        $html .= '<tr><td colspan="9"></td></tr>';

        // Resumen KPIs
        $html .= '<tr><th colspan="9">RESUMEN EJECUTIVO DE PÉRDIDAS</th></tr>';
        $html .= '<tr>
            <td colspan="2" class="kpi-title">Total Ajustes Registrados:</td><td>' . $resumen['total_ajustes'] . '</td>
            <td colspan="2" class="kpi-title">Total Unidades Mermadas:</td><td class="num">' . number_format($resumen['total_unidades'], 2) . '</td>
            <td colspan="2" class="kpi-title">Pérdida Financiera en Costo:</td><td class="currency">S/ ' . number_format($resumen['costo_total_perdido'], 2) . '</td>
        </tr>';
        $html .= '<tr><td colspan="9"></td></tr>';

        // Detalle
        $html .= '<tr>
            <th>CÓDIGO</th>
            <th>FECHA</th>
            <th>ID PRODUCTO</th>
            <th>PRODUCTO</th>
            <th>TIPO DE AJUSTE</th>
            <th>CANTIDAD</th>
            <th>COSTO UNIT.</th>
            <th>PÉRDIDA COSTO (S/)</th>
            <th>MOTIVO / OBSERVACIÓN</th>
        </tr>';

        foreach ($ajustes as $a) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($a->Ajuste_inventario_id) . '</td>';
            $html .= '<td>' . ($a->Ajuste_inventario_fecha ? $a->Ajuste_inventario_fecha->format('d/m/Y H:i') : '') . '</td>';
            $html .= '<td>' . htmlspecialchars($a->Ajuste_inventario_ProductoId) . '</td>';
            $html .= '<td>' . htmlspecialchars($a->producto->ProductoNombre ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($a->Ajuste_inventario_tipo) . '</td>';
            $html .= '<td class="num">' . number_format((float) $a->Ajuste_inventario_cantidad, 2) . '</td>';
            $html .= '<td class="num">S/ ' . number_format($a->costo_unitario ?? 0, 2) . '</td>';
            $html .= '<td class="currency">S/ ' . number_format($a->impacto_financiero_costo ?? 0, 2) . '</td>';
            $html .= '<td>' . htmlspecialchars($a->Ajuste_inventario_motivo) . '</td>';
            $html .= '</tr>';
        }

        // Totales al final
        $html .= '<tr class="total-row">';
        $html .= '<td colspan="5">TOTALES CONSOLIDADOS:</td>';
        $html .= '<td class="num">' . number_format($resumen['total_unidades'], 2) . '</td>';
        $html .= '<td>-</td>';
        $html .= '<td class="currency">S/ ' . number_format($resumen['costo_total_perdido'], 2) . '</td>';
        $html .= '<td></td>';
        $html .= '</tr>';

        $html .= '</table></body></html>';

        return $html;
    }
}
