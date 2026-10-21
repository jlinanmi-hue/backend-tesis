<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiVisionLog extends Model
{
    protected $table = 'ai_vision_logs';

    protected $fillable = [
        'token_auditoria',
        'pedido_id',
        'usuario_id',
        'tokens_consumidos',
        'tiempo_procesamiento_ms',
        'items_detectados_ia',
        'items_guardados_final',
        'total_items_ia',
        'items_validados_sin_cambio',
        'items_modificados',
        'items_eliminados',
        'items_agregados',
        'tasa_precision_pct',
        'tiempo_revision_humana_seg',
    ];

    protected $casts = [
        'items_detectados_ia'        => 'array',
        'items_guardados_final'      => 'array',
        'tasa_precision_pct'         => 'float',
        'tokens_consumidos'          => 'integer',
        'tiempo_procesamiento_ms'    => 'integer',
        'total_items_ia'             => 'integer',
        'items_validados_sin_cambio' => 'integer',
        'items_modificados'          => 'integer',
        'items_eliminados'           => 'integer',
        'items_agregados'            => 'integer',
        'tiempo_revision_humana_seg' => 'integer',
    ];
}
