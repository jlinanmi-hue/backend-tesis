<?php

namespace App\Repositories\Eloquent;

use App\Models\Proveedor;
use App\Repositories\Contracts\ProveedorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProveedorRepository implements ProveedorRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Proveedor::with('productos');

        $isEliminados = !empty($filters['eliminados']) || (($filters['tab'] ?? '') === 'eliminados');

        if ($isEliminados) {
            $query->where('ProveedorEliminado', 'S');
        } else {
            $query->where('ProveedorEliminado', 'N');

            if (($filters['tab'] ?? '') === 'activos') {
                $query->where('ProveedorEstado', 'A');
            } elseif (($filters['tab'] ?? '') === 'inactivos') {
                $query->where('ProveedorEstado', 'I');
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ProveedorRazonSocial', 'like', "%{$search}%")
                  ->orWhere('ProveedorRuc', 'like', "%{$search}%")
                  ->orWhere('ProveedorTelefono', 'like', "%{$search}%")
                  ->orWhere('ProveedorId', 'like', "%{$search}%")
                  ->orWhere('ProveedorActividadEconomica', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['estado'])) {
            $query->where('ProveedorEstado', $filters['estado']);
        }

        $query->orderBy('ProveedorFechaCreacion', 'desc');

        if (!empty($filters['all']) || $perPage <= 0) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    public function findById(string $id, bool $includeDeleted = false): ?Proveedor
    {
        $query = Proveedor::with('productos')->where('ProveedorId', $id);

        if (!$includeDeleted) {
            $query->where('ProveedorEliminado', 'N');
        }

        return $query->first();
    }

    public function findByRuc(string $ruc): ?Proveedor
    {
        return Proveedor::where('ProveedorRuc', $ruc)
            ->where('ProveedorEliminado', 'N')
            ->first();
    }

    public function create(array $data): Proveedor
    {
        return Proveedor::create($data);
    }

    public function update(string $id, array $data): Proveedor
    {
        $proveedor = $this->findById($id, true);
        $proveedor->update($data);

        return $proveedor->fresh();
    }

    public function deleteLogico(string $id, array $auditData): bool
    {
        $proveedor = $this->findById($id);
        if (!$proveedor) {
            return false;
        }

        return (bool) $proveedor->update(array_merge([
            'ProveedorEliminado' => 'S',
            'ProveedorEstado' => 'I',
        ], $auditData));
    }

    public function restore(string $id, array $auditData): bool
    {
        $proveedor = $this->findById($id, true);
        if (!$proveedor) {
            return false;
        }

        return (bool) $proveedor->update(array_merge([
            'ProveedorEliminado' => 'N',
            'ProveedorEstado' => 'A',
            'ProveedorUsuarioEliminacion' => null,
            'ProveedorHostEliminacion' => null,
            'ProveedorFechaEliminacion' => null,
        ], $auditData));
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

        $query = Proveedor::query()
            ->where('ProveedorEliminado', 'N')
            ->where('ProveedorEstado', 'A');

        if (count($words) > 1) {
            $query->where(function ($q) use ($words, $term) {
                $q->where('ProveedorRuc', 'like', "{$term}%")
                  ->orWhere('ProveedorTelefono', 'like', "%{$term}%")
                  ->orWhere('ProveedorId', 'like', "%{$term}%")
                  ->orWhere(function ($sub) use ($words) {
                      foreach ($words as $w) {
                          $sub->whereRaw("ProveedorRazonSocial COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$w}%"]);
                      }
                  });
            });
        } else {
            $query->where(function ($q) use ($term, $norm) {
                $q->where('ProveedorRuc', 'like', "{$term}%")
                  ->orWhere('ProveedorTelefono', 'like', "%{$term}%")
                  ->orWhereRaw("ProveedorRazonSocial COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$term}%"])
                  ->orWhereRaw("ProveedorRazonSocial COLLATE Modern_Spanish_CI_AI LIKE ?", ["%{$norm}%"])
                  ->orWhere('ProveedorId', 'like', "%{$term}%");
            });
        }

        $results = $query->limit($limit)->get();

        if ($results->isEmpty() && strlen($norm) >= 3) {
            $all = Proveedor::where('ProveedorEliminado', 'N')->where('ProveedorEstado', 'A')->get();
            $scored = [];
            foreach ($all as $p) {
                $pNorm = trim(preg_replace('/\s+/', ' ', strtr(mb_strtolower($p->ProveedorRazonSocial ?? '', 'UTF-8'), $reemplazos)));
                similar_text($norm, $pNorm, $pct);
                $maxWordPct = 0;
                foreach (explode(' ', $pNorm) as $pw) {
                    similar_text($norm, $pw, $wpct);
                    if ($wpct > $maxWordPct) $maxWordPct = $wpct;
                }
                $score = max($pct, $maxWordPct);
                if ($score >= 55) {
                    $scored[] = ['proveedor' => $p, 'score' => $score];
                }
            }
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $results = new Collection(array_slice(array_column($scored, 'proveedor'), 0, $limit));
        }

        return $results;
    }

    public function getNextId(): string
    {
        $last = DB::table('Proveedor')
            ->select('ProveedorId')
            ->where('ProveedorId', 'like', 'PRV-%')
            ->orderByRaw('LEN(ProveedorId) DESC, ProveedorId DESC')
            ->first();

        if (!$last) {
            return 'PRV-00001';
        }

        $numberPart = preg_replace('/[^0-9]/', '', $last->ProveedorId);
        $nextNumber = ((int) $numberPart) + 1;

        return 'PRV-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
