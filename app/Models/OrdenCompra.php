<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    use HasFactory;

    protected $table = 'Orden_Compra';
    protected $primaryKey = 'Orden_CompraId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Orden_CompraId',
        'Orden_CompraFecha',
        'Orden_CompraSubtotal',
        'Orden_CompraIgv',
        'Orden_CompraTotal',
        'Orden_CompraEstado', // 'P' = Pendiente, 'C' = Completada/Recibida, 'A' = Anulada
        'Orden_CompraObservacion',
        'Orden_Compra_ProveedorId',
        'Orden_CompraEliminado',
        'Orden_CompraUsuarioCreacion',
        'Orden_CompraHostCreacion',
        'Orden_CompraFechaCreacion',
        'Orden_CompraUsuarioModificacion',
        'Orden_CompraHostModificacion',
        'Orden_CompraFechaModificacion',
        'Orden_CompraUsuarioEliminacion',
        'Orden_CompraHostEliminacion',
        'Orden_CompraFechaEliminacion',
    ];

    protected $casts = [
        'Orden_CompraFecha' => 'datetime',
        'Orden_CompraSubtotal' => 'float',
        'Orden_CompraIgv' => 'float',
        'Orden_CompraTotal' => 'float',
        'Orden_CompraFechaCreacion' => 'datetime',
        'Orden_CompraFechaModificacion' => 'datetime',
        'Orden_CompraFechaEliminacion' => 'datetime',
    ];

    /**
     * Relación con el Proveedor
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'Orden_Compra_ProveedorId', 'ProveedorId');
    }

    /**
     * Relación con los detalles de la orden
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleOrdenCompra::class, 'Detalle_Orden_Compra_Orden_CompraId', 'Orden_CompraId')
            ->where('Detalle_Orden_CompraEliminado', 'N');
    }

    /**
     * Scope para excluir eliminados
     */
    public function scopeActivos($query)
    {
        return $query->where('Orden_CompraEliminado', 'N');
    }

    /**
     * Scope para filtrar por estado ('P', 'C', 'A')
     */

    /**
     * Scope para filtrar por estado ('P', 'C', 'A')
     */
    public function scopePorEstado($query, ?string $estado)
    {
        if (!empty($estado)) {
            return $query->where('Orden_CompraEstado', strtoupper($estado));
        }
        return $query;
    }

    /**
     * Hooks del ciclo de vida del modelo.
     * Invalida la caché de predicciones de compra cuando se crea o cambia el estado de la orden.
     */
    protected static function booted(): void
    {
        $invalidar = function (OrdenCompra $orden) {
            if ($orden->wasRecentlyCreated || $orden->isDirty('Orden_CompraEstado')) {
                app(\App\Services\PurchasePredictionService::class)->invalidarCacheSemanal();
            }
        };

        static::saved($invalidar);

        static::deleted(function () {
            app(\App\Services\PurchasePredictionService::class)->invalidarCacheSemanal();
        });
    }
}
