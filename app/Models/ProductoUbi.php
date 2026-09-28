<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoUbi extends Model
{
    use HasFactory;

    protected $table = 'producto_ubi';
    protected $primaryKey = 'producto_ubi_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'producto_ubi_id',
        'producto_ubi_descripcion',
        'producto_ubi_estado',
        'producto_ubi_observacion',
        'producto_ubi_Eliminado',
        'producto_ubi_UsuarioCreacion',
        'producto_ubi_HostCreacion',
        'producto_ubi_FechaCreacion',
        'producto_ubi_UsuarioModificacion',
        'producto_ubi_HostModificacion',
        'producto_ubi_FechaModificacion',
        'producto_ubi_UsuarioEliminacion',
        'producto_ubi_HostEliminacion',
        'producto_ubi_FechaEliminacion',
    ];

    protected $casts = [
        'producto_ubi_FechaCreacion' => 'datetime',
        'producto_ubi_FechaModificacion' => 'datetime',
        'producto_ubi_FechaEliminacion' => 'datetime',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'Producto_producto_ubi_id', 'producto_ubi_id');
    }
}
