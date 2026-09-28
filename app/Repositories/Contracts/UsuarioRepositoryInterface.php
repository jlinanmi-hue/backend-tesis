<?php

namespace App\Repositories\Contracts;

use App\Models\Usuario;

interface UsuarioRepositoryInterface
{
    /**
     * Find a usuario by ID.
     */
    public function findById(string $id, bool $includeDeleted = false): ?Usuario;

    /**
     * Find a usuario associated with an empleado.
     */
    public function findByEmpleadoId(string $empleadoId, bool $includeDeleted = false): ?Usuario;

    /**
     * Find a usuario by username/email.
     */
    public function findByUserName(string $username): ?Usuario;

    /**
     * Create a new usuario record.
     */
    public function create(array $data): Usuario;

    /**
     * Update an existing usuario record.
     */
    public function update(string $id, array $data): Usuario;

    /**
     * Soft delete (logical delete) a usuario.
     */
    public function deleteLogico(string $id, array $auditData = []): bool;

    /**
     * Restore a logically deleted usuario.
     */
    public function restore(string $id, array $auditData = []): bool;

    /**
     * Generate next sequential UsuarioId (e.g. USR-00001).
     */
    public function getNextId(): string;
}
