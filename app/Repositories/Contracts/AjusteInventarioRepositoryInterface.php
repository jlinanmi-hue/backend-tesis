<?php

namespace App\Repositories\Contracts;

use App\Models\AjusteInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AjusteInventarioRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(string $id, bool $includeDeleted = false): ?AjusteInventario;

    public function create(array $data): AjusteInventario;

    public function deleteLogico(string $id, array $auditData): bool;

    public function restore(string $id, array $auditData): bool;

    public function getNextId(): string;

    public function getResumen(): array;
}
