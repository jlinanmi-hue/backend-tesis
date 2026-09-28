<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProducto extends Model
{
    use HasFactory;

    protected $table = 'Categoria_Producto';
    protected $primaryKey = 'Categoria_ProductoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Categoria_ProductoId',
        'Categoria_ProductoDescripcion_categoria',
        'Categoria_ProductoEstado',
        'Categoria_ProductoEliminado',
        'Categoria_ProductoUsuarioCreacion',
        'Categoria_ProductoHostCreacion',
        'Categoria_ProductoFechaCreacion',
        'Categoria_ProductoUsuarioModificacion',
        'Categoria_ProductoHostModificacion',
        'Categoria_ProductoFechaModificacion',
        'Categoria_ProductoUsuarioEliminacion',
        'Categoria_ProductoHostEliminacion',
        'Categoria_ProductoFechaEliminacion',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'Producto_Categoria_ProductoId', 'Categoria_ProductoId');
    }
}
