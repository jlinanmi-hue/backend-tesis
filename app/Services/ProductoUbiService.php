<?php

namespace App\Services;

use App\Models\ProductoUbi;
use App\Repositories\Contracts\ProductoUbiRepositoryInterface;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductoUbiService
{
    public function __construct(
        protected ProductoUbiRepositoryInterface $ubiRepository
    ) {}

    public function listarUbicaciones(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->ubiRepository->getAll($filters, $perPage);
    }

    public function obtenerUbicacionPorId(string $id, bool $includeDeleted = false): ProductoUbi
    {
        $ubi = $this->ubiRepository->findById($id, $includeDeleted);

        if (!$ubi) {
            throw new Exception("Ubicación de producto con ID '{$id}' no encontrada.");
        }

        return $ubi;
    }

    public function crearUbicacion(array $datos): ProductoUbi
    {
        return DB::transaction(function () use ($datos) {
            $ubiId = $this->ubiRepository->getNextId();
            $audit = AuditHelper::getCreationAudit('producto_ubi_');

            $payload = array_merge($datos, [
                'producto_ubi_id' => $ubiId,
                'producto_ubi_estado' => $datos['producto_ubi_estado'] ?? 'A',
                'producto_ubi_Eliminado' => 'N',
            ], $audit);

            return $this->ubiRepository->create($payload);
        });
    }

    public function actualizarUbicacion(string $id, array $datos): ProductoUbi
    {
        return DB::transaction(function () use ($id, $datos) {
            $this->obtenerUbicacionPorId($id);

            $audit = AuditHelper::getModificationAudit('producto_ubi_');
            $payload = array_merge($datos, $audit);

            return $this->ubiRepository->update($id, $payload);
        });
    }

    public function cambiarEstadoUbicacion(string $id, string $nuevoEstado): ProductoUbi
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $this->obtenerUbicacionPorId($id);

            if (!in_array($nuevoEstado, ['A', 'I'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'El estado debe ser A (Activo) o I (Inactivo).',
                ]);
            }

            $audit = AuditHelper::getModificationAudit('producto_ubi_');
            $payload = array_merge([
                'producto_ubi_estado' => $nuevoEstado,
            ], $audit);

            return $this->ubiRepository->update($id, $payload);
        });
    }

    public function eliminarUbicacion(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $ubi = $this->obtenerUbicacionPorId($id);

            // Validar que no tenga productos activos asociados
            $productosActivos = $ubi->productos()->where('ProductoEliminado', 'N')->count();
            if ($productosActivos > 0) {
                throw ValidationException::withMessages([
                    'ubicacion' => "No se puede eliminar la ubicación porque contiene {$productosActivos} producto(s) asignado(s). Reasigna los productos primero.",
                ]);
            }

            $audit = AuditHelper::getDeletionAudit('producto_ubi_');

            return $this->ubiRepository->deleteLogico($id, $audit);
        });
    }

    public function restaurarUbicacion(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerUbicacionPorId($id, true);

            $audit = AuditHelper::getModificationAudit('producto_ubi_');

            return $this->ubiRepository->restore($id, $audit);
        });
    }
}
