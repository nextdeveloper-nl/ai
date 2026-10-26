<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A chat kept on the server for one user. Plain model on purpose: access is scoped to the owner by
 * LlmChatHistoryService, not by the authorization scopes of the generated models.
 *
 * @property int $id
 * @property string $uuid
 * @property int $iam_user_id
 * @property int $iam_account_id
 * @property string|null $title
 * @property string|null $model
 * @property string|null $system_encrypted
 * @property float|null $temperature
 * @property int|null $max_tokens
 */
class LlmChats extends Model
{
    use SoftDeletes;

    protected $table = 'ai_llm_chats';

    protected $guarded = [];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens'  => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $chat) {
            $chat->uuid ??= (string) Str::uuid();
        });
    }
}
