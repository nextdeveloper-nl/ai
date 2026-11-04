<?php

namespace NextDeveloper\AI\Services;

use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Database\Models\LlmUsageLogs;
use NextDeveloper\AI\Services\AbstractServices\AbstractLlmAliasesService;
use NextDeveloper\AI\Services\Llm\LlmSpendLimitService;
use NextDeveloper\Commons\Exceptions\NotAllowedException;

/**
 * This class is responsible from managing the data for LlmAliases
 *
 * Class LlmAliasesService.
 *
 * @package NextDeveloper\AI\Database\Models
 */
class LlmAliasesService extends AbstractLlmAliasesService
{
    /**
     * Usage of one alias: last 24 hours and this month.
     *
     * @return array<string, mixed>
     */
    public static function stats(string $ref): array
    {
        $alias = self::getByRef($ref) ?? throw new NotAllowedException('Alias not found or not allowed.');

        $logs = fn () => LlmUsageLogs::withoutGlobalScopes()
            ->where('iam_account_id', $alias->iam_account_id)
            ->where(fn ($q) => $q->where('alias', $alias->alias)->orWhere(fn ($q2) => $q2->whereNull('alias')->where('requested_model', $alias->alias)));

        $day = $logs()->where('created_at', '>=', now()->subDay());

        return [
            'calls_24h'                => (clone $day)->count(),
            'failed_24h'               => (clone $day)->where('status', 'failed')->count(),
            'served_by_fallback_24h'   => (clone $day)->where('served_by_fallback', true)->count(),
            'api_keys_used_24h'        => (clone $day)->whereNotNull('ai_llm_api_key_id')->distinct()->count('ai_llm_api_key_id'),
            'spent_this_month'         => round(LlmSpendLimitService::spentThisMonth($alias->iam_account_id, $alias->alias), 8),
            'monthly_spend_limit'      => $alias->monthly_spend_limit !== null ? (float) $alias->monthly_spend_limit : null,
            'last_used_at'             => $logs()->max('created_at'),
        ];
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
