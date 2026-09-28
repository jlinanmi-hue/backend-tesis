<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleOrdenCompra extends Model
{
    use HasFactory;

    protected $table = 'Detalle_Orden_Compra';
    protected $primaryKey = 'Detalle_Orden_CompraId';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Detalle_Orden_CompraId',
        'Detalle_Orden_Compra_Orden_CompraId',
        'Detalle_ProductoId',
        'Detalle_UnidadMedidaId',
        'Detalle_Orden_CompraCantidad',
        'Detalle_Orden_CompraPrecioUnitario',
        'Detalle_Orden_CompraSubtotal',
        'Detalle_Orden_CompraEliminado',
        'Detalle_Orden_CompraUsuarioCreacion',
        'Detalle_Orden_CompraHostCreacion',
        'Detalle_Orden_CompraFechaCreacion',
        'Detalle_Orden_CompraUsuarioModificacion',
        'Detalle_Orden_CompraHostModificacion',
        'Detalle_Orden_CompraFechaModificacion',
        'Detalle_Orden_CompraUsuarioEliminacion',
        'Detalle_Orden_CompraHostEliminacion',
        'Detalle_Orden_CompraFechaEliminacion',
    ];

    protected $casts = [
        'Detalle_Orden_CompraCantidad' => 'float',
        'Detalle_Orden_CompraPrecioUnitario' => 'float',
        'Detalle_Orden_CompraSubtotal' => 'float',
        'Detalle_Orden_CompraFechaCreacion' => 'datetime',
        'Detalle_Orden_CompraFechaModificacion' => 'datetime',
        'Detalle_Orden_CompraFechaEliminacion' => 'datetime',
    ];

    /**
     * Relación con la cabecera Orden_Compra
     */
    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'Detalle_Orden_Compra_Orden_CompraId', 'Orden_CompraId');
    }

    /**
     * Relación con el Producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'Detalle_ProductoId', 'ProductoId');
    }

    /**
     * Relación con la Unidad de Medida
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadesMedida::class, 'Detalle_UnidadMedidaId', 'unidades_medidaId');
    }

    /**
     * Hooks del ciclo de vida del modelo.
     * Invalida la caché de predicciones ante inserción, modificación o eliminación
     * de ítems de órdenes de compra (modifica stock en tránsito o historial).
     */
    protected static function booted(): void
    {
        $invalidar = function () {
            app(\App\Services\PurchasePredictionService::class)->invalidarCacheSemanal();
        };

        static::saved($invalidar);
        static::deleted($invalidar);
    }
}
