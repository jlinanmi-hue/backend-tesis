<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleRoturaStock extends Model
{
    use HasFactory;

    protected $table = 'detalle_rotura_stock';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'pedido_id',
        'detalle_pedido_id',
        'producto_id',
        'orden_compra_codigo',
        'cantidad_solicitada',
        'cantidad_disponible_momento',
        'cantidad_faltante',
        'tipo_rotura', // 'INTENTO' o 'CONFIRMADA'
        'fecha_hora',
        'usuario_id',
        'observaciones',
    ];

    protected $casts = [
        'cantidad_solicitada' => 'decimal:2',
        'cantidad_disponible_momento' => 'decimal:2',
        'cantidad_faltante' => 'decimal:2',
        'fecha_hora' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el Producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'ProductoId');
    }

    /**
     * Relación con el Pedido
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'PedidoId');
    }

    /**
     * Relación con el Detalle del Pedido
     */
    public function detallePedido(): BelongsTo
    {
        return $this->belongsTo(DetallePedidoProductos::class, 'detalle_pedido_id', 'Detalle_Pedido_ProductosId');
    }

    /**
     * Scope para filtrar por tipo de rotura ('INTENTO' o 'CONFIRMADA')
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_rotura', strtoupper(trim($tipo)));
    }

    /**
     * Scope solo roturas confirmadas (usado para métricas oficiales de PRS)
     */
    public function scopeConfirmadas($query)
    {
        return $query->where('tipo_rotura', 'CONFIRMADA');
    }

    /**
     * Scope solo intentos de rotura (telemetría de demanda no satisfecha)
     */
    public function scopeIntentos($query)
    {
        return $query->where('tipo_rotura', 'INTENTO');
    }

    /**
     * Scope por rango de fechas
     */
    public function scopeEntreFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_hora', [$desde, $hasta]);
    }
}
