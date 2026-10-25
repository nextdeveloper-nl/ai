<?php

namespace NextDeveloper\AI\Services;

use NextDeveloper\AI\Database\Models\LlmUsageLogs;
use NextDeveloper\AI\Jobs\ChargeLlmUsageLogJob;
use NextDeveloper\AI\Services\AbstractServices\AbstractLlmUsageLogsService;
use NextDeveloper\AI\Services\Llm\LlmTarget;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * This class is responsible from managing the data for LlmUsageLogs
 *
 * Class LlmUsageLogsService.
 *
 * @package NextDeveloper\AI\Database\Models
 */
class LlmUsageLogsService extends AbstractLlmUsageLogsService
{
    /**
     * Writes one usage log row for a proxied call and queues billing when the call was served by a
     * platform provider. Account-owned (BYOK) providers are marked billed with zero cost.
     *
     * $result keys: upstream_cost, status, status_code, input_tokens, output_tokens, latency_ms, ttft_ms, stream, error_message
     */
    public static function record(LlmTarget $target, int $accountId, int $userId, ?int $apiKeyId, string $requestId, ?string $broadcastId, array $result): LlmUsageLogs
    {
        $in = (int) ($result['input_tokens'] ?? 0);
        $out = (int) ($result['output_tokens'] ?? 0);
        $success = ($result['status'] ?? 'failed') === 'success';

        // The cost LiteLLM reported for this very call is the most current price; the synced model prices are the fallback.
        $upstreamCost = $result['upstream_cost'] ?? ($target->pricing ? LlmModelsService::calculateCost($target->pricing, $in, $out, (int) ($result['cache_read_tokens'] ?? 0), (int) ($result['cache_write_tokens'] ?? 0)) : null);
        $billable = $target->isBilled() && $success;
        $cost = $billable && $upstreamCost !== null
            ? round($upstreamCost * (1 + ((float) config('ai.llm.markup_percent', 5)) / 100), 8)
            : 0;

        // The proxy runs on behalf of an API key without a role-checked user, so the write goes through admin context.
        $log = UserHelper::runAsAdmin(fn () => LlmUsageLogs::create([
            'request_id'         => $requestId,
            'broadcast_id'       => $broadcastId,
            'ai_llm_api_key_id'  => $apiKeyId,
            'ai_llm_provider_id' => $target->provider->id,
            'provider'           => $target->provider->slug,
            'model'              => $target->model,
            'requested_model'    => $target->requestedModel,
            'input_tokens'       => $in,
            'output_tokens'      => $out,
            'upstream_cost'      => $upstreamCost,
            'cost'               => $cost,
            // Nothing to charge for failed calls and BYOK providers.
            'billed'             => !$billable,
            'status'             => $result['status'] ?? 'failed',
            'status_code'        => $result['status_code'] ?? null,
            'latency_ms'         => $result['latency_ms'] ?? null,
            'ttft_ms'            => $result['ttft_ms'] ?? null,
            'stream'             => (bool) ($result['stream'] ?? false),
            'alias'              => $target->alias,
            'served_by_fallback' => (bool) ($result['served_by_fallback'] ?? false),
            'attempts'           => $result['attempts'] ?? null,
            'error_message'      => $result['error_message'] ?? null,
            'iam_account_id'     => $accountId,
            'iam_user_id'        => $userId,
        ]));

        if ($billable) {
            dispatch(new ChargeLlmUsageLogJob($log));
        }

        return $log;
    }

    /**
     * summary() for the account of the logged-in user.
     *
     * @return array<string, mixed>
     */
    public static function summaryForCurrentAccount(?string $from, ?string $to): array
    {
        LlmChatService::assertAiRole();

        return self::summary(UserHelper::currentAccount()->id, $from, $to);
    }

    /**
     * Totals and a per-day series by alias for the account, for the usage page.
     *
     * @return array{from: string, to: string, totals: array<string, mixed>, per_day: array<int, array<string, mixed>>}
     */
    public static function summary(int $accountId, ?string $from = null, ?string $to = null): array
    {
        $from = $from ? \Illuminate\Support\Carbon::parse($from, 'UTC')->startOfDay() : \NextDeveloper\AI\Services\Llm\LlmSpendLimitService::monthStart();
        $to = $to ? \Illuminate\Support\Carbon::parse($to, 'UTC')->endOfDay() : \Illuminate\Support\Carbon::now('UTC');

        $bindings = [$accountId, $from, $to];
        $where = 'iam_account_id = ? AND created_at >= ? AND created_at <= ? AND deleted_at IS NULL';

        $t = \Illuminate\Support\Facades\DB::selectOne("
            SELECT count(*) AS requests,
                   count(*) FILTER (WHERE status = 'success') AS success,
                   count(*) FILTER (WHERE status <> 'success') AS failed,
                   COALESCE(sum(input_tokens), 0) AS input_tokens,
                   COALESCE(sum(output_tokens), 0) AS output_tokens,
                   COALESCE(sum(cost), 0) AS cost,
                   COALESCE(sum(upstream_cost), 0) AS upstream_cost,
                   percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms) AS p50_latency_ms,
                   count(*) FILTER (WHERE served_by_fallback) AS fallback_count
            FROM ai_llm_usage_logs WHERE {$where}", $bindings);

        $days = \Illuminate\Support\Facades\DB::select("
            SELECT to_char(date_trunc('day', created_at AT TIME ZONE 'UTC'), 'YYYY-MM-DD') AS day,
                   COALESCE(alias, requested_model) AS alias,
                   COALESCE(sum(cost), 0) AS cost,
                   count(*) AS requests
            FROM ai_llm_usage_logs WHERE {$where}
            GROUP BY 1, 2 ORDER BY 1, 2", $bindings);

        $requests = (int) $t->requests;

        return [
            'from'    => $from->toIso8601String(),
            'to'      => $to->toIso8601String(),
            'totals'  => [
                'requests'        => $requests,
                'success'         => (int) $t->success,
                'failed'          => (int) $t->failed,
                'failed_percent'  => $requests ? round(100 * $t->failed / $requests, 2) : 0.0,
                'input_tokens'    => (int) $t->input_tokens,
                'output_tokens'   => (int) $t->output_tokens,
                'cost'            => round((float) $t->cost, 8),
                'upstream_cost'   => round((float) $t->upstream_cost, 8),
                'p50_latency_ms'  => $t->p50_latency_ms !== null ? (int) round($t->p50_latency_ms) : null,
                'fallback_count'  => (int) $t->fallback_count,
            ],
            'per_day' => array_map(fn ($d) => [
                'day' => $d->day, 'alias' => $d->alias, 'cost' => round((float) $d->cost, 8), 'requests' => (int) $d->requests,
            ], $days),
        ];
    }

    /**
     * Cost of successful calls whose charge job has not run yet. Subtracted from the credit balance when deciding
     * whether a new call may start, so a lagging queue cannot let an account overspend.
     */
    public static function pendingCost(int $accountId): float
    {
        return (float) LlmUsageLogs::withoutGlobalScopes()
            ->where('iam_account_id', $accountId)
            ->where('billed', false)
            ->where('status', 'success')
            ->sum('cost');
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
