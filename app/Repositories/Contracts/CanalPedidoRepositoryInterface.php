<?php

namespace App\Repositories\Contracts;

use App\Models\CanalPedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CanalPedidoRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(string $id, bool $includeDeleted = false): ?CanalPedido;

    public function findByDescripcion(string $descripcion): ?CanalPedido;

    public function create(array $data): CanalPedido;

    public function update(string $id, array $data): CanalPedido;

    public function deleteLogico(string $id, array $auditData): bool;

    public function restore(string $id, array $auditData): bool;

    public function getNextId(): string;
}
