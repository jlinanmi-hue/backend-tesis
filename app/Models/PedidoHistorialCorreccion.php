<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoHistorialCorreccion extends Model
{
    use HasFactory;

    protected $table = 'pedido_historial_correccion';
    public $timestamps = false;

    protected $fillable = [
        'pedido_id',
        'campo_modificado',
        'valor_anterior',
        'valor_nuevo',
        'motivo',
        'usuario',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'PedidoId');
    }
}
