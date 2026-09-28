<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cargo extends Model
{
    use HasFactory;

    protected $table = 'cargo';
    protected $primaryKey = 'cargoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'cargoId',
        'cargoDescripcion',
        'cargoEstado',
        'cargoEliminado',
        'cargoUsuarioCreacion',
        'cargoHostCreacion',
        'cargoFechaCreacion',
        'cargoUsuarioModificacion',
        'cargoHostModificacion',
        'cargoFechaModificacion',
        'cargoUsuarioEliminacion',
        'cargoHostEliminacion',
        'cargoFechaEliminacion',
    ];

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'Empleado_cargoId', 'cargoId');
    }
}
