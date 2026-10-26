<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The storage fee of one account for one hour. The unique (account, hour) key makes the hourly job safe to repeat.
 */
class LlmChatStorageCharges extends Model
{
    public $timestamps = false;

    protected $table = 'ai_llm_chat_storage_charges';

    protected $guarded = [];

    protected $casts = ['billed' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (self $charge) {
            $charge->created_at ??= now();
        });
    }
}
