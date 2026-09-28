<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoProveedor extends Model
{
    use HasFactory;

    protected $table = 'Producto_Proveedor';
    protected $primaryKey = 'Producto_Proveedor_ProveedorId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Producto_Proveedor_ProveedorId',
        'Producto_Proveedor_ProductoId',
        'Producto_ProveedorEliminado',
        'Producto_ProveedorUsuarioCreacion',
        'Producto_ProveedorHostCreacion',
        'Producto_ProveedorFechaCreacion',
        'Producto_ProveedorUsuarioModificacion',
        'Producto_ProveedorHostModificacion',
        'Producto_ProveedorFechaModificacion',
        'Producto_ProveedorUsuarioEliminacion',
        'Producto_ProveedorHostEliminacion',
        'Producto_ProveedorFechaEliminacion',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'Producto_Proveedor_ProveedorId', 'ProveedorId');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'Producto_Proveedor_ProductoId', 'ProductoId');
    }
}
