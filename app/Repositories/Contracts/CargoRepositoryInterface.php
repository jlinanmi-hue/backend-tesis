<?php

namespace App\Repositories\Contracts;

use App\Models\Cargo;
use Illuminate\Database\Eloquent\Collection;

interface CargoRepositoryInterface
{
    /**
     * Get all active cargos.
     */
    public function getAll(): Collection;

    /**
     * Find a cargo by ID.
     */
    public function findById(string $id): ?Cargo;
}
