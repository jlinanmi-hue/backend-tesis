<?php

namespace App\Repositories\Eloquent;

use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClienteRepository implements ClienteRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Cliente::query();

        // 1. Filtrado por Pestañas (Activos, Recurrentes, Más Compras, Inactivos)
        if (!empty($filters['tab'])) {
            $tab = strtolower($filters['tab']);
            if ($tab === 'activos') {
                $query->activos();
            } elseif ($tab === 'recurrentes') {
                $minCompras = (int) ($filters['min_compras'] ?? 3);
                $dias = (int) ($filters['dias_recientes'] ?? 90);
                $query->recurrentes($minCompras, $dias);
            } elseif ($tab === 'mas_compras') {
                $query->activos();
            } elseif ($tab === 'inactivos') {
                $dias = (int) ($filters['dias_inactividad'] ?? 90);
                $query->inactivos($dias);
            }
        } else {
            if (!empty($filters['eliminados']) && filter_var($filters['eliminados'], FILTER_VALIDATE_BOOLEAN)) {
                $query->where('ClienteEliminado', 'S');
            } else {
                $query->where('ClienteEliminado', 'N');
            }

            if (!empty($filters['estado'])) {
                $query->where('ClienteEstado', $filters['estado']);
            }
        }

        // 2. Búsqueda por texto (Nombre, RUC, Teléfono, Dirección o ID)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ClienteNombre', 'like', "%{$search}%")
                  ->orWhere('ClienteRuc', 'like', "%{$search}%")
                  ->orWhere('ClienteId', 'like', "%{$search}%")
                  ->orWhere('ClienteDireccion', 'like', "%{$search}%")
                  ->orWhere('ClienteNumero', 'like', "%{$search}%");
            });
        }

        // 3. Ordenamiento
        if (!empty($filters['tab']) && strtolower($filters['tab']) === 'mas_compras') {
            $query->orderBy('ClienteComprasCount', 'desc');
        } elseif (!empty($filters['tab']) && strtolower($filters['tab']) === 'inactivos') {
            $query->orderBy('ClienteEstado', 'desc')
                  ->orderBy('ClienteFechaCreacion', 'desc');
        } elseif (!empty($filters['orden']) && $filters['orden'] === 'compras') {
            $query->orderBy('ClienteComprasCount', 'desc');
        } else {
            $query->orderBy('ClienteFechaCreacion', 'desc');
        }

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?Cliente
    {
        $query = Cliente::with(['pedidos' => function ($q) {
            $q->where('PedidoEliminado', 'N')->orderBy('PedidoFecha_pedido', 'desc');
        }])->where('ClienteId', $id);

        if (!$includeDeleted) {
            $query->where('ClienteEliminado', 'N');
        }

        return $query->first();
    }

    public function findByRuc(string $ruc, bool $includeDeleted = false): ?Cliente
    {
        $query = Cliente::where('ClienteRuc', $ruc);

        if (!$includeDeleted) {
            $query->where('ClienteEliminado', 'N');
        }

        return $query->first();
    }

    public function findByNombre(string $nombre): ?Cliente
    {
        return Cliente::where('ClienteNombre', $nombre)
            ->where('ClienteEliminado', 'N')
            ->first();
    }

    public function getInactivos(int $diasInactividad = 90, int $perPage = 15): LengthAwarePaginator
    {
        return Cliente::inactivos($diasInactividad)
            ->orderBy('ClienteUltimaCompra', 'asc')
            ->paginate($perPage);
    }

    public function buscarRapido(string $term, int $limit = 10): Collection
    {
        $term = trim($term);
        if (empty($term)) {
            return new Collection();
        }

        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
            'ñ' => 'n', 'Ñ' => 'n'
        ];
        $norm = trim(preg_replace('/\s+/', ' ', strtr(mb_strtolower($term, 'UTF-8'), $reemplazos)));
        $words = array_filter(explode(' ', $norm), fn($w) => strlen($w) >= 2);

        $query = Cliente::activos();

        if (count($words) > 1) {
            $query->where(function ($q) use ($words, $term) {
                $q->where('ClienteRuc', 'like', "{$term}%")
                  ->orWhere('ClienteNumero', 'like', "%{$term}%")
                  ->orWhere('ClienteId', 'like', "%{$term}%")
                  ->orWhere(function ($sub) use ($words) {
                      foreach ($words as $w) {
                          $sub->whereRaw("ClienteNombre COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$w}%"]);
                      }
                  });
            });
        } else {
            $query->where(function ($q) use ($term, $norm) {
                $q->where('ClienteRuc', 'like', "{$term}%")
                  ->orWhere('ClienteNumero', 'like', "%{$term}%")
                  ->orWhereRaw("ClienteNombre COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$term}%"])
                  ->orWhereRaw("ClienteNombre COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$norm}%"])
                  ->orWhere('ClienteId', 'like', "%{$term}%");
            });
        }

        $results = $query->orderBy('ClienteComprasCount', 'desc')->take($limit)->get();

        if ($results->isEmpty() && strlen($norm) >= 3) {
            $all = Cliente::activos()->get();
            $scored = [];
            $stopWords = ['al', 'el', 'la', 'los', 'las', 'de', 'del', 'en', 'por', 'con', 'sin', 'un', 'una', 'y', 'o', '/'];
            foreach ($all as $c) {
                // Si el cliente es el genérico CLI-00011 y el término buscado no es explícitamente genérico, no considerarlo en fuzzy match
                if ($c->ClienteId === 'CLI-00011' && !in_array($norm, ['general', 'mostrador', 'publico', 'consumidor', 'cf', 'ocasional', 'anonimo'], true)) {
                    continue;
                }

                $cNorm = trim(preg_replace('/\s+/', ' ', strtr(mb_strtolower($c->ClienteNombre ?? '', 'UTF-8'), $reemplazos)));
                similar_text($norm, $cNorm, $pct);
                $maxWordPct = 0;
                foreach (explode(' ', $cNorm) as $cw) {
                    $cw = trim($cw);
                    if (strlen($cw) < 3 || in_array($cw, $stopWords, true)) {
                        continue;
                    }
                    similar_text($norm, $cw, $wpct);
                    if ($wpct > $maxWordPct) $maxWordPct = $wpct;
                }
                $score = max($pct, $maxWordPct);
                if ($score >= 70) {
                    $scored[] = ['cliente' => $c, 'score' => $score];
                }
            }
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $results = new Collection(array_slice(array_column($scored, 'cliente'), 0, $limit));
        }

        return $results;
    }

    public function create(array $data): Cliente
    {
        return Cliente::create($data);
    }

    public function update(string $id, array $data): Cliente
    {
        $cliente = $this->findById($id, true);
        $cliente->update($data);

        return $cliente->fresh();
    }

    public function deleteLogico(string $id, array $auditData): bool
    {
        $cliente = $this->findById($id);
        if (!$cliente) {
            return false;
        }

        return (bool) $cliente->update(array_merge([
            'ClienteEliminado' => 'S',
            'ClienteEstado' => 'I',
        ], $auditData));
    }

    public function restore(string $id, array $auditData): bool
    {
        $cliente = $this->findById($id, true);
        if (!$cliente) {
            return false;
        }

        return (bool) $cliente->update(array_merge([
            'ClienteEliminado' => 'N',
            'ClienteEstado' => 'A',
            'ClienteUsuarioEliminacion' => null,
            'ClienteHostEliminacion' => null,
            'ClienteFechaEliminacion' => null,
        ], $auditData));
    }

    public function sincronizarContadores(?string $clienteId = null): void
    {
        $query = Cliente::query();
        if ($clienteId) {
            $query->where('ClienteId', $clienteId);
        }

        $clientes = $query->get();
        foreach ($clientes as $c) {
            $pedidosActivos = DB::table('Pedido')
                ->where('Pedido_ClienteId', $c->ClienteId)
                ->where('PedidoEliminado', 'N');

            $count = $pedidosActivos->count();
            $totalMonto = (float) ($pedidosActivos->sum('PedidoTotal') ?? 0);
            $ultimaFecha = $pedidosActivos->max('PedidoFecha_pedido');

            $c->update([
                'ClienteComprasCount' => $count,
                'ClienteTotalMonto' => $totalMonto,
                'ClienteUltimaCompra' => $ultimaFecha,
            ]);
        }
    }

    public function getNextId(): string
    {
        $last = DB::table('Cliente')
            ->select('ClienteId')
            ->where('ClienteId', 'like', 'CLI-%')
            ->orderByRaw('LEN(ClienteId) DESC, ClienteId DESC')
            ->first();

        if (!$last) {
            return 'CLI-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->ClienteId);
        $nextNumber = ((int) $numberPart) + 1;

        return 'CLI-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}

