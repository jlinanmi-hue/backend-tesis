<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultaRuc extends Model
{
    use HasFactory;

    protected $table = 'Consulta_Ruc';
    protected $primaryKey = 'ConsultaRucId';
    public $timestamps = false;

    protected $fillable = [
        'ConsultaRucNumero',
        'ConsultaRucRazonSocial',
        'ConsultaRucEstado',
        'ConsultaRucCondicion',
        'ConsultaRucDireccion',
        'ConsultaRucDepartamento',
        'ConsultaRucProvincia',
        'ConsultaRucDistrito',
        'ConsultaRucUbigeo',
        'ConsultaRucTipoContribuyente',
        'ConsultaRucFechaInscripcion',
        'ConsultaRucActividadEconomica',
        'ConsultaRucTelefono',
        'ConsultaRucRawJson',
        'ConsultaRucVecesConsultado',
        'ConsultaRucUltimaConsulta',
        'ConsultaRucUsuarioCreacion',
        'ConsultaRucHostCreacion',
        'ConsultaRucFechaCreacion',
        'ConsultaRucUsuarioModificacion',
        'ConsultaRucHostModificacion',
        'ConsultaRucFechaModificacion',
    ];

    protected $casts = [
        'ConsultaRucVecesConsultado' => 'integer',
        'ConsultaRucUltimaConsulta' => 'datetime',
        'ConsultaRucFechaCreacion' => 'datetime',
        'ConsultaRucFechaModificacion' => 'datetime',
        'ConsultaRucRawJson' => 'array',
    ];
}
