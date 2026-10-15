<?php

namespace NextDeveloper\AI\Services\Llm;

use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Str;
use NextDeveloper\Accounting\Helpers\AccountingHelper;
use NextDeveloper\Accounting\Helpers\CreditHelper;
use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Database\Models\LlmApiKeys;
use NextDeveloper\AI\Database\Models\LlmBroadcastGroups;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Database\Models\LlmUsageLogs;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\LlmModelsService;
use NextDeveloper\AI\Services\LlmUsageLogsService;

/**
 * Proxy for chat completions: resolves the target, calls the upstream provider through its driver,
 * records a usage log and (for platform providers) queues billing.
 *
 * All methods act on behalf of an API key; callers have no logged-in user.
 */
class LlmProxyService
{
    /**
     * Non-streaming chat completion. Returns [httpStatus, openAiBody, meta]; meta describes the usage log of this
     * call (see meta()).
     */
    public static function chat(array $payload, LlmApiKeys $key): array
    {
        $target = self::preflight($payload, $key);
        $payload['model'] = $target->model;

        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);

        try {
            $result = $target->driver()->chat($payload, $target->provider->base_url, self::upstreamKey($target), self::timeout());
        } catch (\Throwable $e) {
            self::log($target, $key, $requestId, null, ['status' => 'failed', 'status_code' => 502, 'latency_ms' => self::elapsed($startedAt), 'error_message' => $e->getMessage()]);

            throw new LlmProxyException('Upstream provider is unreachable.', 502, 'api_error', 'upstream_unreachable');
        }

        $log = self::log($target, $key, $requestId, null, self::toLogResult($result, self::elapsed($startedAt), false));

