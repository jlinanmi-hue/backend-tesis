<?php

namespace App\Repositories\Eloquent;

use App\Models\Empleado;
use App\Repositories\Contracts\EmpleadoRepositoryInterface;
use App\Support\AuditHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EmpleadoRepository implements EmpleadoRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Empleado::with(['cargo', 'usuarios.rolUser']);

        if (!empty($filters['eliminados']) && ($filters['eliminados'] === 'true' || $filters['eliminados'] === '1' || $filters['eliminados'] === 1 || $filters['eliminados'] === true)) {
            $query->where('EmpleadoEliminado', 'S');
        } elseif (!empty($filters['todos']) && ($filters['todos'] === 'true' || $filters['todos'] === '1' || $filters['todos'] === 1 || $filters['todos'] === true)) {
            // No se filtra por EmpleadoEliminado para traer todos
        } else {
            $query->where('EmpleadoEliminado', 'N');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('EmpleadoNombres', 'like', "%{$search}%")
                  ->orWhere('EmpleadoApellidos', 'like', "%{$search}%")
                  ->orWhere('EmpleadoDni', 'like', "%{$search}%")
                  ->orWhere('EmpleadoCorreo', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['cargoId'])) {
            $query->where('Empleado_cargoId', $filters['cargoId']);
        }

        if (!empty($filters['estado'])) {
            $query->where('EmpleadoEstado', $filters['estado']);
        }

        $query->orderBy('EmpleadoFechaCreacion', 'desc');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    public function findById(string $id, bool $includeDeleted = false): ?Empleado
    {
        $query = Empleado::with(['cargo', 'usuarios.rolUser'])
            ->where('EmpleadoId', $id);

        if (!$includeDeleted) {
            $query->where('EmpleadoEliminado', 'N');
        }

        return $query->first();
    }

    public function findByDni(string $dni): ?Empleado
    {
        return Empleado::where('EmpleadoDni', $dni)
            ->where('EmpleadoEliminado', 'N')
            ->first();
    }

    public function findByEmail(string $correo): ?Empleado
    {
        return Empleado::where('EmpleadoCorreo', $correo)
            ->where('EmpleadoEliminado', 'N')
            ->first();
    }

    public function create(array $data): Empleado
    {
        return Empleado::create($data);
    }

    public function update(string $id, array $data): Empleado
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->update($data);
        return $empleado->fresh(['cargo', 'usuarios.rolUser']);
    }

    public function deleteLogico(string $id, array $auditData = []): bool
    {
        $empleado = Empleado::where('EmpleadoId', $id)->first();
        if (!$empleado) {
            return false;
        }

        $payload = array_merge([
            'EmpleadoEliminado' => 'S',
            'EmpleadoEstado' => 'I',
        ], empty($auditData) ? AuditHelper::getDeletionAudit('Empleado') : $auditData);

        return (bool) $empleado->update($payload);
    }

    public function restore(string $id, array $auditData = []): bool
    {
        $empleado = Empleado::where('EmpleadoId', $id)->first();
        if (!$empleado) {
            return false;
        }

        $payload = array_merge([
            'EmpleadoEliminado' => 'N',
            'EmpleadoEstado' => 'A',
            'EmpleadoUsuarioEliminacion' => null,
            'EmpleadoHostEliminacion' => null,
            'EmpleadoFechaEliminacion' => null,
        ], empty($auditData) ? AuditHelper::getModificationAudit('Empleado') : $auditData);

        return (bool) $empleado->update($payload);
    }

    public function getNextId(): string
    {
        $last = DB::table('Empleado')
            ->select('EmpleadoId')
            ->where('EmpleadoId', 'like', 'EMP-%')
            ->orderBy('EmpleadoId', 'desc')
            ->first();

        if (!$last) {
            return 'EMP-00001';
        }

        $number = (int) substr($last->EmpleadoId, 4);
        $nextNumber = $number + 1;

        return 'EMP-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
