<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'Pedido';
    protected $primaryKey = 'PedidoId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'PedidoId',
        'PedidoFecha_pedido',
        'PedidoTotal',
        'PedidoIgv',
        'PedidoUsuarioRegistro',
        'PedidoEstado_pedido',
        'PedidoAcuerdo_Comercial',
        'Pedido_ClienteId',
        'Pedido_canal_pedidoId',
        'PedidoEliminado',
        'PedidoUsuarioCreacion',
        'PedidoHostCreacion',
        'PedidoFechaCreacion',
        'PedidoUsuarioModificacion',
        'PedidoHostModificacion',
        'PedidoFechaModificacion',
        'PedidoUsuarioEliminacion',
        'PedidoHostEliminacion',
        'PedidoFechaEliminacion',
        'PedidoMotivoAnulacion',
        'PedidoTieneError',
        'PedidoTipoError',
        'PedidoOrigenIA',
        // INDICADOR 1: PODE
        'PedidoEstadoDespacho',
        'PedidoCausaFalloDespacho',
        'PedidoFechaInicioPreparacion',
        'PedidoFechaDespacho',
        'PedidoUsuarioDespacho',
        // INDICADOR 2: PEOR
        'PedidoOrigenError',
        'PedidoGravedadError',
        'PedidoOrigenDeteccionError',
        'PedidoMomentoDeteccionError',
        'PedidoCostoError',
        'PedidoScoreConfianzaIA',
        'PedidoTiempoCorreccionMin',
        'PedidoIdOriginal',
        // INDICADOR 4: TBPP
        'PedidoTiempoRegistroSeg',
        'PedidoTiempoEfectivoSeg',
        'PedidoTiempoSeleccionClienteMs',
        'PedidoTiempoCargaProductosMs',
        'PedidoTiempoValidacionStockMs',
        'PedidoTiempoConfirmacionPagoMs',
        'PedidoDispositivo',
        'PedidoTipoCliente',
        'PedidoIntentosCorreccion',
    ];

    protected $casts = [
        'PedidoFecha_pedido' => 'datetime',
        'PedidoTotal' => 'decimal:2',
        'PedidoIgv' => 'decimal:2',
        'PedidoFechaCreacion' => 'datetime',
        'PedidoFechaModificacion' => 'datetime',
        'PedidoFechaEliminacion' => 'datetime',
        'PedidoFechaInicioPreparacion' => 'datetime',
        'PedidoFechaDespacho' => 'datetime',
        'PedidoCostoError' => 'decimal:2',
        'PedidoScoreConfianzaIA' => 'decimal:2',
        'PedidoTiempoCorreccionMin' => 'integer',
        'PedidoTiempoRegistroSeg' => 'integer',
        'PedidoTiempoEfectivoSeg' => 'integer',
        'PedidoTiempoSeleccionClienteMs' => 'integer',
        'PedidoTiempoCargaProductosMs' => 'integer',
        'PedidoTiempoValidacionStockMs' => 'integer',
        'PedidoTiempoConfirmacionPagoMs' => 'integer',
        'PedidoIntentosCorreccion' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'Pedido_ClienteId', 'ClienteId');
    }

    public function canalPedido(): BelongsTo
    {
        return $this->belongsTo(CanalPedido::class, 'Pedido_canal_pedidoId', 'Canal_pedidoId');
    }

    public function detalles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DetallePedidoProductos::class, 'Detalle_Pedido_Productos_PedidoId', 'PedidoId')
            ->where('Detalle_Pedido_ProductosEliminado', 'N');
    }

    /**
     * Alias para compatibilidad hacia atrás
     */
    public function detallePedidoProductos(): HasOne
    {
        return $this->hasOne(DetallePedidoProductos::class, 'Detalle_Pedido_Productos_PedidoId', 'PedidoId');
    }

    public function roturasStock(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditoriaRoturaStock::class, 'pedido_id', 'PedidoId');
    }

    public function historialCorrecciones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PedidoHistorialCorreccion::class, 'pedido_id', 'PedidoId');
    }

    public function pedidoOriginal(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'PedidoIdOriginal', 'PedidoId');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('PedidoEstado_pedido', 'P');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('PedidoEstado_pedido', 'C');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('PedidoEstado_pedido', 'A');
    }

    public function scopeDelDia($query, ?string $fecha = null)
    {
        $fecha = $fecha ? \Carbon\Carbon::parse($fecha) : now();
        return $query->whereDate('PedidoFecha_pedido', $fecha->toDateString());
    }

    public function estaPendiente(): bool
    {
        return strtoupper(trim($this->PedidoEstado_pedido)) === 'P';
    }

    public function estaCompletada(): bool
    {
        return strtoupper(trim($this->PedidoEstado_pedido)) === 'C';
    }

    public function estaCancelada(): bool
    {
        return strtoupper(trim($this->PedidoEstado_pedido)) === 'A';
    }

    public function getNombreEstadoAttribute(): string
    {
        return match (strtoupper(trim($this->PedidoEstado_pedido))) {
            'P' => 'Pendiente',
            'C' => 'Completada',
            'A' => 'Cancelada / Anulada',
            default => 'Desconocido',
        };
    }

    public function getPedidoEstadoAttribute(): ?string
    {
        return $this->attributes['PedidoEstado_pedido'] ?? null;
    }
}
