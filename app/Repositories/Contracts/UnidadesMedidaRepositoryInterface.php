<?php

namespace App\Repositories\Contracts;

use App\Models\UnidadesMedida;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UnidadesMedidaRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(string $id, bool $includeDeleted = false): ?UnidadesMedida;

    public function findByDescripcion(string $descripcion): ?UnidadesMedida;

    public function create(array $data): UnidadesMedida;

    public function update(string $id, array $data): UnidadesMedida;

    public function deleteLogico(string $id, array $auditData): bool;

    public function restore(string $id, array $auditData): bool;

    public function getNextId(): string;
}
