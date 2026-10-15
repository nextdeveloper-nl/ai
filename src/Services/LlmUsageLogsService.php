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
        $upstreamCost = $result['upstream_cost'] ?? ($target->pricing ? LlmModelsService::calculateCost($target->pricing, $in, $out) : null);
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
