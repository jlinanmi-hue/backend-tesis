<?php

namespace App\Repositories\Eloquent;

use App\Models\Cargo;
use App\Repositories\Contracts\CargoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CargoRepository implements CargoRepositoryInterface
{
    public function getAll(): Collection
    {
        return Cargo::where('cargoEliminado', 'N')
            ->where('cargoEstado', 'A')
            ->orderBy('cargoDescripcion', 'asc')
            ->get();
    }

    public function findById(string $id): ?Cargo
    {
        return Cargo::where('cargoId', $id)
            ->where('cargoEliminado', 'N')
            ->first();
    }
}
