<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedidoProductos extends Model
{
    use HasFactory;

    protected $table = 'Detalle_Pedido_Productos';
    protected $primaryKey = 'Detalle_Pedido_ProductosId';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Detalle_Pedido_ProductosId',
        'Detalle_Pedido_Productos_PedidoId',
        'Detalle_Pedido_Productos_ProductoId',
        'Detalle_Pedido_Productos_unidades_medidaId',
        'Detalle_Pedido_Productos_factor_conversion',
        'Detalle_Pedido_Productos_cantidad',
        'Detalle_Pedido_Productos_cantidad_base',
        'Detalle_Pedido_Productos_cantidad_fisica',
        'Detalle_Pedido_Productos_precio_unitario_venta',
        'Detalle_Pedido_Productos_subtotal',
        'Detalle_Pedido_ProductosTuvoRotura',
        'Detalle_Pedido_ProductosCantidadFaltanteRotura',
        'Detalle_Pedido_ProductosEliminado',
        'Detalle_Pedido_ProductosUsuarioCreacion',
        'Detalle_Pedido_ProductosHostCreacion',
        'Detalle_Pedido_ProductosFechaCreacion',
        'Detalle_Pedido_ProductosUsuarioModificacion',
        'Detalle_Pedido_ProductosHostModificacion',
        'Detalle_Pedido_ProductosFechaModificacion',
        'Detalle_Pedido_ProductosUsuarioEliminacion',
        'Detalle_Pedido_ProductosHostEliminacion',
        'Detalle_Pedido_ProductosFechaEliminacion',
    ];

    protected $casts = [
        'Detalle_Pedido_Productos_factor_conversion' => 'decimal:2',
        'Detalle_Pedido_Productos_cantidad' => 'decimal:2',
        'Detalle_Pedido_Productos_cantidad_base' => 'decimal:2',
        'Detalle_Pedido_Productos_cantidad_fisica' => 'decimal:2',
        'Detalle_Pedido_Productos_precio_unitario_venta' => 'decimal:2',
        'Detalle_Pedido_Productos_subtotal' => 'decimal:2',
        'Detalle_Pedido_ProductosCantidadFaltanteRotura' => 'decimal:2',
        'Detalle_Pedido_ProductosFechaCreacion' => 'datetime',
        'Detalle_Pedido_ProductosFechaModificacion' => 'datetime',
        'Detalle_Pedido_ProductosFechaEliminacion' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'Detalle_Pedido_Productos_PedidoId', 'PedidoId');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'Detalle_Pedido_Productos_ProductoId', 'ProductoId');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadesMedida::class, 'Detalle_Pedido_Productos_unidades_medidaId', 'unidades_medidaId');
    }
}
