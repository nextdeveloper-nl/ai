<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One change of an alias (created, updated, deleted). Written by LlmAliasesService, read-only for callers.
 */
class LlmAliasHistory extends Model
{
    public $table = 'ai_llm_alias_history';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];
}
