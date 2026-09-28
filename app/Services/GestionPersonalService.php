<?php

namespace App\Services;

use App\Jobs\SendActualizacionEmailJob;
use App\Jobs\SendBienvenidaEmailJob;
use App\Models\Empleado;
use App\Models\RolUser;
use App\Repositories\Contracts\CargoRepositoryInterface;
use App\Repositories\Contracts\EmpleadoRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use App\Support\AuditHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GestionPersonalService
{
    public function __construct(
        protected EmpleadoRepositoryInterface $empleadoRepository,
        protected UsuarioRepositoryInterface $usuarioRepository,
        protected CargoRepositoryInterface $cargoRepository
    ) {}

    /**
     * List paginated or filtered personal records.
     */
    public function listarPersonal(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->empleadoRepository->getAll($filters, $perPage);
    }

    /**
     * Get a specific personal record by Empleado ID.
     */
    public function obtenerPersonalPorId(string $empleadoId, bool $includeDeleted = false): Empleado
    {
        $empleado = $this->empleadoRepository->findById($empleadoId, $includeDeleted);

        if (!$empleado) {
            throw new Exception("Empleado con ID '{$empleadoId}' no encontrado.");
        }

        return $empleado;
    }

    /**
     * Create an Empleado and automatically generate its corresponding Usuario.
     * Transactional operation + Async Queue Job Dispatch after commit.
     */
    public function crearPersonal(array $datos, ?string $rolUserId = null): Empleado
    {
        return DB::transaction(function () use ($datos, $rolUserId) {
            // 1. Validar unicidad previa de DNI y Correo
            if ($this->empleadoRepository->findByDni($datos['EmpleadoDni'])) {
                throw ValidationException::withMessages([
                    'EmpleadoDni' => 'El DNI ingresado ya se encuentra registrado.',
                ]);
            }

            if ($this->empleadoRepository->findByEmail($datos['EmpleadoCorreo'])) {
                throw ValidationException::withMessages([
                    'EmpleadoCorreo' => 'El correo electrónico ingresado ya se encuentra registrado.',
                ]);
            }

            // 2. Determinar Rol_UserId si no se proporcionó
            $rolId = $rolUserId ?? $this->determinarRolPorCargo($datos['Empleado_cargoId']);

            // 3. Preparar datos de Empleado con ID correlativo, fecha de ingreso y Auditoría
            $empleadoId = $this->empleadoRepository->getNextId();
            $auditEmpleado = AuditHelper::getCreationAudit('Empleado');
            $fechaIngreso = !empty($datos['EmpleadoFechaIngreso'])
                ? Carbon::parse($datos['EmpleadoFechaIngreso'])
                : now();

            $payloadEmpleado = array_merge($datos, [
                'EmpleadoId' => $empleadoId,
                'EmpleadoFechaIngreso' => $fechaIngreso->format('Y-m-d'),
                'EmpleadoEstado' => $datos['EmpleadoEstado'] ?? 'A',
                'EmpleadoEliminado' => 'N',
            ], $auditEmpleado);

            // 4. Guardar Empleado
            $empleado = $this->empleadoRepository->create($payloadEmpleado);

            // 5. Generar credenciales automáticas para Usuario:
            // username = EmpleadoCorreo, password = Hash(EmpleadoDni)
            $usuarioId = $this->usuarioRepository->getNextId();
            $auditUsuario = AuditHelper::getCreationAudit('Usuario');
            $passwordInicial = (string) $empleado->EmpleadoDni;

            $payloadUsuario = [
                'UsuarioId' => $usuarioId,
                'UsuarioUserName' => $empleado->EmpleadoCorreo,
                'UsuarioPassword' => Hash::make($passwordInicial),
                'Usuario_Rol_UserId' => $rolId,
                'Usuario_EmpleadoId' => $empleado->EmpleadoId,
                'UsuarioEliminado' => 'N',
            ];
            $payloadUsuario = array_merge($payloadUsuario, $auditUsuario);

            // 6. Guardar Usuario
            $this->usuarioRepository->create($payloadUsuario);

            $empleadoCompleto = $empleado->fresh(['cargo', 'usuarios.rolUser']);
            $fechaIngresoFormatted = $fechaIngreso->format('d/m/Y');

            // 7. Encolar Job de Correo Asíncrono de Bienvenida SOLO tras confirmación de la transacción
            DB::afterCommit(function () use ($empleadoCompleto, $passwordInicial, $fechaIngresoFormatted) {
                SendBienvenidaEmailJob::dispatch(
                    $empleadoCompleto,
                    $passwordInicial,
                    $fechaIngresoFormatted
                );
            });

            return $empleadoCompleto;
        });
    }

    /**
     * Update an existing Empleado, synchronize Usuario and notify changes via Email.
     * Transactional operation.
     */
    public function actualizarPersonal(
        string $empleadoId,
        array $datos,
        ?string $nuevoRolId = null,
        bool $resetearPassword = false
    ): Empleado {
        return DB::transaction(function () use ($empleadoId, $datos, $nuevoRolId, $resetearPassword) {
            $empleado = $this->obtenerPersonalPorId($empleadoId);

            // 1. Validar unicidad si cambió DNI
            if (isset($datos['EmpleadoDni']) && $datos['EmpleadoDni'] !== $empleado->EmpleadoDni) {
                $existenteDni = $this->empleadoRepository->findByDni($datos['EmpleadoDni']);
                if ($existenteDni && $existenteDni->EmpleadoId !== $empleadoId) {
                    throw ValidationException::withMessages([
                        'EmpleadoDni' => 'El DNI ingresado ya pertenece a otro empleado.',
                    ]);
                }
            }

            // 2. Validar unicidad si cambió Correo
            if (isset($datos['EmpleadoCorreo']) && $datos['EmpleadoCorreo'] !== $empleado->EmpleadoCorreo) {
                $existenteCorreo = $this->empleadoRepository->findByEmail($datos['EmpleadoCorreo']);
                if ($existenteCorreo && $existenteCorreo->EmpleadoId !== $empleadoId) {
                    throw ValidationException::withMessages([
                        'EmpleadoCorreo' => 'El correo ingresado ya pertenece a otro empleado.',
                    ]);
                }
            }

            // 3. Detectar diferencias ('diff') para la notificación de actualización
            $cambios = $this->calcularDiferencias($empleado, $datos);

            // 4. Actualizar datos del empleado y auditoría
            $auditEmpleado = AuditHelper::getModificationAudit('Empleado');
            $payloadEmpleado = array_merge($datos, $auditEmpleado);

            if (isset($datos['EmpleadoFechaIngreso'])) {
                $payloadEmpleado['EmpleadoFechaIngreso'] = Carbon::parse($datos['EmpleadoFechaIngreso'])->format('Y-m-d');
            }

            $empleadoActualizado = $this->empleadoRepository->update($empleadoId, $payloadEmpleado);

            // 5. Sincronizar Usuario asociado
            $usuario = $this->usuarioRepository->findByEmpleadoId($empleadoId);

            if ($usuario) {
                $payloadUsuario = AuditHelper::getModificationAudit('Usuario');

                // Si el correo cambió, actualizar el username del usuario
                if (isset($datos['EmpleadoCorreo']) && $datos['EmpleadoCorreo'] !== $usuario->UsuarioUserName) {
                    $payloadUsuario['UsuarioUserName'] = $datos['EmpleadoCorreo'];
                }

                // Si se cambió el DNI o se pidió reset de password, actualizar hash
                if ($resetearPassword || (isset($datos['EmpleadoDni']) && $datos['EmpleadoDni'] !== $empleado->EmpleadoDni)) {
                    $nuevoDni = $datos['EmpleadoDni'] ?? $empleadoActualizado->EmpleadoDni;
                    $payloadUsuario['UsuarioPassword'] = Hash::make($nuevoDni);
                }

                // Si se especifica nuevo rol
                if ($nuevoRolId) {
                    $payloadUsuario['Usuario_Rol_UserId'] = $nuevoRolId;
                } elseif (isset($datos['Empleado_cargoId']) && $datos['Empleado_cargoId'] !== $empleado->Empleado_cargoId) {
                    $payloadUsuario['Usuario_Rol_UserId'] = $this->determinarRolPorCargo($datos['Empleado_cargoId']);
                }

                $this->usuarioRepository->update($usuario->UsuarioId, $payloadUsuario);
            }

            $empleadoFinal = $empleadoActualizado->fresh(['cargo', 'usuarios.rolUser']);

            // 6. Encolar Job de Notificación de Actualización si hubo cambios relevantes
            if (count($cambios) > 0) {
                DB::afterCommit(function () use ($empleadoFinal, $cambios) {
                    SendActualizacionEmailJob::dispatch($empleadoFinal, $cambios);
                });
            }

            return $empleadoFinal;
        });
    }

    /**
     * Update or reset password specifically for a personal account.
     */
    public function cambiarPassword(string $empleadoId, string $nuevaPassword, bool $esReset = false): bool
    {
        return DB::transaction(function () use ($empleadoId, $nuevaPassword) {
            $empleado = $this->obtenerPersonalPorId($empleadoId);
            $usuario = $this->usuarioRepository->findByEmpleadoId($empleadoId);

            if (!$usuario) {
                throw new Exception("El empleado no posee una cuenta de usuario asociada.");
            }

            $payload = array_merge([
                'UsuarioPassword' => Hash::make($nuevaPassword),
            ], AuditHelper::getModificationAudit('Usuario'));

            $this->usuarioRepository->update($usuario->UsuarioId, $payload);

            return true;
        });
    }

    /**
     * Fast update for employee status (Absences, Vacations, Leaves, Active, Inactive).
     */
    public function cambiarEstado(string $empleadoId, string $nuevoEstado, ?string $motivo = null): Empleado
    {
        return DB::transaction(function () use ($empleadoId, $nuevoEstado) {
            $empleado = $this->obtenerPersonalPorId($empleadoId);
            $estadoAnterior = $empleado->EmpleadoEstado;

            $payloadEmpleado = array_merge([
                'EmpleadoEstado' => $nuevoEstado,
            ], AuditHelper::getModificationAudit('Empleado'));

            $empleadoActualizado = $this->empleadoRepository->update($empleadoId, $payloadEmpleado);

            // Si hubo cambio de estado, notificar por correo
            if ($estadoAnterior !== $nuevoEstado) {
                $cambios = [[
                    'campo' => 'Estado / Condición Laboral',
                    'anterior' => $this->describirEstado($estadoAnterior),
                    'nuevo' => $this->describirEstado($nuevoEstado),
                ]];

                DB::afterCommit(function () use ($empleadoActualizado, $cambios) {
                    SendActualizacionEmailJob::dispatch($empleadoActualizado, $cambios);
                });
            }

            return $empleadoActualizado;
        });
    }

    /**
     * Soft delete (logical delete) an Empleado and its associated Usuario.
     * Transactional operation.
     */
    public function eliminarPersonal(string $empleadoId): bool
    {
        return DB::transaction(function () use ($empleadoId) {
            $empleado = $this->obtenerPersonalPorId($empleadoId);

            // Eliminación lógica de Empleado
            $auditEmpleado = AuditHelper::getDeletionAudit('Empleado');
            $this->empleadoRepository->deleteLogico($empleadoId, $auditEmpleado);

            // Eliminación lógica del Usuario asociado
            $usuario = $this->usuarioRepository->findByEmpleadoId($empleadoId);
            if ($usuario) {
                $auditUsuario = AuditHelper::getDeletionAudit('Usuario');
                $this->usuarioRepository->deleteLogico($usuario->UsuarioId, $auditUsuario);
            }

            return true;
        });
    }

    /**
     * Restore a logically deleted Empleado and its associated Usuario.
     */
    public function restaurarPersonal(string $empleadoId): bool
    {
        return DB::transaction(function () use ($empleadoId) {
            $empleado = $this->obtenerPersonalPorId($empleadoId, true);

            $auditEmpleado = AuditHelper::getModificationAudit('Empleado');
            $this->empleadoRepository->restore($empleadoId, $auditEmpleado);

            $usuario = $this->usuarioRepository->findByEmpleadoId($empleadoId, true);
            if ($usuario) {
                $auditUsuario = AuditHelper::getModificationAudit('Usuario');
                $this->usuarioRepository->restore($usuario->UsuarioId, $auditUsuario);
            }

            return true;
        });
    }

    /**
     * Calculate diff array between old and new values.
     */
    protected function calcularDiferencias(Empleado $empleado, array $datosNuevos): array
    {
        $cambios = [];
        $mapLabels = [
            'EmpleadoNombres' => 'Nombres',
            'EmpleadoApellidos' => 'Apellidos',
            'EmpleadoDni' => 'DNI',
            'EmpleadoTelefono' => 'Teléfono',
            'EmpleadoCorreo' => 'Correo Electrónico',
            'EmpleadoSexo' => 'Sexo',
            'EmpleadoEstado' => 'Estado',
            'Empleado_cargoId' => 'Cargo',
            'EmpleadoFechaIngreso' => 'Fecha de Ingreso',
        ];

        foreach ($mapLabels as $campo => $label) {
            if (array_key_exists($campo, $datosNuevos)) {
                $valorAntiguo = (string) ($empleado->{$campo} ?? '');
                $valorNuevo = (string) ($datosNuevos[$campo] ?? '');

                if ($campo === 'EmpleadoFechaIngreso') {
                    $valorAntiguo = $empleado->EmpleadoFechaIngreso ? Carbon::parse($empleado->EmpleadoFechaIngreso)->format('d/m/Y') : '';
                    $valorNuevo = $datosNuevos[$campo] ? Carbon::parse($datosNuevos[$campo])->format('d/m/Y') : '';
                }

                if ($valorAntiguo !== $valorNuevo) {
                    $cambios[] = [
                        'campo' => $label,
                        'anterior' => $valorAntiguo ?: 'No asignado',
                        'nuevo' => $valorNuevo ?: 'No asignado',
                    ];
                }
            }
        }

        return $cambios;
    }

    /**
     * Human-readable description for state codes.
     */
    protected function describirEstado(string $codigo): string
    {
        return match ($codigo) {
            'A' => 'Activo',
            'V' => 'De Vacaciones',
            'L' => 'De Licencia / Descanso Médico',
            'S' => 'Suspendido',
            'I' => 'Inactivo',
            default => $codigo,
        };
    }

    /**
     * Determine corresponding Rol_UserId from cargoId.
     */
    protected function determinarRolPorCargo(string $cargoId): string
    {
        $map = [
            'CAR-001' => 'ROL-001',
            'CAR-002' => 'ROL-002',
            'CAR-003' => 'ROL-003',
            'CAR-004' => 'ROL-004',
        ];

        if (isset($map[$cargoId])) {
            return $map[$cargoId];
        }

        $primerRol = RolUser::where('Rol_UserEliminado', 'N')->first();
        return $primerRol ? $primerRol->Rol_UserId : 'ROL-001';
    }
}
