<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'Producto';
    protected $primaryKey = 'ProductoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $appends = [
        'detalleProductoMedidas',
    ];

    public function getDetalleProductoMedidasAttribute()
    {
        return $this->relationLoaded('detalleProductoMedidas')
            ? $this->getRelation('detalleProductoMedidas')
            : null;
    }

    protected $fillable = [
        'ProductoId',
        'ProductoNombre',
        'Producto_Categoria_ProductoId',
        'ProductoMarca',
        'producto_descripcion',
        'producto_imagen',
        'Producto_producto_ubi_id',
        'ProductoStockActual',
        'ProductoStockMinimo',
        'ProductoStockMaximo',
        'ProductoStockVirtual',
        'ProductoStockVirtualConsumido',
        'ProductoEstado',
        'ProductoEliminado',
        'ProductoUsuarioCreacion',
        'ProductoHostCreacion',
        'ProductoFechaCreacion',
        'ProductoUsuarioModificacion',
        'ProductoHostModificacion',
        'ProductoFechaModificacion',
        'ProductoUsuarioEliminacion',
        'ProductoHostEliminacion',
        'ProductoFechaEliminacion',
        'ProductoZona',
        'ProductoUbicacion',
    ];

    protected $casts = [
        'ProductoStockActual' => 'decimal:2',
        'ProductoStockMinimo' => 'decimal:2',
        'ProductoStockMaximo' => 'decimal:2',
        'ProductoStockVirtual' => 'decimal:2',
        'ProductoStockVirtualConsumido' => 'decimal:2',
        'ProductoFechaCreacion' => 'datetime',
        'ProductoFechaModificacion' => 'datetime',
        'ProductoFechaEliminacion' => 'datetime',
    ];

    /**
     * Stock virtual disponible para cubrir pedidos (Capacidad - Consumido).
     */
    public function getStockVirtualDisponibleAttribute(): float
    {
        $virtual = (float) ($this->ProductoStockVirtual ?? 0);
        $consumido = (float) ($this->ProductoStockVirtualConsumido ?? 0);
        return max(0.0, round($virtual - $consumido, 2));
    }

    /**
     * Stock total vendible (Stock Físico + Stock Virtual Disponible).
     */
    public function getStockTotalVendibleAttribute(): float
    {
        $fisico = max(0.0, (float) ($this->ProductoStockActual ?? 0));
        return round($fisico + $this->stock_virtual_disponible, 2);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'Producto_Categoria_ProductoId', 'Categoria_ProductoId');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(ProductoUbi::class, 'Producto_producto_ubi_id', 'producto_ubi_id');
    }

    public function ajustesInventario(): HasMany
    {
        return $this->hasMany(AjusteInventario::class, 'Ajuste_inventario_ProductoId', 'ProductoId');
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedidoProductos::class, 'Detalle_Pedido_Productos_ProductoId', 'ProductoId');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoProducto::class, 'Movimiento_producto_ProductoId', 'ProductoId');
    }

    public function detalleProductoMedidas(): HasMany
    {
        return $this->hasMany(DetalleProductoMedida::class, 'Detalle_Producto_medida_ProductoId', 'ProductoId')
            ->where('Detalle_Producto_medidaEliminado', 'N');
    }

    public function todasDetalleProductoMedidas(): HasMany
    {
        return $this->hasMany(DetalleProductoMedida::class, 'Detalle_Producto_medida_ProductoId', 'ProductoId');
    }

    public function productoProveedores(): HasMany
    {
        return $this->hasMany(ProductoProveedor::class, 'Producto_Proveedor_ProductoId', 'ProductoId');
    }

    public function proveedores(): BelongsToMany
    {
        return $this->belongsToMany(
            Proveedor::class,
            'Producto_Proveedor',
            'Producto_Proveedor_ProductoId',
            'Producto_Proveedor_ProveedorId'
        );
    }

    public function unidadesMedidas(): BelongsToMany
    {
        return $this->belongsToMany(
            UnidadesMedida::class,
            'Detalle_Producto_medida',
            'Detalle_Producto_medida_ProductoId',
            'Detalle_Producto_medida_unidades_medidaId'
        )->withPivot([
            'Detalle_Producto_medida_factor_conversion',
            'Detalle_Producto_medida_precio_venta',
            'Detalle_Producto_medida_precio_compra',
            'Detalle_Producto_medidaEliminado',
        ]);
    }
}
