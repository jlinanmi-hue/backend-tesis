<?php

namespace App\Repositories\Eloquent;

use App\Models\CategoriaProducto;
use App\Repositories\Contracts\CategoriaProductoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoriaProductoRepository implements CategoriaProductoRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = CategoriaProducto::query();

        if (!empty($filters['eliminados']) && ($filters['eliminados'] === 'true' || $filters['eliminados'] === '1' || $filters['eliminados'] === 1 || $filters['eliminados'] === true)) {
            $query->where('Categoria_ProductoEliminado', 'S');
        } elseif (!empty($filters['todos']) && ($filters['todos'] === 'true' || $filters['todos'] === '1' || $filters['todos'] === 1 || $filters['todos'] === true)) {
            // No filtrar por Categoria_ProductoEliminado
        } else {
            $query->where('Categoria_ProductoEliminado', 'N');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('Categoria_ProductoDescripcion_categoria', 'like', "%{$search}%")
                  ->orWhere('Categoria_ProductoId', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['estado'])) {
            $query->where('Categoria_ProductoEstado', $filters['estado']);
        }

        $query->orderBy('Categoria_ProductoFechaCreacion', 'desc');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?CategoriaProducto
    {
        $query = CategoriaProducto::with('productos')->where('Categoria_ProductoId', $id);

        if (!$includeDeleted) {
            $query->where('Categoria_ProductoEliminado', 'N');
        }

        return $query->first();
    }

    public function findByDescripcion(string $descripcion): ?CategoriaProducto
    {
        return CategoriaProducto::where('Categoria_ProductoDescripcion_categoria', $descripcion)
            ->where('Categoria_ProductoEliminado', 'N')
            ->first();
    }

    public function create(array $data): CategoriaProducto
    {
        return CategoriaProducto::create($data);
    }

    public function update(string $id, array $data): CategoriaProducto
    {
        $categoria = $this->findById($id, true);
        $categoria->update($data);

        return $categoria->fresh();
    }

    public function deleteLogico(string $id, array $auditData): bool
    {
        $categoria = $this->findById($id);
        if (!$categoria) {
            return false;
        }

        return (bool) $categoria->update(array_merge([
            'Categoria_ProductoEliminado' => 'S',
            'Categoria_ProductoEstado' => 'I',
        ], $auditData));
    }

    public function restore(string $id, array $auditData): bool
    {
        $categoria = $this->findById($id, true);
        if (!$categoria) {
            return false;
        }

        return (bool) $categoria->update(array_merge([
            'Categoria_ProductoEliminado' => 'N',
            'Categoria_ProductoEstado' => 'A',
            'Categoria_ProductoUsuarioEliminacion' => null,
            'Categoria_ProductoHostEliminacion' => null,
            'Categoria_ProductoFechaEliminacion' => null,
        ], $auditData));
    }

    public function getNextId(): string
    {
        $last = DB::table('Categoria_Producto')
            ->select('Categoria_ProductoId')
            ->where('Categoria_ProductoId', 'like', 'CAT-%')
            ->orderByRaw('LEN(Categoria_ProductoId) DESC, Categoria_ProductoId DESC')
            ->first();

        if (!$last) {
            return 'CAT-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->Categoria_ProductoId);
        $nextNumber = ((int) $numberPart) + 1;

        return 'CAT-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
