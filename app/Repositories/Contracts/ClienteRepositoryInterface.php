<?php

namespace App\Repositories\Contracts;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClienteRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(string $id, bool $includeDeleted = false): ?Cliente;

    public function findByRuc(string $ruc): ?Cliente;

    public function create(array $data): Cliente;

    public function update(string $id, array $data): Cliente;

    public function deleteLogico(string $id, array $auditData): bool;

    public function restore(string $id, array $auditData): bool;

    public function findByNombre(string $nombre): ?Cliente;

    public function getInactivos(int $diasInactividad = 90, int $perPage = 15): LengthAwarePaginator;

    public function buscarRapido(string $term, int $limit = 10): Collection;

    public function sincronizarContadores(?string $clienteId = null): void;

    public function getNextId(): string;
}
