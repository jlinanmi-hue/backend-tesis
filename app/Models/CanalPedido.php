<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanalPedido extends Model
{
    use HasFactory;

    protected $table = 'Canal_pedido';
    protected $primaryKey = 'Canal_pedidoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Canal_pedidoId',
        'Canal_pedidoDescripcion',
        'Canal_pedidoEstado',
        'Canal_pedidoEliminado',
        'Canal_pedidoUsuarioCreacion',
        'Canal_pedidoHostCreacion',
        'Canal_pedidoFechaCreacion',
        'Canal_pedidoUsuarioModificacion',
        'Canal_pedidoHostModificacion',
        'Canal_pedidoFechaModificacion',
        'Canal_pedidoUsuarioEliminacion',
        'Canal_pedidoHostEliminacion',
        'Canal_pedidoFechaEliminacion',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'Pedido_canal_pedidoId', 'Canal_pedidoId');
    }
}
