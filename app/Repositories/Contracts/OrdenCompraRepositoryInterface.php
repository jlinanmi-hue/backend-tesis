<?php

namespace App\Repositories\Contracts;

use App\Models\OrdenCompra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface OrdenCompraRepositoryInterface
{
    /**
     * Get paginated or filtered list of purchase orders.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    /**
     * Find a purchase order by its ID with relations.
     */
    public function findById(string $id, bool $includeDeleted = false): ?OrdenCompra;

    /**
     * Create a purchase order header with audit data.
     */
    public function create(array $data): OrdenCompra;

    /**
     * Update a purchase order header.
     */
    public function update(string $id, array $data): OrdenCompra;

    /**
     * Soft delete a purchase order.
     */
    public function delete(string $id): bool;

    /**
     * Restore a soft-deleted purchase order.
     */
    public function restore(string $id): bool;

    /**
     * Generate the next sequential ID (e.g. OC-00001).
     */
    public function generateNextId(): string;
}

