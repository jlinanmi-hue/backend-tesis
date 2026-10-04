<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaRoturaStock extends Model
{
    use HasFactory;

    protected $table = 'auditoria_roturas_stock';
    public $timestamps = false;

    protected $fillable = [
        'pedido_id',
        'cantidad_solicitada',
        'cantidad_disponible_fisica',
        'deficit_unidades',
        'tipo_rotura',
        'rompe_stock_seguridad',
        'categoria_id',
        'usuario',
        'created_at',
    ];

    protected $casts = [
        'cantidad_solicitada' => 'decimal:2',
        'cantidad_disponible_fisica' => 'decimal:2',
        'deficit_unidades' => 'decimal:2',
        'rompe_stock_seguridad' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'PedidoId');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id', 'Categoria_ProductoId');
    }
}
