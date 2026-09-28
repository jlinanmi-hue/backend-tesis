<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'Cliente';
    protected $primaryKey = 'ClienteId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ClienteId',
        'ClienteNombre',
        'ClienteDireccion',
        'ClienteNumero',
        'ClienteEstado',
        'ClienteRuc',
        'ClienteComprasCount',
        'ClienteTotalMonto',
        'ClienteUltimaCompra',
        'ClienteEliminado',
        'ClienteUsuarioCreacion',
        'ClienteHostCreacion',
        'ClienteFechaCreacion',
        'ClienteUsuarioModificacion',
        'ClienteHostModificacion',
        'ClienteFechaModificacion',
        'ClienteUsuarioEliminacion',
        'ClienteHostEliminacion',
        'ClienteFechaEliminacion',
    ];

    protected $casts = [
        'ClienteComprasCount' => 'integer',
        'ClienteTotalMonto' => 'decimal:2',
        'ClienteUltimaCompra' => 'datetime',
        'ClienteFechaCreacion' => 'datetime',
        'ClienteFechaModificacion' => 'datetime',
        'ClienteFechaEliminacion' => 'datetime',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'Pedido_ClienteId', 'ClienteId');
    }

    /**
     * Scope para clientes activos.
     */
    public function scopeActivos($query)
    {
        return $query->where('ClienteEliminado', 'N')->where('ClienteEstado', 'A');
    }

    /**
     * Scope para clientes recurrentes (alta fidelidad y compras recientes).
     */
    public function scopeRecurrentes($query, int $minCompras = 3, int $diasRecientes = 90)
    {
        return $query->where('ClienteEliminado', 'N')
            ->where('ClienteEstado', 'A')
            ->where(function ($q) use ($minCompras, $diasRecientes) {
                $q->where(function ($sub) use ($minCompras, $diasRecientes) {
                    $sub->where('ClienteComprasCount', '>=', $minCompras)
                        ->where('ClienteUltimaCompra', '>=', now()->subDays($diasRecientes));
                })->orWhere('ClienteComprasCount', '>=', 5);
            });
    }

    /**
     * Scope para clientes inactivos (desactivados con estado 'I' o clientes que compraban y llevan más de X días sin comprar).
     */
    public function scopeInactivos($query, int $diasInactividad = 90)
    {
        return $query->where('ClienteEliminado', 'N')
            ->where(function ($q) use ($diasInactividad) {
                $q->where('ClienteEstado', 'I')
                  ->orWhere(function ($sub) use ($diasInactividad) {
                      $sub->where('ClienteEstado', 'A')
                          ->where('ClienteComprasCount', '>', 0)
                          ->where('ClienteUltimaCompra', '<', now()->subDays($diasInactividad));
                  });
            });
    }

    /**
     * Scope para ordenar por mayor número de compras acumuladas.
     */
    public function scopeMasCompras($query)
    {
        return $query->where('ClienteEliminado', 'N')->orderBy('ClienteComprasCount', 'desc');
    }
}
