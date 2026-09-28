<?php

namespace App\Repositories\Contracts;

use App\Models\CategoriaProducto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CategoriaProductoRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(string $id, bool $includeDeleted = false): ?CategoriaProducto;

    public function findByDescripcion(string $descripcion): ?CategoriaProducto;

    public function create(array $data): CategoriaProducto;

    public function update(string $id, array $data): CategoriaProducto;

    public function deleteLogico(string $id, array $auditData): bool;

    public function restore(string $id, array $auditData): bool;

    public function getNextId(): string;
}
