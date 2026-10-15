<?php

namespace NextDeveloper\AI\Services\Llm;

use Illuminate\Support\Carbon;
use NextDeveloper\AI\Database\Models\LlmAccountLimits;
use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Database\Models\LlmUsageLogs;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Monthly spend limits of the LLM proxy, per account and per alias. The month is the calendar month in UTC.
 * Spend is the charged cost of the usage logs, so calls that are still waiting for their charge job count already.
 */
class LlmSpendLimitService
{
    public static function monthStart(): Carbon
    {
        return Carbon::now('UTC')->startOfMonth();
    }

    /**
     * Charged cost of the month so far; for one alias when $alias is given.
     */
    public static function spentThisMonth(int $accountId, ?string $alias = null): float
    {
        $query = LlmUsageLogs::withoutGlobalScopes()
            ->where('iam_account_id', $accountId)
            ->where('status', 'success')
            ->where('created_at', '>=', self::monthStart());

        if ($alias !== null) {
            // Logs written before the alias column existed carry the alias only in requested_model.
            $query->where(fn ($q) => $q->where('alias', $alias)->orWhere(fn ($q2) => $q2->whereNull('alias')->where('requested_model', $alias)));
        }

        return (float) $query->sum('cost');
    }

    public static function accountLimit(int $accountId): ?float
    {
        $limit = LlmAccountLimits::where('iam_account_id', $accountId)->value('monthly_spend_limit');

        return $limit === null ? null : (float) $limit;
    }

    /**
     * @return array{monthly_spend_limit: ?float, spent_this_month: float, remaining: ?float, month_start: string}
     */
    public static function status(int $accountId): array
    {
        $limit = self::accountLimit($accountId);
        $spent = self::spentThisMonth($accountId);

        return [
            'monthly_spend_limit' => $limit,
            'spent_this_month'    => round($spent, 8),
            'remaining'           => $limit === null ? null : round(max(0, $limit - $spent), 8),
            'month_start'         => self::monthStart()->toIso8601String(),
        ];
    }

    /**
     * Sets (or clears with null) the account limit. Changing it is a manager/admin job.
     *
     * @throws NotAllowedException
     */
    public static function setAccountLimit(?float $limit): array
    {
        throw_if(
            !UserHelper::hasRole('ai-manager') && !UserHelper::hasRole('ai-admin'),
            new NotAllowedException('You need the ai-manager role to change the spend limit.')
        );

        $accountId = UserHelper::currentAccount()->id;

        LlmAccountLimits::updateOrCreate(
            ['iam_account_id' => $accountId],
            ['monthly_spend_limit' => $limit, 'iam_user_id' => UserHelper::me()->id]
        );

        return self::status($accountId);
    }

    /**
     * Refuses the call when it would take the alias or the account over its monthly limit.
     *
     * @throws LlmProxyException 429 monthly_spend_limit_reached
     */
    public static function assertWithin(int $accountId, ?LlmAliases $alias, float $estimate = 0.0): void
    {
        if ($alias && $alias->monthly_spend_limit !== null
            && self::spentThisMonth($accountId, $alias->alias) + $estimate > (float) $alias->monthly_spend_limit) {
            throw new LlmProxyException(
                "The monthly spend limit of the alias '{$alias->alias}' is reached.",
                429, 'insufficient_quota', 'monthly_spend_limit_reached'
            );
        }

        $limit = self::accountLimit($accountId);

        if ($limit !== null && self::spentThisMonth($accountId) + $estimate > $limit) {
            throw new LlmProxyException('The monthly spend limit of the account is reached.', 429, 'insufficient_quota', 'monthly_spend_limit_reached');
        }
    }
}
