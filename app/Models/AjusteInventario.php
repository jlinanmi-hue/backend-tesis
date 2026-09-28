<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteInventario extends Model
{
    use HasFactory;

    protected $table = 'Ajuste_inventario';
    protected $primaryKey = 'Ajuste_inventario_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Ajuste_inventario_id',
        'Ajuste_inventario_ProductoId',
        'Ajuste_inventario_tipo',
        'Ajuste_inventario_cantidad',
        'Ajuste_inventario_motivo',
        'Ajuste_inventario_fecha',
        'Ajuste_inventario_Eliminado',
        'Ajuste_inventario_UsuarioCreacion',
        'Ajuste_inventario_HostCreacion',
        'Ajuste_inventario_FechaCreacion',
        'Ajuste_inventario_UsuarioModificacion',
        'Ajuste_inventario_HostModificacion',
        'Ajuste_inventario_FechaModificacion',
        'Ajuste_inventario_UsuarioEliminacion',
        'Ajuste_inventario_HostEliminacion',
        'Ajuste_inventario_FechaEliminacion',
    ];

    protected $casts = [
        'Ajuste_inventario_cantidad' => 'decimal:2',
        'Ajuste_inventario_fecha' => 'datetime',
        'Ajuste_inventario_FechaCreacion' => 'datetime',
        'Ajuste_inventario_FechaModificacion' => 'datetime',
        'Ajuste_inventario_FechaEliminacion' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'Ajuste_inventario_ProductoId', 'ProductoId');
    }
}
