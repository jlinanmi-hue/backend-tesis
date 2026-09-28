<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RolUser extends Model
{
    use HasFactory;

    protected $table = 'Rol_User';
    protected $primaryKey = 'Rol_UserId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Rol_UserId',
        'Rol_UserEmpleadocol',
        'Rol_UserRol_Usercol',
        'Rol_UserEliminado',
        'Rol_UserUsuarioCreacion',
        'Rol_UserHostCreacion',
        'Rol_UserFechaCreacion',
        'Rol_UserUsuarioModificacion',
        'Rol_UserHostModificacion',
        'Rol_UserFechaModificacion',
        'Rol_UserUsuarioEliminacion',
        'Rol_UserHostEliminacion',
        'Rol_UserFechaEliminacion',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'Usuario_Rol_UserId', 'Rol_UserId');
    }
}
