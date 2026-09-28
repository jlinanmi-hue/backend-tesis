<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiIntentLog extends Model
{
    use HasFactory;

    protected $table = 'ai_intent_logs';

    protected $fillable = [
        'session_id',
        'user_id',
        'intent',
        'query',
        'complexity',
        'model_used',
        'tool_used',
        'tools_sent',
        'tokens_used',
        'gemini_ms',
        'tool_ms',
        'total_ms',
        'cache_hit',
        'success',
        'error_message',
    ];

    protected $casts = [
        'tools_sent'  => 'integer',
        'tokens_used' => 'integer',
        'gemini_ms'   => 'integer',
        'tool_ms'     => 'integer',
        'total_ms'    => 'integer',
        'cache_hit'   => 'boolean',
        'success'     => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}