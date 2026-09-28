<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadesMedida extends Model
{
    use HasFactory;

    protected $table = 'unidades_medida';
    protected $primaryKey = 'unidades_medidaId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'unidades_medidaId',
        'unidades_medidaDescripcionUnidades',
        'unidades_medidaAbreviatura',
        'unidades_medidaEsBase',
        'unidades_medidaEstadoUnidades',
        'unidades_medidaEliminado',
        'unidades_medidaUsuarioCreacion',
        'unidades_medidaHostCreacion',
        'unidades_medidaFechaCreacion',
        'unidades_medidaUsuarioModificacion',
        'unidades_medidaHostModificacion',
        'unidades_medidaFechaModificacion',
        'unidades_medidaUsuarioEliminacion',
        'unidades_medidaHostEliminacion',
        'unidades_medidaFechaEliminacion',
    ];

    protected $casts = [
        'unidades_medidaEsBase' => 'boolean',
        'unidades_medidaFechaCreacion' => 'datetime',
        'unidades_medidaFechaModificacion' => 'datetime',
        'unidades_medidaFechaEliminacion' => 'datetime',
    ];

    public function detalleProductoMedidas(): HasMany
    {
        return $this->hasMany(DetalleProductoMedida::class, 'Detalle_Producto_medida_unidades_medidaId', 'unidades_medidaId');
    }
}
