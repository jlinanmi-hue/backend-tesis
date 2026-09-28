<?php

namespace App\Repositories\Eloquent;

use App\Models\CanalPedido;
use App\Repositories\Contracts\CanalPedidoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CanalPedidoRepository implements CanalPedidoRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = CanalPedido::query();

        if (!empty($filters['eliminados']) && ($filters['eliminados'] === 'true' || $filters['eliminados'] === '1' || $filters['eliminados'] === 1 || $filters['eliminados'] === true)) {
            $query->where('Canal_pedidoEliminado', 'S');
        } elseif (!empty($filters['todos']) && ($filters['todos'] === 'true' || $filters['todos'] === '1' || $filters['todos'] === 1 || $filters['todos'] === true)) {
            // No filtrar por Canal_pedidoEliminado
        } else {
            $query->where('Canal_pedidoEliminado', 'N');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('Canal_pedidoDescripcion', 'like', "%{$search}%")
                  ->orWhere('Canal_pedidoId', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['estado'])) {
            $query->where('Canal_pedidoEstado', $filters['estado']);
        }

        $query->orderBy('Canal_pedidoFechaCreacion', 'desc');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?CanalPedido
    {
        $query = CanalPedido::with('pedidos')->where('Canal_pedidoId', $id);

        if (!$includeDeleted) {
            $query->where('Canal_pedidoEliminado', 'N');
        }

        return $query->first();
    }

    public function findByDescripcion(string $descripcion): ?CanalPedido
    {
        return CanalPedido::where('Canal_pedidoDescripcion', $descripcion)
            ->where('Canal_pedidoEliminado', 'N')
            ->first();
    }

    public function create(array $data): CanalPedido
    {
        return CanalPedido::create($data);
    }

    public function update(string $id, array $data): CanalPedido
    {
        $canal = $this->findById($id, true);
        $canal->update($data);

        return $canal->fresh();
    }

    public function deleteLogico(string $id, array $auditData): bool
    {
        $canal = $this->findById($id);
        if (!$canal) {
            return false;
        }

        return (bool) $canal->update(array_merge([
            'Canal_pedidoEliminado' => 'S',
            'Canal_pedidoEstado' => 'I',
        ], $auditData));
    }

    public function restore(string $id, array $auditData): bool
    {
        $canal = $this->findById($id, true);
        if (!$canal) {
            return false;
        }

        return (bool) $canal->update(array_merge([
            'Canal_pedidoEliminado' => 'N',
            'Canal_pedidoEstado' => 'A',
            'Canal_pedidoUsuarioEliminacion' => null,
            'Canal_pedidoHostEliminacion' => null,
            'Canal_pedidoFechaEliminacion' => null,
        ], $auditData));
    }

    public function getNextId(): string
    {
        $last = DB::table('Canal_pedido')
            ->select('Canal_pedidoId')
            ->where('Canal_pedidoId', 'like', 'CNL-%')
            ->orderByRaw('LEN(Canal_pedidoId) DESC, Canal_pedidoId DESC')
            ->first();

        if (!$last) {
            return 'CNL-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->Canal_pedidoId);
        $nextNumber = ((int) $numberPart) + 1;

        return 'CNL-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
