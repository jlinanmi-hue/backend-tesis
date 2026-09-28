<?php

namespace App\Services;

use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Support\AuditHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteService
{
    public function __construct(
        protected ClienteRepositoryInterface $clienteRepository
    ) {}

    public function listarClientes(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $result = $this->clienteRepository->getAll($filters, $perPage);

        if ($result instanceof LengthAwarePaginator) {
            $result->getCollection()->transform(fn($c) => $this->enriquecerCliente($c));
        } else {
            $result->transform(fn($c) => $this->enriquecerCliente($c));
        }

        return $result;
    }

    public function obtenerClientePorId(string $id, bool $includeDeleted = false): Cliente
    {
        $cliente = $this->clienteRepository->findById($id, $includeDeleted);

        if (!$cliente) {
            throw new Exception("Cliente con ID '{$id}' no encontrado.");
        }

        return $this->enriquecerCliente($cliente);
    }

    public function crearCliente(array $datos): Cliente
    {
        return DB::transaction(function () use ($datos) {
            // Validación de duplicado por RUC/DNI (exceptuando comodín sin RUC)
            // Validación de duplicado por RUC/DNI (exceptuando comodines sin RUC como 10000000000, 00000000000, 11110000)
            $ruc = trim($datos['ClienteRuc'] ?? '');
            if (!empty($ruc) && !in_array($ruc, ['10000000000', '00000000000', '11110000', '00000000'], true)) {
                $existenteRuc = $this->clienteRepository->findByRuc($ruc, true);
                if ($existenteRuc) {
                    if ($existenteRuc->ClienteEliminado === 'S') {
                        throw ValidationException::withMessages([
                            'ClienteRuc' => "El RUC/DNI '{$ruc}' pertenece al cliente '{$existenteRuc->ClienteNombre}' ({$existenteRuc->ClienteId}) que se encuentra eliminado. Puede restaurarlo en vez de crearlo de nuevo.",
                        ]);
                    }
                    throw ValidationException::withMessages([
                        'ClienteRuc' => "El número de RUC/DNI '{$ruc}' ya se encuentra registrado para '{$existenteRuc->ClienteNombre}'.",
                    ]);
                }
            }

            // Validación de duplicado por Nombre exacto
            if (!empty($datos['ClienteNombre'])) {
                $nombre = trim($datos['ClienteNombre']);
                $existenteNombre = $this->clienteRepository->findByNombre($nombre);
                if ($existenteNombre) {
                    throw ValidationException::withMessages([
                        'ClienteNombre' => "Ya existe un cliente registrado con la razón social o nombre '{$nombre}' ({$existenteNombre->ClienteId}).",
                    ]);
                }
            }

            $clienteId = $this->clienteRepository->getNextId();
            $audit = AuditHelper::getCreationAudit('Cliente');

            $payload = array_merge($datos, [
                'ClienteId' => $clienteId,
                'ClienteEstado' => $datos['ClienteEstado'] ?? 'A',
                'ClienteEliminado' => 'N',
                'ClienteComprasCount' => 0,
                'ClienteTotalMonto' => 0.00,
                'ClienteUltimaCompra' => null,
            ], $audit);

            $cliente = $this->clienteRepository->create($payload);

            return $this->enriquecerCliente($cliente);
        });
    }

    public function actualizarCliente(string $id, array $datos): Cliente
    {
        return DB::transaction(function () use ($id, $datos) {
            $cliente = $this->clienteRepository->findById($id);
            if (!$cliente) {
                throw new Exception("Cliente con ID '{$id}' no encontrado.");
            }

            if (isset($datos['ClienteRuc']) && trim($datos['ClienteRuc']) !== $cliente->ClienteRuc) {
                $ruc = trim($datos['ClienteRuc']);
                if (!in_array($ruc, ['10000000000', '00000000000', '11110000', '00000000'], true)) {
                    $existente = $this->clienteRepository->findByRuc($ruc, true);
                    if ($existente && $existente->ClienteId !== $id) {
                        throw ValidationException::withMessages([
                            'ClienteRuc' => "El número de RUC/DNI '{$ruc}' ya pertenece a otro cliente registrado ({$existente->ClienteNombre}).",
                        ]);
                    }
                }
            }

            if (isset($datos['ClienteNombre']) && trim($datos['ClienteNombre']) !== $cliente->ClienteNombre) {
                $nombre = trim($datos['ClienteNombre']);
                $existenteNombre = $this->clienteRepository->findByNombre($nombre);
                if ($existenteNombre && $existenteNombre->ClienteId !== $id) {
                    throw ValidationException::withMessages([
                        'ClienteNombre' => "Ya existe otro cliente con el nombre '{$nombre}'.",
                    ]);
                }
            }

            $audit = AuditHelper::getModificationAudit('Cliente');
            $payload = array_merge($datos, $audit);

            $actualizado = $this->clienteRepository->update($id, $payload);

            return $this->enriquecerCliente($actualizado);
        });
    }

    public function cambiarEstadoCliente(string $id, string $nuevoEstado): Cliente
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $cliente = $this->clienteRepository->findById($id);
            if (!$cliente) {
                throw new Exception("Cliente con ID '{$id}' no encontrado.");
            }

            if (!in_array($nuevoEstado, ['A', 'I'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'El estado debe ser A (Activo) o I (Inactivo).',
                ]);
            }

            $audit = AuditHelper::getModificationAudit('Cliente');
            $payload = array_merge([
                'ClienteEstado' => $nuevoEstado,
            ], $audit);

            $actualizado = $this->clienteRepository->update($id, $payload);

            return $this->enriquecerCliente($actualizado);
        });
    }

    public function eliminarCliente(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $cliente = $this->clienteRepository->findById($id);
            if (!$cliente) {
                throw new Exception("Cliente con ID '{$id}' no encontrado.");
            }

            $audit = AuditHelper::getDeletionAudit('Cliente');

            return $this->clienteRepository->deleteLogico($id, $audit);
        });
    }

    public function restaurarCliente(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $cliente = $this->clienteRepository->findById($id, true);
            if (!$cliente) {
                throw new Exception("Cliente con ID '{$id}' no encontrado.");
            }

            $audit = AuditHelper::getModificationAudit('Cliente');

            return $this->clienteRepository->restore($id, $audit);
        });
    }

    public function obtenerResumenDirectorio(): array
    {
        $totalActivos = Cliente::where('ClienteEliminado', 'N')->where('ClienteEstado', 'A')->count();
        $totalRecurrentes = Cliente::recurrentes(3, 90)->count();
        $totalInactivos = Cliente::inactivos(90)->count();
        $totalCompras = (int) (Cliente::where('ClienteEliminado', 'N')->sum('ClienteComprasCount') ?? 0);
        $totalVentasMonto = (float) (Cliente::where('ClienteEliminado', 'N')->sum('ClienteTotalMonto') ?? 0.0);

        return [
            'total_clientes_activos' => $totalActivos,
            'total_recurrentes' => $totalRecurrentes,
            'total_inactivos' => $totalInactivos,
            'total_compras_acumuladas' => $totalCompras,
            'total_monto_acumulado' => $totalVentasMonto,
        ];
    }

    public function obtenerClientesInactivos(int $dias = 90, int $perPage = 15): LengthAwarePaginator
    {
        $paginador = $this->clienteRepository->getInactivos($dias, $perPage);
        $paginador->getCollection()->transform(fn($c) => $this->enriquecerCliente($c));
        return $paginador;
    }

    public function buscarClientesRapido(string $term, int $limit = 10): Collection
    {
        $clientes = $this->clienteRepository->buscarRapido($term, $limit);
        return $clientes->map(fn($c) => $this->enriquecerCliente($c));
    }

    public function obtenerHistorialPedidos(string $clienteId, int $perPage = 10): LengthAwarePaginator
    {
        $cliente = $this->obtenerClientePorId($clienteId);

        return DB::table('Pedido')
            ->where('Pedido_ClienteId', $cliente->ClienteId)
            ->where('PedidoEliminado', 'N')
            ->orderBy('PedidoFecha_pedido', 'desc')
            ->paginate($perPage);
    }

    public function sincronizarContadorCompras(?string $clienteId = null): void
    {
        $this->clienteRepository->sincronizarContadores($clienteId);
    }

    public function enriquecerCliente(Cliente $cliente): Cliente
    {
        $compras = (int) ($cliente->ClienteComprasCount ?? 0);
        $ultimaCompra = $cliente->ClienteUltimaCompra ? Carbon::parse($cliente->ClienteUltimaCompra) : null;
        $diasSinComprar = $ultimaCompra ? (int) $ultimaCompra->diffInDays(now()) : null;

        // Categoría y badge
        $esRecurrente = ($compras >= 5) || ($compras >= 3 && $diasSinComprar !== null && $diasSinComprar <= 90);
        
        if ($cliente->ClienteEstado === 'I') {
            $categoria = 'Inactivo';
            $badgeColor = 'gray';
        } elseif ($esRecurrente) {
            $categoria = 'Recurrente';
            $badgeColor = 'green';
        } elseif ($compras > 0) {
            $categoria = 'Ocasional';
            $badgeColor = 'blue';
        } else {
            $categoria = 'Nuevo';
            $badgeColor = 'purple';
        }

        $cliente->setAttribute('es_recurrente', $esRecurrente);
        $cliente->setAttribute('categoria_cliente', $categoria);
        $cliente->setAttribute('badge_color', $badgeColor);
        $cliente->setAttribute('dias_sin_comprar', $diasSinComprar);
        $cliente->setAttribute('es_inactivo_alerta', ($cliente->ClienteEstado === 'A' && $compras > 0 && $diasSinComprar !== null && $diasSinComprar >= 90));

        return $cliente;
    }
}

