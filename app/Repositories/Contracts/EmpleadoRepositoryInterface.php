<?php

namespace App\Repositories\Contracts;

use App\Models\Empleado;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EmpleadoRepositoryInterface
{
    /**
     * Get paginated or filtered list of active empleados.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    /**
     * Find an empleado by ID.
     */
    public function findById(string $id, bool $includeDeleted = false): ?Empleado;

    /**
     * Find an empleado by DNI.
     */
    public function findByDni(string $dni): ?Empleado;

    /**
     * Find an empleado by Email.
     */
    public function findByEmail(string $correo): ?Empleado;

    /**
     * Create a new empleado record.
     */
    public function create(array $data): Empleado;

    /**
     * Update an existing empleado record.
     */
    public function update(string $id, array $data): Empleado;

    /**
     * Soft delete (logical delete) an empleado.
     */
    public function deleteLogico(string $id, array $auditData = []): bool;

    /**
     * Restore a logically deleted empleado.
     */
    public function restore(string $id, array $auditData = []): bool;

    /**
     * Generate next sequential EmpleadoId (e.g. EMP-00001).
     */
    public function getNextId(): string;
}
