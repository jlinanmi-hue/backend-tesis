<?php

namespace App\Services;

use App\Models\UnidadesMedida;
use App\Repositories\Contracts\UnidadesMedidaRepositoryInterface;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnidadesMedidaService
{
    public function __construct(
        protected UnidadesMedidaRepositoryInterface $unidadesRepository
    ) {}

    public function listarUnidades(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->unidadesRepository->getAll($filters, $perPage);
    }

    public function obtenerUnidadPorId(string $id, bool $includeDeleted = false): UnidadesMedida
    {
        $unidad = $this->unidadesRepository->findById($id, $includeDeleted);

        if (!$unidad) {
            throw new Exception("Unidad de medida con ID '{$id}' no encontrada.");
        }

        return $unidad;
    }

    public function crearUnidad(array $datos): UnidadesMedida
    {
        return DB::transaction(function () use ($datos) {
            if ($this->unidadesRepository->findByDescripcion($datos['unidades_medidaDescripcionUnidades'])) {
                throw ValidationException::withMessages([
                    'unidades_medidaDescripcionUnidades' => 'La descripción de unidad de medida ingresada ya existe.',
                ]);
            }

            $unidadId = $this->unidadesRepository->getNextId();
            $audit = AuditHelper::getCreationAudit('unidades_medida');

            $payload = array_merge($datos, [
                'unidades_medidaId' => $unidadId,
                'unidades_medidaEstadoUnidades' => $datos['unidades_medidaEstadoUnidades'] ?? 'A',
                'unidades_medidaEliminado' => 'N',
            ], $audit);

            return $this->unidadesRepository->create($payload);
        });
    }

    public function actualizarUnidad(string $id, array $datos): UnidadesMedida
    {
        return DB::transaction(function () use ($id, $datos) {
            $unidad = $this->obtenerUnidadPorId($id);

            if (isset($datos['unidades_medidaDescripcionUnidades']) && $datos['unidades_medidaDescripcionUnidades'] !== $unidad->unidades_medidaDescripcionUnidades) {
                $existente = $this->unidadesRepository->findByDescripcion($datos['unidades_medidaDescripcionUnidades']);
                if ($existente && $existente->unidades_medidaId !== $id) {
                    throw ValidationException::withMessages([
                        'unidades_medidaDescripcionUnidades' => 'La descripción de unidad de medida ya pertenece a otro registro.',
                    ]);
                }
            }

            $audit = AuditHelper::getModificationAudit('unidades_medida');
            $payload = array_merge($datos, $audit);

            return $this->unidadesRepository->update($id, $payload);
        });
    }

    public function cambiarEstadoUnidad(string $id, string $nuevoEstado): UnidadesMedida
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $unidad = $this->obtenerUnidadPorId($id);

            if (!in_array($nuevoEstado, ['A', 'I'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'El estado debe ser A (Activo) o I (Inactivo).',
                ]);
            }

            $audit = AuditHelper::getModificationAudit('unidades_medida');
            $payload = array_merge([
                'unidades_medidaEstadoUnidades' => $nuevoEstado,
            ], $audit);

            return $this->unidadesRepository->update($id, $payload);
        });
    }

    public function eliminarUnidad(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerUnidadPorId($id);

            $audit = AuditHelper::getDeletionAudit('unidades_medida');

            return $this->unidadesRepository->deleteLogico($id, $audit);
        });
    }

    public function restaurarUnidad(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerUnidadPorId($id, true);

            $audit = AuditHelper::getModificationAudit('unidades_medida');

            return $this->unidadesRepository->restore($id, $audit);
        });
    }
}
