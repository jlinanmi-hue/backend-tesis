<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleProductoMedida extends Model
{
    use HasFactory;

    protected $table = 'Detalle_Producto_medida';
    protected $primaryKey = 'Detalle_Producto_medida_ProductoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    /**
     * Set the keys for a save update query (Composite Primary Key).
     */
    protected function setKeysForSaveQuery($query)
    {
        $query->where('Detalle_Producto_medida_ProductoId', '=', $this->getAttribute('Detalle_Producto_medida_ProductoId'))
              ->where('Detalle_Producto_medida_unidades_medidaId', '=', $this->getAttribute('Detalle_Producto_medida_unidades_medidaId'));

        return $query;
    }

    protected $fillable = [
        'Detalle_Producto_medida_ProductoId',
        'Detalle_Producto_medida_unidades_medidaId',
        'Detalle_Producto_medida_factor_conversion',
        'Detalle_Producto_medida_precio_venta',
        'Detalle_Producto_medida_precio_compra',
        'Detalle_Producto_medidaEliminado',
        'Detalle_Producto_medidaUsuarioCreacion',
        'Detalle_Producto_medidaHostCreacion',
        'Detalle_Producto_medidaFechaCreacion',
        'Detalle_Producto_medidaUsuarioModificacion',
        'Detalle_Producto_medidaHostModificacion',
        'Detalle_Producto_medidaFechaModificacion',
        'Detalle_Producto_medidaUsuarioEliminacion',
        'Detalle_Producto_medidaHostEliminacion',
        'Detalle_Producto_medidaFechaEliminacion',
    ];

    protected $casts = [
        'Detalle_Producto_medida_factor_conversion' => 'integer',
        'Detalle_Producto_medida_precio_venta' => 'decimal:2',
        'Detalle_Producto_medida_precio_compra' => 'decimal:2',
        'Detalle_Producto_medidaFechaCreacion' => 'datetime',
        'Detalle_Producto_medidaFechaModificacion' => 'datetime',
        'Detalle_Producto_medidaFechaEliminacion' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'Detalle_Producto_medida_ProductoId', 'ProductoId');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadesMedida::class, 'Detalle_Producto_medida_unidades_medidaId', 'unidades_medidaId');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            MovimientoProducto::class,
            'Movimiento_producto_Detalle_Producto_medida_ProductoId',
            'Detalle_Producto_medida_ProductoId'
        );
    }
}
