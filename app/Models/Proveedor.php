<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'Proveedor';
    protected $primaryKey = 'ProveedorId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ProveedorId',
        'ProveedorRuc',
        'ProveedorRazonSocial',
        'ProveedorTipoContribuyente',
        'ProveedorEstado',
        'ProveedorActividadEconomica',
        'ProveedorTelefono',
        'ProveedorEliminado',
        'ProveedorUsuarioCreacion',
        'ProveedorHostCreacion',
        'ProveedorFechaCreacion',
        'ProveedorUsuarioModificacion',
        'ProveedorHostModificacion',
        'ProveedorFechaModificacion',
        'ProveedorUsuarioEliminacion',
        'ProveedorHostEliminacion',
        'ProveedorFechaEliminacion',
    ];

    public function productoProveedores(): HasMany
    {
        return $this->hasMany(ProductoProveedor::class, 'Producto_Proveedor_ProveedorId', 'ProveedorId');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            Producto::class,
            'Producto_Proveedor',
            'Producto_Proveedor_ProveedorId',
            'Producto_Proveedor_ProductoId'
        );
    }
}
