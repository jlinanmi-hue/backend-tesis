<?php

namespace App\Repositories\Contracts;

use App\Models\ProductoUbi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductoUbiRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(string $id, bool $includeDeleted = false): ?ProductoUbi;

    public function create(array $data): ProductoUbi;

    public function update(string $id, array $data): ProductoUbi;

    public function deleteLogico(string $id, array $auditData): bool;

    public function restore(string $id, array $auditData): bool;

    public function getNextId(): string;
}
