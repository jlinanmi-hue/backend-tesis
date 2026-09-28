<?php

namespace App\Repositories\Eloquent;

use App\Models\UnidadesMedida;
use App\Repositories\Contracts\UnidadesMedidaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UnidadesMedidaRepository implements UnidadesMedidaRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = UnidadesMedida::query();

        if (!empty($filters['eliminados']) && ($filters['eliminados'] === 'true' || $filters['eliminados'] === '1' || $filters['eliminados'] === 1 || $filters['eliminados'] === true)) {
            $query->where('unidades_medidaEliminado', 'S');
        } elseif (!empty($filters['todos']) && ($filters['todos'] === 'true' || $filters['todos'] === '1' || $filters['todos'] === 1 || $filters['todos'] === true)) {
            // No filtrar por unidades_medidaEliminado
        } else {
            $query->where('unidades_medidaEliminado', 'N');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('unidades_medidaDescripcionUnidades', 'like', "%{$search}%")
                  ->orWhere('unidades_medidaAbreviatura', 'like', "%{$search}%")
                  ->orWhere('unidades_medidaId', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['estado'])) {
            $query->where('unidades_medidaEstadoUnidades', $filters['estado']);
        }

        $query->orderBy('unidades_medidaFechaCreacion', 'desc');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?UnidadesMedida
    {
        $query = UnidadesMedida::with('detalleProductoMedidas')->where('unidades_medidaId', $id);

        if (!$includeDeleted) {
            $query->where('unidades_medidaEliminado', 'N');
        }

        return $query->first();
    }

    public function findByDescripcion(string $descripcion): ?UnidadesMedida
    {
        return UnidadesMedida::where('unidades_medidaDescripcionUnidades', $descripcion)
            ->where('unidades_medidaEliminado', 'N')
            ->first();
    }

    public function create(array $data): UnidadesMedida
    {
        return UnidadesMedida::create($data);
    }

    public function update(string $id, array $data): UnidadesMedida
    {
        $unidad = $this->findById($id, true);
        $unidad->update($data);

        return $unidad->fresh();
    }

    public function deleteLogico(string $id, array $auditData): bool
    {
        $unidad = $this->findById($id);
        if (!$unidad) {
            return false;
        }

        return (bool) $unidad->update(array_merge([
            'unidades_medidaEliminado' => 'S',
            'unidades_medidaEstadoUnidades' => 'I',
        ], $auditData));
    }

    public function restore(string $id, array $auditData): bool
    {
        $unidad = $this->findById($id, true);
        if (!$unidad) {
            return false;
        }

        return (bool) $unidad->update(array_merge([
            'unidades_medidaEliminado' => 'N',
            'unidades_medidaEstadoUnidades' => 'A',
            'unidades_medidaUsuarioEliminacion' => null,
            'unidades_medidaHostEliminacion' => null,
            'unidades_medidaFechaEliminacion' => null,
        ], $auditData));
    }

    public function getNextId(): string
    {
        $last = DB::table('unidades_medida')
            ->select('unidades_medidaId')
            ->where('unidades_medidaId', 'like', 'UND-%')
            ->orderByRaw('LEN(unidades_medidaId) DESC, unidades_medidaId DESC')
            ->first();

        if (!$last) {
            return 'UND-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->unidades_medidaId);
        $nextNumber = ((int) $numberPart) + 1;

        return 'UND-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
