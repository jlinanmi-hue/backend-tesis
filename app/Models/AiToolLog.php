<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolLog extends Model
{
    use HasFactory;

    protected $table = 'ai_tool_logs';

    protected $fillable = [
        'user_id',
        'session_id',
        'tool_name',
        'pedido_id',
        'args',
        'result',
        'status',
        'duration_ms',
        'prompt_tokens',
        'candidates_tokens',
        'total_tokens',
        'cost_usd',
        'error_message',
        'feedback',
        'motivo_dislike',
        'feedback_at',
    ];

    protected $casts = [
        'args' => 'array',
        'result' => 'array',
        'duration_ms' => 'integer',
        'prompt_tokens' => 'integer',
        'candidates_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost_usd' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'PedidoId');
    }
}
