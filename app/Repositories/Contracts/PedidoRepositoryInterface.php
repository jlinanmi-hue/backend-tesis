<?php

namespace App\Repositories\Contracts;

use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PedidoRepositoryInterface
{
    /**
     * Obtener listado de pedidos con filtros y paginación.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    /**
     * Buscar un pedido por ID con sus relaciones.
     */
    public function findById(string $id, bool $includeDeleted = false): ?Pedido;

    /**
     * Crear encabezado de pedido con auditoría.
     */
    public function create(array $data): Pedido;

    /**
     * Actualizar encabezado de pedido.
     */
    public function update(string $id, array $data): Pedido;

    /**
     * Eliminación lógica de pedido.
     */
    public function delete(string $id): bool;

    /**
     * Restaurar un pedido eliminado lógicamente.
     */
    public function restore(string $id): bool;

    /**
     * Generar el siguiente código correlativo (ej: PED-00001).
     */
    public function generateNextId(): string;

    /**
     * Obtener pedidos de un cliente específico.
     */
    public function getByCliente(string $clienteId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Obtener pedidos pendientes con más de X horas de antigüedad.
     */
    public function getPendingExpired(int $timeoutHours): Collection;

    /**
     * Obtener pedidos pendientes próximos a expirar (entre $timeoutHours - $windowHours y $timeoutHours).
     */
    public function getPendingExpiringSoon(int $timeoutHours, int $windowHours = 2): Collection;

    /**
     * Obtener métricas y KPIs de pedidos con filtros opcionales.
     */
    public function getStats(array $filters = []): array;

    /**
     * Obtener pedidos de una fecha determinada.
     */
    public function getDaily(string $date, int $perPage = 15): LengthAwarePaginator|Collection;
}
