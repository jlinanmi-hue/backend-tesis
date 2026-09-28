<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'Empresa';
    protected $primaryKey = 'Id_Empresa';
    public $timestamps = false;

    protected $fillable = [
        'Id_Empresa',
        'EmpresaRuc',
        'EmpresaRazonSocial',
        'EmpresaNombreComercial',
        'EmpresaDireccion',
        'EmpresaDepartamento',
        'EmpresaProvincia',
        'EmpresaDistrito',
        'EmpresaUbigeo',
        'EmpresaTelefono',
        'EmpresaWhatsapp',
        'EmpresaEmail',
        'EmpresaLogo',
        'EmpresaMensajeTicket',
        'EmpresaEstado',
        'EmpresaUsuarioCreacion',
        'EmpresaHostCreacion',
        'EmpresaFechaCreacion',
        'EmpresaUsuarioModificacion',
        'EmpresaHostModificacion',
        'EmpresaFechaModificacion',
    ];

    protected $casts = [
        'Id_Empresa' => 'integer',
        'EmpresaFechaCreacion' => 'datetime',
        'EmpresaFechaModificacion' => 'datetime',
    ];

    /**
     * Obtener la instancia singleton de la empresa (Id_Empresa = 1)
     */
    public static function getDatos()
    {
        return static::first();
    }
}
