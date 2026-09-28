<?php

namespace App\Repositories\Eloquent;

use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Support\AuditHelper;
use Illuminate\Support\Facades\DB;

class UsuarioRepository implements UsuarioRepositoryInterface
{
    public function findById(string $id, bool $includeDeleted = false): ?Usuario
    {
        $query = Usuario::with(['rolUser', 'empleado'])
            ->where('UsuarioId', $id);

        if (!$includeDeleted) {
            $query->where('UsuarioEliminado', 'N');
        }

        return $query->first();
    }

    public function findByEmpleadoId(string $empleadoId, bool $includeDeleted = false): ?Usuario
    {
        $query = Usuario::with(['rolUser'])
            ->where('Usuario_EmpleadoId', $empleadoId);

        if (!$includeDeleted) {
            $query->where('UsuarioEliminado', 'N');
        }

        return $query->first();
    }

    public function findByUserName(string $username): ?Usuario
    {
        return Usuario::where('UsuarioUserName', $username)
            ->where('UsuarioEliminado', 'N')
            ->first();
    }

    public function create(array $data): Usuario
    {
        return Usuario::create($data);
    }

    public function update(string $id, array $data): Usuario
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->update($data);
        return $usuario->fresh(['rolUser', 'empleado']);
    }

    public function deleteLogico(string $id, array $auditData = []): bool
    {
        $usuario = Usuario::where('UsuarioId', $id)->first();
        if (!$usuario) {
            return false;
        }

        $payload = array_merge([
            'UsuarioEliminado' => 'S',
        ], empty($auditData) ? AuditHelper::getDeletionAudit('Usuario') : $auditData);

        return (bool) $usuario->update($payload);
    }

    public function restore(string $id, array $auditData = []): bool
    {
        $usuario = Usuario::where('UsuarioId', $id)->first();
        if (!$usuario) {
            return false;
        }

        $payload = array_merge([
            'UsuarioEliminado' => 'N',
            'UsuarioUsuarioEliminacion' => null,
            'UsuarioHostEliminacion' => null,
            'UsuarioFechaEliminacion' => null,
        ], empty($auditData) ? AuditHelper::getModificationAudit('Usuario') : $auditData);

        return (bool) $usuario->update($payload);
    }

    public function getNextId(): string
    {
        $last = DB::table('Usuario')
            ->select('UsuarioId')
            ->where('UsuarioId', 'like', 'USR-%')
            ->orderBy('UsuarioId', 'desc')
            ->first();

        if (!$last) {
            return 'USR-00001';
        }

        $number = (int) substr($last->UsuarioId, 4);
        $nextNumber = $number + 1;

        return 'USR-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
