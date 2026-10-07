<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZonaDelivery extends Model
{
    use HasFactory;

    protected $table = 'Zona_Delivery';
    protected $primaryKey = 'Zona_DeliveryId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Zona_DeliveryId',
        'Zona_DeliveryNombre',
        'Zona_DeliveryTarifa',
        'Zona_DeliveryPoligonoGeoJSON',
        'Zona_DeliveryEstado',
        'Zona_DeliveryUsuarioCreacion',
        'Zona_DeliveryHostCreacion',
        'Zona_DeliveryFechaCreacion',
        'Zona_DeliveryUsuarioModificacion',
        'Zona_DeliveryHostModificacion',
        'Zona_DeliveryFechaModificacion',
    ];

    protected $casts = [
        'Zona_DeliveryTarifa' => 'float',
        'Zona_DeliveryFechaCreacion' => 'datetime',
        'Zona_DeliveryFechaModificacion' => 'datetime',
    ];

    /**
     * Única relación foránea permitida: pedidos asociados a esta zona.
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'PedidoZonaDeliveryId', 'Zona_DeliveryId');
    }

    /**
     * Scope para consultar únicamente zonas activas.
     */
    public function scopeActivas($query)
    {
        return $query->where('Zona_DeliveryEstado', 'S');
    }

    /**
     * Determina si la zona se encuentra activa para delivery.
     */
    public function esActiva(): bool
    {
        return strtoupper((string) $this->Zona_DeliveryEstado) === 'S';
    }
}
