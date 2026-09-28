<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'Usuario';
    protected $primaryKey = 'UsuarioId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'UsuarioId',
        'UsuarioUserName',
        'UsuarioPassword',
        'Usuario_Rol_UserId',
        'Usuario_EmpleadoId',
        'UsuarioEliminado',
        'UsuarioUsuarioCreacion',
        'UsuarioHostCreacion',
        'UsuarioFechaCreacion',
        'UsuarioUsuarioModificacion',
        'UsuarioHostModificacion',
        'UsuarioFechaModificacion',
        'UsuarioUsuarioEliminacion',
        'UsuarioHostEliminacion',
        'UsuarioFechaEliminacion',
    ];

    protected $hidden = [
        'UsuarioPassword',
    ];

    public function getAuthPassword(): string
    {
        return $this->UsuarioPassword;
    }

    public function rolUser(): BelongsTo
    {
        return $this->belongsTo(RolUser::class, 'Usuario_Rol_UserId', 'Rol_UserId');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'Usuario_EmpleadoId', 'EmpleadoId');
    }
}
