<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationFeedback extends Model
{
    use HasFactory;

    protected $table = 'ai_generation_feedback';

    protected $fillable = [
        'session_id',
        'message_id',
        'tool_log_id',
        'tool_name',
        'pedido_id',
        'tipo_operacion',
        'rating',
        'es_correcto',
        'motivo_dislike',
        'comentario',
        'user_id',
    ];

    protected $casts = [
        'es_correcto' => 'boolean',
        'tool_log_id' => 'integer',
        'user_id'     => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function toolLog(): BelongsTo
    {
        return $this->belongsTo(AiToolLog::class, 'tool_log_id');
    }
}
