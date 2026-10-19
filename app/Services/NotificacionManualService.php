<?php

namespace App\Services;

use App\Models\RolUser;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class NotificacionManualService
{
    public function __construct(
        protected NotificacionService $notificacionService
    ) {}

    /**
     * Resuelve destinatarios, valida permisos y crea una notificación por
     * cada usuario del segmento seleccionado.
     */
    public function enviar(array $data): array
    {
        $remitente = $this->obtenerRemitenteValido((string) ($data['enviado_por'] ?? ''));
        $segmento = $data['destinatarios'] ?? [];

        $resolucion = $this->resolverDestinatarios($segmento);
        $destinatarios = $resolucion['usuarios'];

        if ($destinatarios->isEmpty()) {
            throw new AuthorizationException(
                'No se encontraron destinatarios activos para el segmento seleccionado.'
            );
        }

        [$titulo, $mensaje, $motivoKey, $motivoLabel] = $this->resolverContenido($data);

        $prioridad = strtoupper((string) ($data['prioridad'] ?? 'NORMAL'));
        $modo = $this->modoDestinatario($segmento);
        $remitenteNombre = $this->nombreUsuario($remitente);
        $destinoLabel = $this->describirDestino($segmento, $destinatarios->count());

        $datosTarjeta = $this->construirTarjeta(
            $remitente,
            $remitenteNombre,
            $destinoLabel,
            $motivoKey,
            $motivoLabel,
            $prioridad,
            $modo
        );

        $enviadas = 0;

        DB::transaction(function () use (
            $destinatarios,
            $titulo,
            $mensaje,
            $motivoKey,
            $remitente,
            $datosTarjeta,
            &$enviadas
        ) {
            foreach ($destinatarios as $usuario) {
                $this->notificacionService->crear([
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'tipo' => 'manual',
                    'categoria' => $motivoKey,
                    'referencia_id' => $remitente->UsuarioId,
                    'usuario_id' => $usuario->UsuarioId,
                    'datos_tarjeta' => $datosTarjeta,
                ]);

                $enviadas++;
            }
        });

        return [
            'enviadas' => $enviadas,
            'destinatarios' => $destinatarios->pluck('UsuarioId')->values()->all(),
            'deduplicados' => $resolucion['deduplicados'],
            'omitidos' => $resolucion['omitidos'],
            'modo' => $modo,
        ];
    }

    /**
     * Catálogo para el compositor: motivos, prioridades y roles con conteo
     * de usuarios activos.
     */
    public function catalogo(): array
    {
        $motivos = collect(config('notificaciones.motivos', []))
            ->map(fn (array $motivo, string $key) => [
                'key' => $key,
                'label' => $motivo['label'] ?? $key,
                'titulo' => $motivo['titulo'] ?? '',
                'plantilla' => $motivo['plantilla'] ?? '',
            ])
            ->values()
            ->all();

        $roles = RolUser::query()
            ->where('Rol_UserEliminado', 'N')
            ->withCount(['usuarios' => fn ($query) => $query->where('UsuarioEliminado', 'N')])
            ->orderBy('Rol_UserEmpleadocol')
            ->get()
            ->map(fn (RolUser $rol) => [
                'id' => $rol->Rol_UserId,
                'codigo' => $rol->Rol_UserEmpleadocol,
                'nombre' => $rol->Rol_UserRol_Usercol,
                'usuarios_count' => (int) $rol->usuarios_count,
            ])
            ->values()
            ->all();

        return [
            'motivos' => $motivos,
            'prioridades' => config('notificaciones.prioridades', ['NORMAL', 'ALTA', 'URGENTE']),
            'roles' => $roles,
        ];
    }

    /**
     * Búsqueda ligera de usuarios activos para el autocompletado del
     * compositor. Busca por usuario (login/correo), nombres, apellidos o
     * correo del empleado vinculado.
     */
    public function buscarUsuarios(string $termino, int $limite = 20): array
    {
        $termino = trim($termino);

        return Usuario::query()
            ->with(['empleado.cargo', 'rolUser'])
            ->where('UsuarioEliminado', 'N')
            ->when($termino !== '', function ($query) use ($termino) {
                $query->where(function ($subquery) use ($termino) {
                    $subquery->where('UsuarioUserName', 'like', "{$termino}%")
                        ->orWhereHas('empleado', function ($empleado) use ($termino) {
                            $empleado->where('EmpleadoNombres', 'like', "{$termino}%")
                                ->orWhere('EmpleadoApellidos', 'like', "{$termino}%")
                                ->orWhere('EmpleadoCorreo', 'like', "{$termino}%");
                        });
                });
            })
            ->orderBy('UsuarioId')
            ->limit($limite)
            ->get()
            ->map(fn (Usuario $usuario) => [
                'id' => $usuario->UsuarioId,
                'nombre' => $this->nombreUsuario($usuario),
                'usuario' => $usuario->UsuarioUserName,
                'email' => $usuario->empleado?->EmpleadoCorreo ?? $usuario->UsuarioUserName,
                'rol' => $usuario->rolUser?->Rol_UserEmpleadocol,
                'rol_nombre' => $usuario->rolUser?->Rol_UserRol_Usercol,
                'cargo' => $usuario->empleado?->cargo?->cargoDescripcion,
            ])
            ->values()
            ->all();
    }

    private function obtenerRemitenteValido(string $usuarioId): Usuario
    {
        $remitente = Usuario::query()
            ->with(['empleado', 'rolUser'])
            ->where('UsuarioEliminado', 'N')
            ->find($usuarioId);

        if (!$remitente) {
            throw new AuthorizationException('El usuario remitente no existe o está inactivo.');
        }

        $rol = strtoupper((string) ($remitente->rolUser?->Rol_UserEmpleadocol ?? ''));
        $autorizados = array_map('strtoupper', config('notificaciones.roles_autorizados', []));

        if (!in_array($rol, $autorizados, true)) {
            throw new AuthorizationException('No tienes permisos para enviar notificaciones manuales.');
        }

        return $remitente;
    }

    /**
     * @return array{usuarios: \Illuminate\Support\Collection, deduplicados: int, omitidos: int}
     */
    private function resolverDestinatarios(array $segmento): array
    {
        $todos = filter_var($segmento['todos'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($todos) {
            return [
                'usuarios' => Usuario::query()
                    ->where('UsuarioEliminado', 'N')
                    ->orderBy('UsuarioId')
                    ->get(),
                'deduplicados' => 0,
                'omitidos' => 0,
            ];
        }

        $idsSolicitados = collect($segmento['usuarios'] ?? [])
            ->filter(fn ($id) => is_string($id) && trim($id) !== '')
            ->map(fn ($id) => trim($id))
            ->unique()
            ->values();

        $roles = collect($segmento['roles'] ?? [])
            ->filter(fn ($rol) => is_string($rol) && trim($rol) !== '')
            ->map(fn ($rol) => strtoupper(trim($rol)))
            ->unique()
            ->values();

        $usuarios = Usuario::query()
            ->where('UsuarioEliminado', 'N')
            ->where(function ($query) use ($idsSolicitados, $roles) {
                if ($idsSolicitados->isNotEmpty()) {
                    $query->whereIn('UsuarioId', $idsSolicitados->all());
                }

                if ($roles->isNotEmpty()) {
                    $query->orWhereHas('rolUser', function ($rolQuery) use ($roles) {
                        $rolQuery->whereIn('Rol_UserEmpleadocol', $roles->all())
                            ->where('Rol_UserEliminado', 'N');
                    });
                }
            })
            ->orderBy('UsuarioId')
            ->get();

        $usuariosPorRol = $roles->isEmpty()
            ? 0
            : Usuario::query()
                ->where('UsuarioEliminado', 'N')
                ->whereHas('rolUser', function ($rolQuery) use ($roles) {
                    $rolQuery->whereIn('Rol_UserEmpleadocol', $roles->all())
                        ->where('Rol_UserEliminado', 'N');
                })
                ->count();

        $solicitados = $idsSolicitados->count() + $usuariosPorRol;

        return [
            'usuarios' => $usuarios,
            'deduplicados' => max(0, $solicitados - $usuarios->count()),
            'omitidos' => $idsSolicitados->diff($usuarios->pluck('UsuarioId'))->count(),
        ];
    }

    private function resolverContenido(array $data): array
    {
        $motivos = config('notificaciones.motivos', []);
        $motivoKey = (string) ($data['motivo'] ?? 'GENERAL');
        $motivo = $motivos[$motivoKey] ?? $motivos['GENERAL'] ?? [];

        $titulo = trim((string) ($data['titulo'] ?? ''));
        $mensaje = trim((string) ($data['mensaje'] ?? ''));

        if ($titulo === '') {
            $titulo = trim((string) ($motivo['titulo'] ?? 'Comunicado'));
        }

        if ($mensaje === '') {
            $mensaje = trim((string) ($motivo['plantilla'] ?? ''));
        }

        return [$titulo, $mensaje, $motivoKey, (string) ($motivo['label'] ?? 'Comunicado')];
    }

    private function modoDestinatario(array $segmento): string
    {
        if (filter_var($segmento['todos'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 'todos';
        }

        $tieneUsuarios = !empty($segmento['usuarios']);
        $tieneRoles = !empty($segmento['roles']);

        if ($tieneUsuarios && $tieneRoles) {
            return 'mixto';
        }

        return $tieneRoles ? 'rol' : 'usuario';
    }

    private function describirDestino(array $segmento, int $total): string
    {
        return match ($this->modoDestinatario($segmento)) {
            'todos' => 'Todos los usuarios activos',
            'rol' => 'Rol: ' . implode(', ', array_map('strtoupper', $segmento['roles'] ?? [])),
            'mixto' => 'Usuarios y roles seleccionados',
            default => $total === 1 ? '1 usuario' : "{$total} usuarios",
        };
    }

    private function construirTarjeta(
        Usuario $remitente,
        string $remitenteNombre,
        string $destino,
        string $motivoKey,
        string $motivoLabel,
        string $prioridad,
        string $modo
    ): array {
        $estilos = [
            'NORMAL' => ['tipo' => 'info', 'color' => '#3B82F6', 'indicador' => 'Comunicado'],
            'ALTA' => ['tipo' => 'warning', 'color' => '#F59E0B', 'indicador' => 'Prioridad alta'],
            'URGENTE' => ['tipo' => 'danger', 'color' => '#EF4444', 'indicador' => 'Urgente'],
        ];

        $estilo = $estilos[$prioridad] ?? $estilos['NORMAL'];

        return [
            'badge' => [
                'texto' => $motivoLabel,
                'tipo' => $estilo['tipo'],
                'color' => $estilo['color'],
                'icono' => 'campaign',
            ],
            'entidad' => 'COMERCIAL VALENCIA',
            'referencia_codigo' => $remitente->UsuarioId,
            'flujo' => [
                'origen' => $remitenteNombre,
                'destino' => $destino,
                'indicador' => $estilo['indicador'],
            ],
            'impacto_financiero' => null,
            'accion' => [
                'texto' => 'Ver notificación',
                'url' => '/notificaciones',
            ],
            'modo_audio' => 'audible',
            'prioridad' => $prioridad,
            'motivo' => $motivoKey,
            'enviado_por' => $remitente->UsuarioId,
            'modo' => $modo,
        ];
    }

    private function nombreUsuario(Usuario $usuario): string
    {
        $nombre = trim(
            ($usuario->empleado?->EmpleadoNombres ?? '') . ' ' .
            ($usuario->empleado?->EmpleadoApellidos ?? '')
        );

        return $nombre !== '' ? $nombre : (string) $usuario->UsuarioUserName;
    }
}
