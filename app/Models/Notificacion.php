<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'Notificacion';
    protected $primaryKey = 'NotificacionId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'NotificacionId',
        'NotificacionTitulo',
        'NotificacionMensaje',
        'NotificacionTipo',
        'NotificacionCategoria',
        'NotificacionReferenciaId',
        'NotificacionDatos',
        'NotificacionLeida',
        'NotificacionSilenciosa',
        'NotificacionFecha',
        'NotificacionUsuarioId',
        'NotificacionEliminado',
    ];

    protected $casts = [
        'NotificacionDatos' => 'array',
        'NotificacionFecha' => 'datetime',
    ];

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('NotificacionEliminado', 'N');
    }

    public function scopeNoLeidas(Builder $query): Builder
    {
        return $query->where('NotificacionEliminado', 'N')
                     ->where('NotificacionLeida', 'N');
    }

    public function scopeParaUsuario(Builder $query, ?string $usuarioId = null): Builder
    {
        if (empty($usuarioId)) {
            return $query;
        }

        return $query->where(function ($q) use ($usuarioId) {
            $q->where('NotificacionUsuarioId', $usuarioId)
              ->orWhere('NotificacionUsuarioId', 'TODOS')
              ->orWhereNull('NotificacionUsuarioId');
        });
    }
}
