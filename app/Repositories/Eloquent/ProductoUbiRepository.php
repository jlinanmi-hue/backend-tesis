<?php

namespace App\Repositories\Eloquent;

use App\Models\ProductoUbi;
use App\Repositories\Contracts\ProductoUbiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductoUbiRepository implements ProductoUbiRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = ProductoUbi::query();

        if (!empty($filters['eliminados']) && ($filters['eliminados'] === 'true' || $filters['eliminados'] === '1' || $filters['eliminados'] === 1 || $filters['eliminados'] === true)) {
            $query->where('producto_ubi_Eliminado', 'S');
        } elseif (!empty($filters['todos']) && ($filters['todos'] === 'true' || $filters['todos'] === '1' || $filters['todos'] === 1 || $filters['todos'] === true)) {
            // No filtrar por producto_ubi_Eliminado
        } else {
            $query->where('producto_ubi_Eliminado', 'N');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('producto_ubi_descripcion', 'like', "%{$search}%")
                  ->orWhere('producto_ubi_id', 'like', "%{$search}%")
                  ->orWhere('producto_ubi_observacion', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['estado'])) {
            $query->where('producto_ubi_estado', $filters['estado']);
        }

        $query->orderBy('producto_ubi_FechaCreacion', 'desc');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?ProductoUbi
    {
        $query = ProductoUbi::with('productos')->where('producto_ubi_id', $id);

        if (!$includeDeleted) {
            $query->where('producto_ubi_Eliminado', 'N');
        }

        return $query->first();
    }

    public function create(array $data): ProductoUbi
    {
        return ProductoUbi::create($data);
    }

    public function update(string $id, array $data): ProductoUbi
    {
        $ubi = $this->findById($id, true);
        $ubi->update($data);

        return $ubi->fresh(['productos']);
    }

    public function deleteLogico(string $id, array $auditData): bool
    {
        $ubi = $this->findById($id);
        if (!$ubi) {
            return false;
        }

        return (bool) $ubi->update(array_merge([
            'producto_ubi_Eliminado' => 'S',
            'producto_ubi_estado' => 'I',
        ], $auditData));
    }

    public function restore(string $id, array $auditData): bool
    {
        $ubi = $this->findById($id, true);
        if (!$ubi) {
            return false;
        }

        return (bool) $ubi->update(array_merge([
            'producto_ubi_Eliminado' => 'N',
            'producto_ubi_estado' => 'A',
            'producto_ubi_UsuarioEliminacion' => null,
            'producto_ubi_HostEliminacion' => null,
            'producto_ubi_FechaEliminacion' => null,
        ], $auditData));
    }

    public function getNextId(): string
    {
        $last = DB::table('producto_ubi')
            ->select('producto_ubi_id')
            ->where('producto_ubi_id', 'like', 'UBI-%')
            ->orderByRaw('LEN(producto_ubi_id) DESC, producto_ubi_id DESC')
            ->first();

        if (!$last) {
            return 'UBI-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->producto_ubi_id);
        $nextNumber = ((int) $numberPart) + 1;

        return 'UBI-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
