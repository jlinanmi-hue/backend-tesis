<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoProducto extends Model
{
    use HasFactory;

    protected $table = 'Movimiento_producto';
    protected $primaryKey = 'Movimiento_productoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Movimiento_productoId',
        'Movimiento_productoCantidadPresentacion',
        'Movimiento_productoDocumentoOperacionId',
        'Movimiento_productoTipoMovimiento',
        'Movimiento_productoCostoPrecioUnitario',
        'Movimiento_productoCantidadEntrada',
        'Movimiento_productoCantidadSalida',
        'Movimiento_productoCantidadSaldo',
        'Movimiento_productoFecha_Movimiento',
        'Movimiento_producto_ProductoId',
        'Movimiento_producto_Detalle_Producto_medida_ProductoId', // Alias de compatibilidad
        'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId',
        'Movimiento_productoSubtipo',
        'Movimiento_productoReferenciaTipo',
        'Movimiento_productoReferenciaId',
        'Movimiento_productoMotivo',
        'Movimiento_productoEliminado',
        'Movimiento_productoUsuarioCreacion',
        'Movimiento_productoHostCreacion',
        'Movimiento_productoFechaCreacion',
        'Movimiento_productoUsuarioModificacion',
        'Movimiento_productoHostModificacion',
        'Movimiento_productoFechaModificacion',
        'Movimiento_productoUsuarioEliminacion',
        'Movimiento_productoHostEliminacion',
        'Movimiento_productoFechaEliminacion',
    ];

    protected $casts = [
        'Movimiento_productoCostoPrecioUnitario' => 'decimal:2',
        'Movimiento_productoFecha_Movimiento' => 'datetime',
        'Movimiento_productoFechaCreacion' => 'datetime',
        'Movimiento_productoFechaModificacion' => 'datetime',
        'Movimiento_productoFechaEliminacion' => 'datetime',
    ];

    /**
     * Mutator de compatibilidad para asignar el ProductoId
     */
    public function setMovimientoProductoDetalleProductoMedidaProductoIdAttribute($value): void
    {
        $this->attributes['Movimiento_producto_ProductoId'] = $value;
    }

    /**
     * Accessor de compatibilidad
     */
    public function getMovimientoProductoDetalleProductoMedidaProductoIdAttribute()
    {
        return $this->attributes['Movimiento_producto_ProductoId'] ?? null;
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class,
            'Movimiento_producto_ProductoId',
            'ProductoId'
        );
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(
            UnidadesMedida::class,
            'Movimiento_producto_Detalle_Producto_medida_unidades_medidaId',
            'unidades_medidaId'
        );
    }

    public function detalleProductoMedida(): BelongsTo
    {
        return $this->belongsTo(
            DetalleProductoMedida::class,
            'Movimiento_producto_ProductoId',
            'Detalle_Producto_medida_ProductoId'
        );
    }

    /**
     * Hooks del ciclo de vida del modelo.
     * Invalida la caché de predicciones de compra ante nuevos movimientos de Kardex
     * (entradas de mercadería, ajustes de stock, etc.).
     */
    protected static function booted(): void
    {
        static::saved(function (MovimientoProducto $mov) {
            if ($mov->wasRecentlyCreated) {
                app(\App\Services\PurchasePredictionService::class)->invalidarCacheSemanal();
            }
        });
    }
}
