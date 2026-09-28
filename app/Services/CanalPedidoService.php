<?php

namespace App\Services;

use App\Models\CanalPedido;
use App\Repositories\Contracts\CanalPedidoRepositoryInterface;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CanalPedidoService
{
    public function __construct(
        protected CanalPedidoRepositoryInterface $canalRepository
    ) {}

    public function listarCanales(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->canalRepository->getAll($filters, $perPage);
    }

    public function obtenerCanalPorId(string $id, bool $includeDeleted = false): CanalPedido
    {
        $canal = $this->canalRepository->findById($id, $includeDeleted);

        if (!$canal) {
            throw new Exception("Canal de pedido con ID '{$id}' no encontrado.");
        }

        return $canal;
    }

    public function crearCanal(array $datos): CanalPedido
    {
        return DB::transaction(function () use ($datos) {
            if ($this->canalRepository->findByDescripcion($datos['Canal_pedidoDescripcion'])) {
                throw ValidationException::withMessages([
                    'Canal_pedidoDescripcion' => 'La descripción del canal de pedido ingresada ya existe.',
                ]);
            }

            $canalId = $this->canalRepository->getNextId();
            $audit = AuditHelper::getCreationAudit('Canal_pedido');

            $payload = array_merge($datos, [
                'Canal_pedidoId' => $canalId,
                'Canal_pedidoEstado' => $datos['Canal_pedidoEstado'] ?? 'A',
                'Canal_pedidoEliminado' => 'N',
            ], $audit);

            return $this->canalRepository->create($payload);
        });
    }

    public function actualizarCanal(string $id, array $datos): CanalPedido
    {
        return DB::transaction(function () use ($id, $datos) {
            $canal = $this->obtenerCanalPorId($id);

            if (isset($datos['Canal_pedidoDescripcion']) && $datos['Canal_pedidoDescripcion'] !== $canal->Canal_pedidoDescripcion) {
                $existente = $this->canalRepository->findByDescripcion($datos['Canal_pedidoDescripcion']);
                if ($existente && $existente->Canal_pedidoId !== $id) {
                    throw ValidationException::withMessages([
                        'Canal_pedidoDescripcion' => 'La descripción del canal de pedido ya pertenece a otro registro.',
                    ]);
                }
            }

            $audit = AuditHelper::getModificationAudit('Canal_pedido');
            $payload = array_merge($datos, $audit);

            return $this->canalRepository->update($id, $payload);
        });
    }

    public function cambiarEstadoCanal(string $id, string $nuevoEstado): CanalPedido
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $canal = $this->obtenerCanalPorId($id);

            if (!in_array($nuevoEstado, ['A', 'I'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'El estado debe ser A (Activo) o I (Inactivo).',
                ]);
            }

            $audit = AuditHelper::getModificationAudit('Canal_pedido');
            $payload = array_merge([
                'Canal_pedidoEstado' => $nuevoEstado,
            ], $audit);

            return $this->canalRepository->update($id, $payload);
        });
    }

    public function eliminarCanal(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerCanalPorId($id);

            $audit = AuditHelper::getDeletionAudit('Canal_pedido');

            return $this->canalRepository->deleteLogico($id, $audit);
        });
    }

    public function restaurarCanal(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerCanalPorId($id, true);

            $audit = AuditHelper::getModificationAudit('Canal_pedido');

            return $this->canalRepository->restore($id, $audit);
        });
    }
}
