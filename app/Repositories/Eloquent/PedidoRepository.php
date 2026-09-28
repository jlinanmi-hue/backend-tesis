<?php

namespace App\Repositories\Eloquent;

use App\Models\Pedido;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Support\AuditHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PedidoRepository implements PedidoRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Pedido::query()
            ->with(['cliente', 'canalPedido', 'detalles.producto', 'detalles.unidadMedida']);

        if (empty($filters['include_deleted'])) {
            $query->where('PedidoEliminado', 'N');
        }

        // Búsqueda por texto (Código PED, Cliente Nombre, RUC/DNI o teléfono)
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('PedidoId', 'LIKE', "%{$search}%")
                  ->orWhere('PedidoAcuerdo_Comercial', 'LIKE', "%{$search}%")
                  ->orWhereHas('cliente', function ($cQuery) use ($search) {
                      $cQuery->where('ClienteNombre', 'LIKE', "%{$search}%")
                             ->orWhere('ClienteDniRuc', 'LIKE', "%{$search}%")
                             ->orWhere('ClienteTelefono', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filtro por Estado (P, C, A)
        if (!empty($filters['estado'])) {
            $query->where('PedidoEstado_pedido', strtoupper($filters['estado']));
        }

        // Filtro por Cliente
        if (!empty($filters['cliente_id'])) {
            $query->where('Pedido_ClienteId', $filters['cliente_id']);
        }

        // Filtro por Canal de Pedido
        if (!empty($filters['canal_id'])) {
            $query->where('Pedido_canal_pedidoId', $filters['canal_id']);
        }

        // Filtro por Origen IA ('S' = Valencia AI, 'N' = Manual)
        if (isset($filters['origen_ia']) && $filters['origen_ia'] !== '') {
            $val = in_array(strtoupper((string) $filters['origen_ia']), ['S', '1', 'TRUE', 'IA']) ? 'S' : 'N';
            $query->where('PedidoOrigenIA', $val);
        }

        // Filtro por rango de fechas
        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('PedidoFecha_pedido', '>=', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('PedidoFecha_pedido', '<=', $filters['fecha_hasta']);
        }

        $query->orderBy('PedidoFechaCreacion', 'desc')
              ->orderBy('PedidoId', 'desc');

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?Pedido
    {
        $query = Pedido::query()
            ->with(['cliente', 'canalPedido', 'detalles.producto', 'detalles.unidadMedida'])
            ->where('PedidoId', $id);

        if (!$includeDeleted) {
            $query->where('PedidoEliminado', 'N');
        }

        return $query->first();
    }

    public function create(array $data): Pedido
    {
        if (empty($data['PedidoId'])) {
            $data['PedidoId'] = $this->generateNextId();
        }

        $audit = AuditHelper::getCreationAudit('Pedido');
        $data = array_merge($data, $audit);
        $data['PedidoEliminado'] = 'N';

        return Pedido::create($data);
    }

    public function update(string $id, array $data): Pedido
    {
        $pedido = $this->findById($id);

        if (!$pedido) {
            throw new \Exception("Pedido con ID '{$id}' no encontrado.");
        }

        $audit = AuditHelper::getModificationAudit('Pedido');
        $data = array_merge($data, $audit);

        $pedido->update($data);
        return $pedido->fresh(['cliente', 'canalPedido', 'detalles.producto', 'detalles.unidadMedida']);
    }

    public function delete(string $id): bool
    {
        $pedido = $this->findById($id);

        if (!$pedido) {
            return false;
        }

        $audit = AuditHelper::getDeletionAudit('Pedido');
        return $pedido->update($audit);
    }

    public function restore(string $id): bool
    {
        $pedido = $this->findById($id, true);

        if (!$pedido) {
            return false;
        }

        $audit = AuditHelper::getModificationAudit('Pedido');
        return $pedido->update(array_merge($audit, [
            'PedidoEliminado' => 'N',
        ]));
    }

    public function generateNextId(): string
    {
        $lastRecord = DB::table('Pedido')
            ->select('PedidoId')
            ->where('PedidoId', 'LIKE', 'PED-%')
            ->orderBy('PedidoId', 'desc')
            ->first();

        if (!$lastRecord) {
            return 'PED-00001';
        }

        $lastNumber = (int) substr($lastRecord->PedidoId, 4);
        $nextNumber = $lastNumber + 1;

        return sprintf('PED-%05d', $nextNumber);
    }

    public function getByCliente(string $clienteId, int $perPage = 15): LengthAwarePaginator
    {
        return Pedido::query()
            ->with(['canalPedido', 'detalles.producto'])
            ->where('Pedido_ClienteId', $clienteId)
            ->where('PedidoEliminado', 'N')
            ->orderBy('PedidoFechaCreacion', 'desc')
            ->paginate($perPage);
    }

    public function getPendingExpired(int $timeoutHours): Collection
    {
        $threshold = now()->subHours($timeoutHours);

        return Pedido::query()
            ->with(['detalles.producto', 'cliente'])
            ->where('PedidoEstado_pedido', 'P')
            ->where('PedidoEliminado', 'N')
            ->where('PedidoFechaCreacion', '<=', $threshold)
            ->get();
    }

    public function getPendingExpiringSoon(int $timeoutHours, int $windowHours = 2): Collection
    {
        // Órdenes pendientes a las que les falta menos de $windowHours para expirar
        // Es decir, su tiempo transcurrido está entre ($timeoutHours - $windowHours) y $timeoutHours
        $minAge = now()->subHours($timeoutHours - $windowHours);
        $maxAge = now()->subHours($timeoutHours);

        return Pedido::query()
            ->with(['cliente', 'canalPedido', 'detalles.producto'])
            ->where('PedidoEstado_pedido', 'P')
            ->where('PedidoEliminado', 'N')
            ->where('PedidoFechaCreacion', '<=', $minAge)
            ->where('PedidoFechaCreacion', '>', $maxAge)
            ->get();
    }

    public function getStats(array $filters = []): array
    {
        $baseQuery = DB::table('Pedido')->where('PedidoEliminado', 'N');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $baseQuery->where(function ($q) use ($search) {
                $q->where('PedidoId', 'LIKE', "%{$search}%")
                  ->orWhereExists(function ($sub) use ($search) {
                      $sub->select(DB::raw(1))
                          ->from('Cliente')
                          ->whereColumn('Cliente.ClienteId', 'Pedido.Pedido_ClienteId')
                          ->where('ClienteEliminado', 'N')
                          ->where(function ($cQuery) use ($search) {
                              $cQuery->where('ClienteNombre', 'LIKE', "%{$search}%")
                                     ->orWhere('ClienteDniRuc', 'LIKE', "%{$search}%")
                                     ->orWhere('ClienteTelefono', 'LIKE', "%{$search}%");
                          });
                  });
            });
        }

        if (!empty($filters['canal_id'])) {
            $baseQuery->where('Pedido_canal_pedidoId', $filters['canal_id']);
        }

        if (!empty($filters['fecha_desde'])) {
            $baseQuery->whereDate('PedidoFecha_pedido', '>=', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $baseQuery->whereDate('PedidoFecha_pedido', '<=', $filters['fecha_hasta']);
        }

        if (!empty($filters['estado'])) {
            $baseQuery->where('PedidoEstado_pedido', strtoupper($filters['estado']));
        }

        $totalOrdenes = (clone $baseQuery)->count();
        $totalVentas = (float) ((clone $baseQuery)->where('PedidoEstado_pedido', 'C')->sum('PedidoTotal') ?? 0);
        $totalPendientesMonto = (float) ((clone $baseQuery)->where('PedidoEstado_pedido', 'P')->sum('PedidoTotal') ?? 0);

        $pendientes = (clone $baseQuery)->where('PedidoEstado_pedido', 'P')->count();
        $completadas = (clone $baseQuery)->where('PedidoEstado_pedido', 'C')->count();
        $canceladas = (clone $baseQuery)->where('PedidoEstado_pedido', 'A')->count();

        // Órdenes hoy
        $hoyInicio = now()->startOfDay();
        $ordenesHoy = (clone $baseQuery)->where('PedidoFechaCreacion', '>=', $hoyInicio)->count();
        $ventasHoy = (float) ((clone $baseQuery)->where('PedidoFechaCreacion', '>=', $hoyInicio)->where('PedidoEstado_pedido', 'C')->sum('PedidoTotal') ?? 0);

        // Desglose por canal
        $porCanal = DB::table('Pedido')
            ->leftJoin('Canal_pedido', 'Pedido.Pedido_canal_pedidoId', '=', 'Canal_pedido.Canal_pedidoId')
            ->where('Pedido.PedidoEliminado', 'N');

        if (!empty($filters['estado'])) {
            $porCanal->where('Pedido.PedidoEstado_pedido', strtoupper($filters['estado']));
        }

        $desgloseCanal = $porCanal->select(
                'Canal_pedido.Canal_pedidoId as canal_id',
                'Canal_pedido.Canal_pedidoDescripcion as canal_nombre',
                DB::raw('COUNT(*) as total_ordenes'),
                DB::raw('SUM(Pedido.PedidoTotal) as total_monto')
            )
            ->groupBy('Canal_pedido.Canal_pedidoId', 'Canal_pedido.Canal_pedidoDescripcion')
            ->get();

        return [
            'total_ordenes' => $totalOrdenes,
            'total_ventas_completadas' => round($totalVentas, 2),
            'total_monto_pendiente' => round($totalPendientesMonto, 2),
            'conteo_estados' => [
                'pendientes' => $pendientes,
                'completadas' => $completadas,
                'canceladas' => $canceladas,
            ],
            'hoy' => [
                'total_ordenes' => $ordenesHoy,
                'total_ventas' => round($ventasHoy, 2),
            ],
            'por_canal' => $desgloseCanal,
        ];
    }

    public function getDaily(string $date, int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Pedido::query()
            ->with(['cliente', 'canalPedido', 'detalles.producto'])
            ->where('PedidoEliminado', 'N')
            ->whereDate('PedidoFecha_pedido', $date)
            ->orderBy('PedidoFechaCreacion', 'desc');

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }
}
