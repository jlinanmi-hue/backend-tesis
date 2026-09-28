<?php

namespace App\Repositories\Eloquent;

use App\Models\AjusteInventario;
use App\Repositories\Contracts\AjusteInventarioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AjusteInventarioRepository implements AjusteInventarioRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = AjusteInventario::with(['producto'])
            ->where('Ajuste_inventario_Eliminado', 'N');

        if (!empty($filters['productoId'])) {
            $query->where('Ajuste_inventario_ProductoId', $filters['productoId']);
        }

        if (!empty($filters['tipo'])) {
            $query->where('Ajuste_inventario_tipo', $filters['tipo']);
        }

        if (!empty($filters['fechaDesde'])) {
            $query->whereDate('Ajuste_inventario_fecha', '>=', $filters['fechaDesde']);
        }

        if (!empty($filters['fechaHasta'])) {
            $query->whereDate('Ajuste_inventario_fecha', '<=', $filters['fechaHasta']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('Ajuste_inventario_id', 'like', "%{$search}%")
                  ->orWhere('Ajuste_inventario_motivo', 'like', "%{$search}%")
                  ->orWhereHas('producto', function ($qp) use ($search) {
                      $qp->where('ProductoNombre', 'like', "%{$search}%");
                  });
            });
        }

        $query->orderBy('Ajuste_inventario_fecha', 'desc');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?AjusteInventario
    {
        $query = AjusteInventario::with(['producto'])->where('Ajuste_inventario_id', $id);

        if (!$includeDeleted) {
            $query->where('Ajuste_inventario_Eliminado', 'N');
        }

        return $query->first();
    }

    public function create(array $data): AjusteInventario
    {
        return AjusteInventario::create($data);
    }

    public function deleteLogico(string $id, array $auditData): bool
    {
        $ajuste = $this->findById($id);
        if (!$ajuste) {
            return false;
        }

        return (bool) $ajuste->update(array_merge([
            'Ajuste_inventario_Eliminado' => 'S',
        ], $auditData));
    }

    public function restore(string $id, array $auditData): bool
    {
        $ajuste = $this->findById($id, true);
        if (!$ajuste) {
            return false;
        }

        return (bool) $ajuste->update(array_merge([
            'Ajuste_inventario_Eliminado' => 'N',
            'Ajuste_inventario_UsuarioEliminacion' => null,
            'Ajuste_inventario_HostEliminacion' => null,
            'Ajuste_inventario_FechaEliminacion' => null,
        ], $auditData));
    }

    public function getNextId(): string
    {
        $last = DB::table('Ajuste_inventario')
            ->select('Ajuste_inventario_id')
            ->where('Ajuste_inventario_id', 'like', 'AJU-%')
            ->orderByRaw('LEN(Ajuste_inventario_id) DESC, Ajuste_inventario_id DESC')
            ->first();

        if (!$last) {
            return 'AJU-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->Ajuste_inventario_id);
        $nextNumber = ((int) $numberPart) + 1;

        return 'AJU-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }

    public function getResumen(): array
    {
        $baseQuery = AjusteInventario::where('Ajuste_inventario_Eliminado', 'N');

        $totalAjustes = (clone $baseQuery)->count();
        $totalUnidades = (float) ((clone $baseQuery)->sum('Ajuste_inventario_cantidad') ?? 0);

        $porTipo = (clone $baseQuery)
            ->select('Ajuste_inventario_tipo as tipo', DB::raw('COUNT(*) as total_registros'), DB::raw('SUM(Ajuste_inventario_cantidad) as total_unidades'))
            ->groupBy('Ajuste_inventario_tipo')
            ->get();

        return [
            'total_ajustes' => $totalAjustes,
            'total_unidades_ajustadas' => $totalUnidades,
            'por_tipo' => $porTipo,
        ];
    }
}
