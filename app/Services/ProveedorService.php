<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Repositories\Contracts\ProveedorRepositoryInterface;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProveedorService
{
    public function __construct(
        protected ProveedorRepositoryInterface $proveedorRepository
    ) {}

    /**
     * List paginated or filtered suppliers.
     */
    public function listarProveedores(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->proveedorRepository->getAll($filters, $perPage);
    }

    /**
     * Get a specific supplier by ID.
     */
    public function obtenerProveedorPorId(string $id, bool $includeDeleted = false): Proveedor
    {
        $proveedor = $this->proveedorRepository->findById($id, $includeDeleted);

        if (!$proveedor) {
            throw new Exception("Proveedor con ID '{$id}' no encontrado.");
        }

        return $proveedor;
    }

    /**
     * Create a new supplier.
     * Transactional operation.
     */
    public function crearProveedor(array $datos): Proveedor
    {
        return DB::transaction(function () use ($datos) {
            // 1. Validar que el RUC no se encuentre registrado (excepto si es comodín sin RUC)
            $ruc = trim($datos['ProveedorRuc'] ?? '');
            if (!empty($ruc) && $ruc !== '10000000000' && $ruc !== '00000000000') {
                if ($this->proveedorRepository->findByRuc($ruc)) {
                    throw ValidationException::withMessages([
                        'ProveedorRuc' => 'El número de RUC ingresado ya se encuentra registrado.',
                    ]);
                }
            }

            // 2. Generar ProveedorId correlativo y auditoría
            $proveedorId = $this->proveedorRepository->getNextId();
            $audit = AuditHelper::getCreationAudit('Proveedor');

            $payload = array_merge([
                'ProveedorTipoContribuyente' => 'GENERAL',
                'ProveedorActividadEconomica' => '-',
                'ProveedorTelefono' => '-',
            ], $datos, [
                'ProveedorId' => $proveedorId,
                'ProveedorEstado' => $datos['ProveedorEstado'] ?? 'A',
                'ProveedorEliminado' => 'N',
            ], $audit);

            return $this->proveedorRepository->create($payload);
        });
    }

    /**
     * Update an existing supplier.
     * Transactional operation.
     */
    public function actualizarProveedor(string $id, array $datos): Proveedor
    {
        return DB::transaction(function () use ($id, $datos) {
            $proveedor = $this->obtenerProveedorPorId($id);

            // 1. Validar RUC único si cambió (excepto si es comodín sin RUC)
            if (isset($datos['ProveedorRuc']) && $datos['ProveedorRuc'] !== $proveedor->ProveedorRuc) {
                $ruc = trim($datos['ProveedorRuc']);
                if ($ruc !== '10000000000' && $ruc !== '00000000000') {
                    $existenteRuc = $this->proveedorRepository->findByRuc($ruc);
                    if ($existenteRuc && $existenteRuc->ProveedorId !== $id) {
                        throw ValidationException::withMessages([
                            'ProveedorRuc' => 'El número de RUC ingresado ya pertenece a otro proveedor.',
                        ]);
                    }
                }
            }

            // 2. Aplicar auditoría de modificación
            $audit = AuditHelper::getModificationAudit('Proveedor');
            $payload = array_merge($datos, $audit);

            return $this->proveedorRepository->update($id, $payload);
        });
    }

    /**
     * Activate or Deactivate supplier.
     */
    public function cambiarEstadoProveedor(string $id, string $nuevoEstado): Proveedor
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $proveedor = $this->obtenerProveedorPorId($id);

            if (!in_array($nuevoEstado, ['A', 'I'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'El estado debe ser A (Activo) o I (Inactivo).',
                ]);
            }

            $audit = AuditHelper::getModificationAudit('Proveedor');
            $payload = array_merge([
                'ProveedorEstado' => $nuevoEstado,
            ], $audit);

            return $this->proveedorRepository->update($id, $payload);
        });
    }

    /**
     * Soft delete supplier.
     */
    public function eliminarProveedor(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerProveedorPorId($id);

            $audit = AuditHelper::getDeletionAudit('Proveedor');

            return $this->proveedorRepository->deleteLogico($id, $audit);
        });
    }

    /**
     * Restore supplier.
     */
    public function restaurarProveedor(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerProveedorPorId($id, true);

            $audit = AuditHelper::getModificationAudit('Proveedor');

            return $this->proveedorRepository->restore($id, $audit);
        });
    }

    /**
     * Quick search for autocomplete / purchase order modal.
     */
    public function buscarProveedoresRapido(string $term, int $limit = 10): Collection
    {
        return $this->proveedorRepository->buscarRapido($term, $limit);
    }

    /**
     * Resumen de contadores para KPIs del directorio de proveedores.
     */
    public function obtenerResumenDirectorio(): array
    {
        $totalActivos = Proveedor::where('ProveedorEliminado', 'N')->where('ProveedorEstado', 'A')->count();
        $totalInactivos = Proveedor::where('ProveedorEliminado', 'N')->where('ProveedorEstado', 'I')->count();
        $totalEliminados = Proveedor::where('ProveedorEliminado', 'S')->count();
        $totalProveedores = $totalActivos + $totalInactivos;

        return [
            'total_proveedores' => $totalProveedores,
            'total_activos' => $totalActivos,
            'total_inactivos' => $totalInactivos,
            'total_eliminados' => $totalEliminados,
        ];
    }

    /**
     * Obtener productos suministrados por un proveedor específico.
     */
    public function obtenerProductosProveedor(string $proveedorId): array
    {
        $proveedor = $this->obtenerProveedorPorId($proveedorId);

        // 1. Productos asociados en Producto_Proveedor
        $idsAsociados = DB::table('Producto_Proveedor')
            ->where('Producto_Proveedor_ProveedorId', $proveedorId)
            ->where('Producto_ProveedorEliminado', 'N')
            ->pluck('Producto_Proveedor_ProductoId')
            ->toArray();

        // 2. Productos comprados históricamente a este proveedor en Orden_Compra
        $idsHistoricos = DB::table('Detalle_Orden_Compra as doc')
            ->join('Orden_Compra as oc', 'doc.Detalle_Orden_Compra_Orden_CompraId', '=', 'oc.Orden_CompraId')
            ->where('oc.Orden_Compra_ProveedorId', $proveedorId)
            ->where('doc.Detalle_Orden_CompraEliminado', 'N')
            ->where('oc.Orden_CompraEliminado', 'N')
            ->pluck('doc.Detalle_ProductoId')
            ->toArray();

        $todosIds = array_unique(array_merge($idsAsociados, $idsHistoricos));

        if (empty($todosIds)) {
            return [
                'proveedor' => [
                    'id' => $proveedor->ProveedorId,
                    'razon_social' => $proveedor->ProveedorRazonSocial,
                    'ruc' => $proveedor->ProveedorRuc,
                    'telefono' => $proveedor->ProveedorTelefono,
                ],
                'total_productos' => 0,
                'productos' => [],
            ];
        }

        $productos = Producto::with([
            'categoria',
            'detalleProductoMedidas' => function ($q) {
                $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
            }
        ])
        ->whereIn('ProductoId', $todosIds)
        ->where('ProductoEliminado', 'N')
        ->get();

        $resultado = $productos->map(function ($p) use ($proveedorId) {
            $ultimaCompra = DB::table('Detalle_Orden_Compra as doc')
                ->join('Orden_Compra as oc', 'doc.Detalle_Orden_Compra_Orden_CompraId', '=', 'oc.Orden_CompraId')
                ->where('oc.Orden_Compra_ProveedorId', $proveedorId)
                ->where('doc.Detalle_ProductoId', $p->ProductoId)
                ->where('doc.Detalle_Orden_CompraEliminado', 'N')
                ->where('oc.Orden_CompraEliminado', 'N')
                ->orderBy('oc.Orden_CompraFecha', 'desc')
                ->select([
                    'doc.Detalle_Orden_CompraPrecioUnitario as precio',
                    'doc.Detalle_UnidadMedidaId as unidad_id',
                    'oc.Orden_CompraFecha as fecha',
                ])
                ->first();

            $unidades = $p->detalleProductoMedidas->map(function ($det) {
                return [
                    'unidades_medidaId' => $det->Detalle_Producto_medida_unidades_medidaId,
                    'descripcion' => $det->unidadMedida->unidades_medidaDescripcionUnidades ?? 'Unidad',
                    'abreviatura' => $det->unidadMedida->unidades_medidaAbreviatura ?? 'UND',
                    'factor_conversion' => (int) $det->Detalle_Producto_medida_factor_conversion,
                    'precio_compra' => (float) $det->Detalle_Producto_medida_precio_compra,
                ];
            });

            $unidadBase = $unidades->first(fn ($u) => $u['factor_conversion'] === 1) ?? $unidades->first();
            $precioPactado = $ultimaCompra ? (float) $ultimaCompra->precio : ($unidadBase['precio_compra'] ?? 0);

            return [
                'producto_id' => $p->ProductoId,
                'nombre' => $p->ProductoNombre,
                'marca' => $p->ProductoMarca,
                'categoria' => $p->categoria->Categoria_ProductoDescripcion_categoria ?? null,
                'stock_actual' => (float) $p->ProductoStockActual,
                'stock_minimo' => (float) $p->ProductoStockMinimo,
                'ultimo_precio_pactado' => $precioPactado,
                'fecha_ultimo_pedido' => $ultimaCompra ? $ultimaCompra->fecha : null,
                'unidad_base' => $unidadBase['abreviatura'] ?? 'UND',
                'unidades_medida' => $unidades,
            ];
        })->values()->all();

        return [
            'proveedor' => [
                'id' => $proveedor->ProveedorId,
                'razon_social' => $proveedor->ProveedorRazonSocial,
                'ruc' => $proveedor->ProveedorRuc,
                'telefono' => $proveedor->ProveedorTelefono,
            ],
            'total_productos' => count($resultado),
            'productos' => $resultado,
        ];
    }
}
