<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One message of a chat. Text and reasoning are stored encrypted; meta only holds whitelisted fields.
 *
 * @property int $id
 * @property string $uuid
 * @property int $ai_llm_chat_id
 * @property string $role
 * @property string $content_encrypted
 * @property string|null $reasoning_encrypted
 * @property array|null $meta
 * @property int $position
 * @property int $content_bytes
 */
class LlmChatMessages extends Model
{
    public $timestamps = false;

    protected $table = 'ai_llm_chat_messages';

    protected $guarded = [];

    protected $casts = [
        'meta'     => 'array',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message) {
            $message->uuid ??= (string) Str::uuid();
            $message->created_at ??= now();
        });
    }
}
