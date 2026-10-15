<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Account level monthly spend limit of the LLM proxy (one row per account).
 */
class LlmAccountLimits extends Model
{
    public $table = 'ai_llm_account_limits';

    protected $guarded = [];

    protected $casts = [
        'monthly_spend_limit' => 'float',
    ];
}