        return [$result['status'], $result['body'], self::meta($target, $log, $requestId)];
    }

    /**
     * Streaming chat completion. $emit receives complete SSE strings to write to the client.
     * Returns the meta of the usage log, or null when the upstream could not be reached.
     */
    public static function stream(array $payload, LlmApiKeys $key, callable $emit): ?array
    {
        $target = self::preflight($payload, $key);
        $payload['model'] = $target->model;

        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);

        try {
            $result = $target->driver()->stream($payload, $target->provider->base_url, self::upstreamKey($target), self::timeout(), $emit);
        } catch (\Throwable $e) {
            self::log($target, $key, $requestId, null, ['status' => 'failed', 'status_code' => 502, 'latency_ms' => self::elapsed($startedAt), 'stream' => true, 'error_message' => $e->getMessage()]);
            $emit('data: ' . json_encode((new LlmProxyException('Upstream provider is unreachable.', 502, 'api_error', 'upstream_unreachable'))->toArray()) . "\n\n");

            return null;
        }

        $log = self::log($target, $key, $requestId, null, [
            'status'        => $result['status'] < 400 ? 'success' : 'failed',
            'status_code'   => $result['status'],
            'input_tokens'  => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'latency_ms'    => self::elapsed($startedAt),
            'ttft_ms'       => $result['ttft_ms'],
            'upstream_cost' => $result['upstream_cost'] ?? null,
            'stream'        => true,
        ]);

        return self::meta($target, $log, $requestId);
    }

    /**
     * What a caller needs to show and join a call: request_id matches ai_llm_usage_logs.request_id; cost is what the
     * account is charged (markup included, 0 when not billed or failed).
     *
     * @return array{request_id: string, alias: ?string, requested_model: string, provider: string, model: string, input_tokens: int, output_tokens: int, cost: float, latency_ms: ?int, ttft_ms: ?int, status: string}
     */
    private static function meta(LlmTarget $target, LlmUsageLogs $log, string $requestId): array
    {
        return [
            'request_id'      => $requestId,
            'alias'           => $target->alias,
            'requested_model' => $target->requestedModel,
            'provider'        => $target->provider->slug,
            'model'           => $target->model,
            'input_tokens'    => (int) $log->input_tokens,
            'output_tokens'   => (int) $log->output_tokens,
            'cost'            => (float) $log->cost,
            'latency_ms'      => $log->latency_ms,
            'ttft_ms'         => $log->ttft_ms,
            'status'          => $log->status,
        ];
    }

    /**
     * Fans one request out to every target of a broadcast group, concurrently.
     * Returns one entry per target: provider/model + status + body (or error).
     * Mode "first" returns only the first successful answer (all calls are still made and metered).
     */
    public static function broadcast(string $groupName, array $payload, LlmApiKeys $key): array
    {
        $group = LlmBroadcastGroups::withoutGlobalScopes()
            ->where('iam_account_id', $key->iam_account_id)
            ->where('name', $groupName)
            ->where('is_active', true)
            ->first();

        if (!$group) {
            throw new LlmProxyException("Broadcast group '{$groupName}' does not exist.", 404, 'invalid_request_error', 'broadcast_group_not_found');
        }

        $broadcastId = (string) Str::uuid();
        $timeout = max(1, (int) ceil($group->timeout_ms / 1000));
        $promises = [];
        $targets = [];
        $reserved = 0.0;

        foreach ($group->targets as $i => $spec) {
            $requested = isset($spec['alias']) ? $spec['alias'] : (($spec['provider'] ?? '') . '/' . ($spec['model'] ?? ''));

            try {
                $target = ModelResolver::resolve($requested, $key->iam_account_id);
                $reserved += self::assertCredit($target, $key, $payload, $reserved);
            } catch (LlmProxyException $e) {
                // A broken target must not take the whole broadcast down; report it in place.
                $targets[$i] = ['requested' => $requested, 'error' => $e->toArray()['error']];
                continue;
            }

            $targets[$i] = ['requested' => $requested, 'target' => $target, 'started_at' => microtime(true)];
            $promises[$i] = $target->driver()->chatAsync(
                array_merge($payload, ['model' => $target->model, 'stream' => false]),
                $target->provider->base_url,
                self::upstreamKey($target),
                $timeout
            );
        }

        // Nothing can run because the account has no credit: say so with 402 instead of a 200 full of failures.
        if (!$promises && $targets && collect($targets)->every(fn ($t) => ($t['error']['code'] ?? null) === 'insufficient_credit')) {
            throw new LlmProxyException('Insufficient credit.', 402, 'insufficient_quota', 'insufficient_credit');
        }

        // settle() never throws: every promise resolves to ['state' => fulfilled|rejected, ...].
        $settled = Utils::settle($promises)->wait();
        $responses = [];

        foreach ($targets as $i => $entry) {
            if (isset($entry['error'])) {
                $responses[] = ['requested' => $entry['requested'], 'status' => 'failed', 'error' => $entry['error']];
                continue;
            }

            /** @var LlmTarget $target */
            $target = $entry['target'];
            $outcome = $settled[$i];
            $latency = self::elapsed($entry['started_at']);

            if ($outcome['state'] === 'fulfilled') {
                $result = $outcome['value'];
                self::log($target, $key, (string) Str::uuid(), $broadcastId, self::toLogResult($result, $latency, false));
                $responses[] = [
                    'requested'   => $entry['requested'],
                    'provider'    => $target->provider->slug,
                    'model'       => $target->model,
                    'status'      => $result['status'] < 400 ? 'success' : 'failed',
                    'status_code' => $result['status'],
                    'response'    => $result['body'],
                ];
            } else {
                $message = $outcome['reason'] instanceof \Throwable ? $outcome['reason']->getMessage() : 'upstream error';
                self::log($target, $key, (string) Str::uuid(), $broadcastId, ['status' => 'failed', 'status_code' => 502, 'latency_ms' => $latency, 'error_message' => $message]);
                $responses[] = ['requested' => $entry['requested'], 'provider' => $target->provider->slug, 'model' => $target->model, 'status' => 'failed', 'error' => ['message' => 'Upstream provider timed out or is unreachable.']];
            }
        }

        if ($group->mode === 'first') {
            $first = collect($responses)->firstWhere('status', 'success');
            $responses = $first ? [$first] : $responses;
        }

        return ['broadcast_id' => $broadcastId, 'group' => $group->name, 'responses' => $responses];
    }

    /**
     * OpenAI-style model list for the key's account: its active aliases plus every usable "provider/model".
     */
    public static function listModels(LlmApiKeys $key): array
    {
        // Same visibility rule as ModelResolver: account-owned (BYOK) providers only while ai.llm.allow_byok is on.
        $providers = LlmProviders::withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($q) use ($key) {
                $q->whereNull('iam_account_id');

                if (config('ai.llm.allow_byok', false)) {
                    $q->orWhere('iam_account_id', $key->iam_account_id);
                }
            })
            ->get(['id', 'slug']);

        // Only advertise aliases that currently resolve to an available provider.
        $aliases = LlmAliases::withoutGlobalScopes()
            ->where('iam_account_id', $key->iam_account_id)
            ->where('is_active', true)
            ->whereIn('target_provider', $providers->pluck('slug'))
            ->pluck('alias');

        $slugs = $providers->pluck('slug', 'id');

        // Platform calls are billed with the cost the upstream reports per call, so unpriced models are listed too.
        $models = LlmModels::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereIn('ai_llm_provider_id', $providers->pluck('id'))
            ->get(['ai_llm_provider_id', 'model_id'])
            ->map(fn ($m) => $slugs[$m->ai_llm_provider_id] . '/' . $m->model_id);

        return [
            'object' => 'list',
            'data'   => $aliases->merge($models)->unique()->values()
                ->map(fn ($id) => ['id' => $id, 'object' => 'model', 'owned_by' => 'plusclouds'])->all(),
        ];
    }

    /**
     * Resolves the target and runs the pre-flight checks shared by chat and stream.
     */
    public static function preflight(array $payload, LlmApiKeys $key): LlmTarget
    {
        if (empty($payload['model']) || empty($payload['messages'])) {
            throw new LlmProxyException("'model' and 'messages' are required.", 400, 'invalid_request_error', 'invalid_request');
        }

        $target = ModelResolver::resolve((string) $payload['model'], $key->iam_account_id);
        self::assertCredit($target, $key, $payload);

        return $target;
    }

    /**
     * Platform providers are billed after the fact (queue job right after the call), so refuse accounts that
     * cannot cover the call. Calls still waiting for their charge job count against the balance already, and
     * $reserved carries the estimated cost of the other targets of the same broadcast, so a broadcast has to be
     * affordable as a whole.
     *
     * @return float estimated cost of this call (0 for providers that are not billed)
     */
    private static function assertCredit(LlmTarget $target, LlmApiKeys $key, array $payload = [], float $reserved = 0.0): float
    {
        if (!$target->isBilled()) {
            return 0.0;
        }

        $account = AccountingHelper::getAccountFromIamAccountId($key->iam_account_id);
        $estimate = self::estimateCost($target, $payload);

        $available = $account ? CreditHelper::getCredit($account) - LlmUsageLogsService::pendingCost($key->iam_account_id) - $reserved : 0;

        if (!$account || $available <= 0 || $available < $estimate) {
            throw new LlmProxyException('Insufficient credit.', 402, 'insufficient_quota', 'insufficient_credit');
        }

        return $estimate;
    }

    /**
     * Worst-case charge for a call, markup included: the prompt size plus max_tokens at the model price, or, for
     * models without a synced price, the average charge of the latest successful calls of that model.
     */
    private static function estimateCost(LlmTarget $target, array $payload): float
    {
        $markup = 1 + ((float) config('ai.llm.markup_percent', 5)) / 100;
        $pricing = $target->pricing;

        if ($pricing && $pricing->input_price_per_1m !== null && $pricing->output_price_per_1m !== null) {
            $inputTokens = (int) ceil(strlen(json_encode($payload['messages'] ?? [])) / 4);
            $outputTokens = (int) ($payload['max_tokens'] ?? 1024);

            return round(LlmModelsService::calculateCost($pricing, $inputTokens, $outputTokens) * $markup, 8);
        }

        return (float) LlmUsageLogs::withoutGlobalScopes()
            ->where('ai_llm_provider_id', $target->provider->id)
            ->where('model', $target->model)
            ->where('status', 'success')
            ->where('cost', '>', 0)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['cost'])
            ->avg('cost');
    }

    private static function upstreamKey(LlmTarget $target): string
    {
        return $target->provider->api_key_encrypted ? LlmKeyHelper::decrypt($target->provider->api_key_encrypted) : '';
    }

    private static function timeout(): int
    {
        return (int) config('ai.llm.timeout', 120);
    }

    private static function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private static function toLogResult(array $result, int $latency, bool $stream): array
    {
        $ok = $result['status'] < 400;

        return [
            'status'        => $ok ? 'success' : 'failed',
            'status_code'   => $result['status'],
            'input_tokens'  => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'upstream_cost' => $result['upstream_cost'] ?? null,
            'latency_ms'    => $latency,
            'stream'        => $stream,
            'error_message' => $ok ? null : ($result['body']['error']['message'] ?? null),
        ];
    }

    private static function log(LlmTarget $target, LlmApiKeys $key, string $requestId, ?string $broadcastId, array $result): LlmUsageLogs
    {
        return LlmUsageLogsService::record($target, $key->iam_account_id, $key->iam_user_id, $key->id, $requestId, $broadcastId, $result);
    }
}
