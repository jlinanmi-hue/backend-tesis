<?php

namespace App\Repositories\Eloquent;

use App\Models\OrdenCompra;
use App\Repositories\Contracts\OrdenCompraRepositoryInterface;
use App\Support\AuditHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrdenCompraRepository implements OrdenCompraRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = OrdenCompra::query()
            ->with(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);

        // Excluir eliminados a menos que se solicite expresamente
        if (empty($filters['include_deleted'])) {
            $query->where('Orden_CompraEliminado', 'N');
        }

        // Búsqueda por texto (Código OC, RUC o Razón Social de proveedor)
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('Orden_CompraId', 'LIKE', "%{$search}%")
                  ->orWhere('Orden_CompraObservacion', 'LIKE', "%{$search}%")
                  ->orWhereHas('proveedor', function ($provQuery) use ($search) {
                      $provQuery->where('ProveedorRazonSocial', 'LIKE', "%{$search}%")
                                ->orWhere('ProveedorRuc', 'LIKE', "%{$search}%")
                                ->orWhere('ProveedorTelefono', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filtro por Estado
        if (!empty($filters['estado'])) {
            $query->where('Orden_CompraEstado', strtoupper($filters['estado']));
        }

        // Filtro por Proveedor
        if (!empty($filters['proveedor_id'])) {
            $query->where('Orden_Compra_ProveedorId', $filters['proveedor_id']);
        }

        // Filtro por rango de fechas
        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('Orden_CompraFecha', '>=', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('Orden_CompraFecha', '<=', $filters['fecha_hasta']);
        }

        // Ordenamiento descendente por fecha y código
        $query->orderBy('Orden_CompraFecha', 'desc')
              ->orderBy('Orden_CompraId', 'desc');

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?OrdenCompra
    {
        $query = OrdenCompra::query()
            ->with(['proveedor', 'detalles.producto', 'detalles.unidadMedida'])
            ->where('Orden_CompraId', $id);

        if (!$includeDeleted) {
            $query->where('Orden_CompraEliminado', 'N');
        }

        return $query->first();
    }

    public function create(array $data): OrdenCompra
    {
        if (empty($data['Orden_CompraId'])) {
            $data['Orden_CompraId'] = $this->generateNextId();
        }

        $audit = AuditHelper::getCreationAudit('Orden_Compra');
        $data = array_merge($data, $audit);
        $data['Orden_CompraEliminado'] = 'N';

        return OrdenCompra::create($data);
    }

    public function update(string $id, array $data): OrdenCompra
    {
        $orden = $this->findById($id);

        if (!$orden) {
            throw new \Exception("Orden de Compra con ID '{$id}' no encontrada.");
        }

        $audit = AuditHelper::getModificationAudit('Orden_Compra');
        $data = array_merge($data, $audit);

        $orden->update($data);
        return $orden->fresh(['proveedor', 'detalles.producto', 'detalles.unidadMedida']);
    }

    public function delete(string $id): bool
    {
        $orden = $this->findById($id);

        if (!$orden) {
            return false;
        }

        $audit = AuditHelper::getDeletionAudit('Orden_Compra');
        return $orden->update($audit);
    }

    public function restore(string $id): bool
    {
        $orden = $this->findById($id, true);

        if (!$orden) {
            return false;
        }

        $audit = AuditHelper::getModificationAudit('Orden_Compra');
        return $orden->update(array_merge($audit, [
            'Orden_CompraEliminado' => 'N',
        ]));
    }

    public function generateNextId(): string
    {
        $lastRecord = DB::table('Orden_Compra')
            ->select('Orden_CompraId')
            ->where('Orden_CompraId', 'LIKE', 'OC-%')
            ->orderBy('Orden_CompraId', 'desc')
            ->first();

        if (!$lastRecord) {
            return 'OC-00001';
        }

        $lastNumber = (int) substr($lastRecord->Orden_CompraId, 3);
        $nextNumber = $lastNumber + 1;

        return sprintf('OC-%05d', $nextNumber);
    }
}
