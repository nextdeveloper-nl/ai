<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user choice to keep the chat history on the server (default: no).
 *
 * @property int $iam_user_id
 * @property int $iam_account_id
 * @property bool $keep_history
 */
class LlmChatSettings extends Model
{
    protected $table = 'ai_llm_chat_settings';

    protected $guarded = [];

    protected $casts = ['keep_history' => 'boolean'];
}
