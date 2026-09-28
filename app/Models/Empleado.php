<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empleado extends Model
{
    use HasFactory;

    protected $table = 'Empleado';
    protected $primaryKey = 'EmpleadoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'EmpleadoId',
        'EmpleadoNombres',
        'EmpleadoApellidos',
        'EmpleadoDni',
        'EmpleadoTelefono',
        'EmpleadoCorreo',
        'EmpleadoSexo',
        'EmpleadoFechaIngreso',
        'EmpleadoEstado',
        'Empleado_cargoId',
        'EmpleadoEliminado',
        'EmpleadoUsuarioCreacion',
        'EmpleadoHostCreacion',
        'EmpleadoFechaCreacion',
        'EmpleadoUsuarioModificacion',
        'EmpleadoHostModificacion',
        'EmpleadoFechaModificacion',
        'EmpleadoUsuarioEliminacion',
        'EmpleadoHostEliminacion',
        'EmpleadoFechaEliminacion',
    ];

    protected $casts = [
        'EmpleadoFechaIngreso' => 'date:Y-m-d',
        'EmpleadoFechaCreacion' => 'datetime',
        'EmpleadoFechaModificacion' => 'datetime',
        'EmpleadoFechaEliminacion' => 'datetime',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'Empleado_cargoId', 'cargoId');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'Usuario_EmpleadoId', 'EmpleadoId');
    }
}
