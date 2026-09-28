<?php

namespace App\Repositories\Contracts;

use App\Models\Proveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProveedorRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(string $id, bool $includeDeleted = false): ?Proveedor;

    public function findByRuc(string $ruc): ?Proveedor;

    public function create(array $data): Proveedor;

    public function update(string $id, array $data): Proveedor;

    public function deleteLogico(string $id, array $auditData): bool;

    public function restore(string $id, array $auditData): bool;

    public function buscarRapido(string $term, int $limit = 10): Collection;

    public function getNextId(): string;
}
