<?php

namespace App\Support;

class AuditHelper
{
    /**
     * Get the current authenticated username or fallback.
     */
    public static function getCurrentUser(): string
    {
        if (auth()->check()) {
            return auth()->user()->UsuarioUserName ?? (string) auth()->id();
        }

        return request()->header('X-User', 'ADMIN');
    }

    /**
     * Get the current client IP/Host or fallback.
     */
    public static function getCurrentHost(): string
    {
        return request()->ip() ?? gethostname() ?? '127.0.0.1';
    }

    /**
     * Get creation audit attributes for a given table prefix.
     */
    public static function getCreationAudit(string $prefix): array
    {
        return [
            $prefix . 'UsuarioCreacion' => self::getCurrentUser(),
            $prefix . 'HostCreacion' => self::getCurrentHost(),
            $prefix . 'FechaCreacion' => now(),
        ];
    }

    /**
     * Get modification audit attributes for a given table prefix.
     */
    public static function getModificationAudit(string $prefix): array
    {
        return [
            $prefix . 'UsuarioModificacion' => self::getCurrentUser(),
            $prefix . 'HostModificacion' => self::getCurrentHost(),
            $prefix . 'FechaModificacion' => now(),
        ];
    }

    /**
     * Get deletion audit attributes for a given table prefix.
     */
    public static function getDeletionAudit(string $prefix): array
    {
        return [
            $prefix . 'Eliminado' => 'S',
            $prefix . 'UsuarioEliminacion' => self::getCurrentUser(),
            $prefix . 'HostEliminacion' => self::getCurrentHost(),
            $prefix . 'FechaEliminacion' => now(),
        ];
    }

    /**
     * Cache estático en memoria para resolución rápida de nombres por request
     */
    protected static array $nombresUsuariosCache = [];

    /**
     * Resuelve el nombre completo del empleado asociado a un identificador (correo, username o id).
     */
    public static function resolverNombreUsuario(?string $identificador): ?string
    {
        if (empty($identificador)) {
            return null;
        }

        $idTrim = trim($identificador);
        if (array_key_exists($idTrim, self::$nombresUsuariosCache)) {
            return self::$nombresUsuariosCache[$idTrim];
        }

        try {
            // 1. Buscar por correo de empleado
            $empleado = \App\Models\Empleado::where('EmpleadoCorreo', $idTrim)
                ->where('EmpleadoEliminado', 'N')
                ->first();

            if ($empleado) {
                $nombre = trim("{$empleado->EmpleadoNombres} {$empleado->EmpleadoApellidos}");
                return self::$nombresUsuariosCache[$idTrim] = $nombre ?: null;
            }

            // 2. Buscar por UsuarioUserName
            $usuario = \App\Models\Usuario::with('empleado')
                ->where('UsuarioUserName', $idTrim)
                ->where('UsuarioEliminado', 'N')
                ->first();

            if ($usuario?->empleado) {
                $nombre = trim("{$usuario->empleado->EmpleadoNombres} {$usuario->empleado->EmpleadoApellidos}");
                return self::$nombresUsuariosCache[$idTrim] = $nombre ?: null;
            }

            // 3. Buscar por EmpleadoId
            $empPorId = \App\Models\Empleado::where('EmpleadoId', $idTrim)
                ->where('EmpleadoEliminado', 'N')
                ->first();

            if ($empPorId) {
                $nombre = trim("{$empPorId->EmpleadoNombres} {$empPorId->EmpleadoApellidos}");
                return self::$nombresUsuariosCache[$idTrim] = $nombre ?: null;
            }
        } catch (\Throwable) {
            // Si la conexión o tabla no está disponible, no interrumpir ejecución
        }

        return self::$nombresUsuariosCache[$idTrim] = null;
    }
}

