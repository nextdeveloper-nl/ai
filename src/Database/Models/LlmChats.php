<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A chat kept on the server for one user in one account. Plain model on purpose: access is scoped to the owner by
 * scopeOwnedBy(), which every LlmChatHistoryService query goes through, not by the authorization scopes of the
 * generated models.
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

    /**
     * A chat belongs to the user who made it inside the account they made it in: the same user working in
     * another account does not see it.
     *
     * @param array{user: int, account: int} $owner
     */
    public function scopeOwnedBy(Builder $query, array $owner): Builder
    {
        return $query->where('ai_llm_chats.iam_user_id', $owner['user'])
            ->where('ai_llm_chats.iam_account_id', $owner['account']);
    }

    protected static function booted(): void
    {
        static::creating(function (self $chat) {
            $chat->uuid ??= (string) Str::uuid();
        });
    }
}
