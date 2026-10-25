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
     * call (see meta()). When the alias has fallbacks, the next target answers if the previous one fails in a way
     * the alias is set to fall back on.
     */
    public static function chat(array $payload, LlmApiKeys $key): array
    {
        $primary = self::preflight($payload, $key);
        $chain = self::chain($primary, $key, $payload);
        $requestId = (string) Str::uuid();
        $attempts = [];

        foreach ($chain as $i => $target) {
            $isLast = $i === array_key_last($chain);
            $startedAt = microtime(true);
            $result = null;
            $error = null;

            try {
                $result = $target->driver()->chat(
                    self::prepare($payload, $target), $target->provider->base_url, self::upstreamKey($target), self::attemptTimeout($primary, $isLast)
                );
                $failure = self::failureKind($result['status'], null);
            } catch (\Throwable $e) {
                $error = $e;
                $failure = self::failureKind(null, $e);
            }

            $attempts[] = self::attempt($target, $result['status'] ?? null, $failure ? ($error?->getMessage() ?? ($result['body']['error']['message'] ?? 'upstream error')) : null, self::elapsed($startedAt));

            if ($failure && !$isLast && self::shouldFallBack($primary, $failure)) {
                continue;
            }

            $served = $target->servingFor($primary);
            $extra = ['attempts' => $attempts, 'served_by_fallback' => $i > 0];

            if ($error) {
                self::log($served, $key, $requestId, null, $extra + ['status' => 'failed', 'status_code' => 502, 'latency_ms' => self::elapsed($startedAt), 'error_message' => $error->getMessage()]);

                throw new LlmProxyException('Upstream provider is unreachable.', 502, 'api_error', 'upstream_unreachable');
            }

            $log = self::log($served, $key, $requestId, null, $extra + self::toLogResult($result, self::elapsed($startedAt), false));

            [$status, $body] = self::publicAnswer($result['status'], $result['body'], $primary);

            return [$status, $body, self::meta($served, $log, $requestId)];
        }

        throw new LlmProxyException('Upstream provider is unreachable.', 502, 'api_error', 'upstream_unreachable');
    }

    /**
     * What the caller gets to see of an upstream answer: the model it asked for instead of the upstream's own id,
     * and for failures a generic error, since upstream messages can name the provider.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function publicAnswer(int $status, array $body, LlmTarget $primary): array
    {
        if ($status < 400) {
            if (isset($body['model'])) {
                $body['model'] = $primary->requestedModel;
            }

            return [$status, $body];
        }

        [$status, $message, $type, $code] = match (true) {
            $status === 429                                  => [429, 'Rate limit reached. Try again shortly.', 'rate_limit_error', 'rate_limit_exceeded'],
            in_array($status, [400, 404, 413, 422], true)    => [400, 'The request was rejected by the model. Check the request parameters.', 'invalid_request_error', 'request_rejected'],
            default                                          => [502, 'The model is temporarily unavailable.', 'api_error', 'upstream_error'],
        };

        return [$status, ['error' => ['message' => $message, 'type' => $type, 'code' => $code]]];
    }

    /**
     * Same as publicAnswer() for one SSE event of a stream. Every data line is checked: a line that is not
     * JSON (an upstream error page, say) is replaced by a generic error instead of being passed on, so nothing
     * unparsed gets through to the caller.
     */
    private static function publicChunk(string $chunk, LlmTarget $primary): string
    {
        $lines = explode("\n", rtrim($chunk, "\n"));

        foreach ($lines as $i => $line) {
            if (!str_starts_with($line, 'data:')) {
                // Only SSE fields and comments pass; any other line is upstream text that came with no field
                // (the rest of a multi-line page, say), so the whole event is replaced.
                if ($line === '' || preg_match('/^(event|id|retry):[^\r\n]*$/', $line) || str_starts_with($line, ':')) {
                    continue;
                }

                return 'data: ' . json_encode(self::publicAnswer(502, [], $primary)[1]) . "\n\n";
            }

            $data = trim(substr($line, 5));

            if ($data === '[DONE]') {
                continue;
            }

            $json = json_decode($data, true);

            if (!is_array($json)) {
                $lines[$i] = 'data: ' . json_encode(self::publicAnswer(502, [], $primary)[1]);

                continue;
            }

            if (isset($json['error'])) {
                $code = $json['error']['code'] ?? $json['error']['status'] ?? null;
                $json = self::publicAnswer(is_numeric($code) ? (int) $code : 502, $json, $primary)[1];
            } elseif (isset($json['model'])) {
                $json['model'] = $primary->requestedModel;
            }

            $lines[$i] = 'data: ' . json_encode($json);
        }

        return implode("\n", $lines) . "\n\n";
    }

    /**
     * The usage-log meta as a customer may see it: the requested model instead of provider and upstream model,
     * and the attempts without who answered or what it said. meta() itself stays complete for in-process callers.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function publicMeta(array $meta): array
    {
        unset($meta['provider']);

        $meta['model'] = $meta['requested_model'] ?? $meta['model'] ?? null;
        $meta['attempts'] = array_map(
            fn ($attempt) => array_intersect_key((array) $attempt, array_flip(['status', 'status_code', 'latency_ms'])),
            (array) ($meta['attempts'] ?? [])
        );

        return $meta;
    }

    /**
     * Streaming chat completion. $emit receives complete SSE strings to write to the client.
     * Returns the meta of the usage log, or null when the upstream could not be reached.
     *
     * Fallbacks only work until the first content byte went to the client: an error answer or a connection failure
     * before that moves on to the next target, a stream that already started cannot be taken back.
     */
    public static function stream(array $payload, LlmApiKeys $key, callable $emit): ?array
    {
        $primary = self::preflight($payload, $key);
        $emitRaw = $emit;
        $emit = fn (string $chunk) => $emitRaw(self::publicChunk($chunk, $primary));
        $chain = self::chain($primary, $key, $payload);
        $requestId = (string) Str::uuid();
        $attempts = [];

        foreach ($chain as $i => $target) {
            $isLast = $i === array_key_last($chain);
            $startedAt = microtime(true);
            $committed = false;
            $held = [];

            // Until the first non-error chunk, error answers are held back so a fallback can replace them.
            $gate = function (string $chunk) use (&$committed, &$held, $emit, $isLast): void {
                if ($isLast || $committed) {
                    $committed = true;
                    $emit($chunk);

                    return;
                }

                if (self::isErrorEvent($chunk)) {
                    $held[] = $chunk;

                    return;
                }

                $committed = true;
                $emit($chunk);
            };

            $served = $target->servingFor($primary);

            try {
                $result = $target->driver()->stream(self::prepare($payload, $target), $target->provider->base_url, self::upstreamKey($target), self::timeout(), $gate);
            } catch (\Throwable $e) {
                $failure = self::failureKind(null, $e);
                $attempts[] = self::attempt($target, null, $e->getMessage(), self::elapsed($startedAt));

                if (!$committed && !$isLast && self::shouldFallBack($primary, $failure)) {
                    continue;
                }

                self::log($served, $key, $requestId, null, ['attempts' => $attempts, 'served_by_fallback' => $i > 0, 'status' => 'failed', 'status_code' => 502, 'latency_ms' => self::elapsed($startedAt), 'stream' => true, 'error_message' => $e->getMessage()]);
                $emit('data: ' . json_encode((new LlmProxyException('Upstream provider is unreachable.', 502, 'api_error', 'upstream_unreachable'))->toArray()) . "\n\n");

                return null;
            }

            $failure = self::failureKind($result['status'], null);
            $attempts[] = self::attempt($target, $result['status'], $failure ? 'upstream error' : null, self::elapsed($startedAt));

            if ($failure && !$committed && !$isLast && self::shouldFallBack($primary, $failure)) {
                continue;
            }

            foreach ($held as $chunk) {
                $emit($chunk);
            }

            $log = self::log($served, $key, $requestId, null, [
                'status'             => $result['status'] < 400 ? 'success' : 'failed',
                'status_code'        => $result['status'],
                'input_tokens'       => $result['input_tokens'],
                'output_tokens'      => $result['output_tokens'],
                'cache_read_tokens'  => $result['cache_read_tokens'] ?? 0,
                'latency_ms'         => self::elapsed($startedAt),
                'ttft_ms'            => $result['ttft_ms'],
                'upstream_cost'      => $result['upstream_cost'] ?? null,
                'stream'             => true,
                'attempts'           => $attempts,
                'served_by_fallback' => $i > 0,
            ]);

            return self::meta($served, $log, $requestId);
        }

        return null;
    }

    /**
     * The primary target followed by the alias fallbacks that can run right now (known provider, credit, limits).
     *
     * @return array<int, LlmTarget>
     */
    private static function chain(LlmTarget $primary, LlmApiKeys $key, array $payload): array
    {
        $chain = [$primary];

        // The same public model at the other providers, cheapest first.
        foreach ($primary->alternates as $alternate) {
            try {
                $estimate = self::assertCredit($alternate, $key, self::prepare($payload, $alternate));
                LlmSpendLimitService::assertWithin($key->iam_account_id, null, $estimate);
            } catch (LlmProxyException $e) {
                continue;
            }

            $chain[] = $alternate;
        }

        foreach (array_slice($primary->aliasRecord?->fallbacks ?? [], 0, 5) as $fallback) {
            if (empty($fallback['provider']) || empty($fallback['model'])) {
                continue;
            }

            try {
                $target = ModelResolver::resolveProviderModel($fallback['provider'], $fallback['model'], $key->iam_account_id);
                $estimate = self::assertCredit($target, $key, self::prepare($payload, $primary));
                LlmSpendLimitService::assertWithin($key->iam_account_id, $primary->aliasRecord, $estimate);
            } catch (LlmProxyException $e) {
                continue;
            }

            $chain[] = $target;
        }

        return $chain;
    }

    /**
     * Applies the request-level defaults of the alias (only where the caller sent nothing) and the upstream model id.
     */
    private static function prepare(array $payload, LlmTarget $target): array
    {
        $alias = $target->aliasRecord;
        $payload['model'] = $target->model;

        if ($alias) {
            if ($alias->temperature !== null && !isset($payload['temperature'])) {
                $payload['temperature'] = (float) $alias->temperature;
            }

            if ($alias->max_tokens !== null && !isset($payload['max_tokens'])) {
                $payload['max_tokens'] = (int) $alias->max_tokens;
            }

            if (filled($alias->system_prompt)) {
                array_unshift($payload['messages'], ['role' => 'system', 'content' => $alias->system_prompt]);
            }
        }

        return $payload;
    }

    /**
     * 'rate_limit' (429), 'timeout', 'error' (5xx, auth problems, unreachable) or null when the call is not a
     * provider failure (success or a mistake of the caller, which another model would not fix).
     */
    private static function failureKind(?int $status, ?\Throwable $e): ?string
    {
        if ($e) {
            return str_contains($e->getMessage(), 'cURL error 28') || str_contains($e->getMessage(), 'timed out') ? 'timeout' : 'error';
        }

        return match (true) {
            $status === 429                                  => 'rate_limit',
            $status >= 500 || in_array($status, [401, 403, 408], true) => 'error',
            default                                          => null,
        };
    }

    private static function shouldFallBack(LlmTarget $primary, ?string $failure): bool
    {
        // Another offer of the same public model is always worth trying.
        if ($primary->publicId) {
            return $failure !== null;
        }

        $alias = $primary->aliasRecord;

        return $alias && match ($failure) {
            'rate_limit' => (bool) $alias->fallback_on_rate_limit,
            'timeout'    => (bool) $alias->fallback_on_timeout,
            'error'      => (bool) $alias->fallback_on_error,
            default      => false,
        };
    }

    /**
     * Non-final attempts of an alias with a timeout rule are cut off after fallback_timeout_seconds.
     */
    private static function attemptTimeout(LlmTarget $primary, bool $isLast): int
    {
        $alias = $primary->aliasRecord;

        if (!$isLast && $alias && $alias->fallback_on_timeout && $alias->fallback_timeout_seconds) {
            return min(self::timeout(), (int) $alias->fallback_timeout_seconds);
        }

        return self::timeout();
    }

    /**
     * @return array{provider: string, model: string, status: string, status_code: ?int, error: ?string, latency_ms: int}
     */
    private static function attempt(LlmTarget $target, ?int $statusCode, ?string $error, int $latencyMs): array
    {
        return [
            'provider'    => $target->provider->slug,
            'model'       => $target->model,
            'status'      => $error === null && $statusCode !== null && $statusCode < 400 ? 'success' : 'failed',
            'status_code' => $statusCode,
            'error'       => $error,
            'latency_ms'  => $latencyMs,
        ];
    }

    private static function isErrorEvent(string $chunk): bool
    {
        $chunk = trim($chunk);

        return str_starts_with($chunk, 'data:') && isset((json_decode(trim(substr($chunk, 5)), true) ?: [])['error']);
    }

    /**
     * What a caller needs to show and join a call: request_id matches ai_llm_usage_logs.request_id; cost is what the
     * account is charged (markup included, 0 when not billed or failed).
     *
     * @return array{request_id: string, served_by_fallback: bool, attempts: array<int, array<string, mixed>>, alias: ?string, requested_model: string, provider: string, model: string, input_tokens: int, output_tokens: int, cost: float, latency_ms: ?int, ttft_ms: ?int, status: string}
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
            'served_by_fallback' => (bool) $log->served_by_fallback,
            'attempts'        => $log->attempts ?? [],
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
                $target = isset($spec['alias'])
                    ? ModelResolver::resolve($requested, $key->iam_account_id)
                    : ModelResolver::resolveProviderModel($spec['provider'] ?? null, $spec['model'] ?? null, $key->iam_account_id);
                $estimate = self::assertCredit($target, $key, self::prepare($payload, $target), $reserved);
                LlmSpendLimitService::assertWithin($key->iam_account_id, $target->aliasRecord, $reserved + $estimate);
                $reserved += $estimate;
            } catch (LlmProxyException $e) {
                // A broken target must not take the whole broadcast down; report it in place.
                $targets[$i] = ['requested' => $requested, 'error' => $e->toArray()['error']];
                continue;
            }

            $targets[$i] = ['requested' => $requested, 'target' => $target, 'started_at' => microtime(true)];
            $promises[$i] = $target->driver()->chatAsync(
                array_merge(self::prepare($payload, $target), ['stream' => false]),
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
     * OpenAI-style model list for the key's account: its active aliases plus the public ids of every model it can
     * use. Provider names and upstream model ids are never listed.
     */
    public static function listModels(LlmApiKeys $key): array
    {
        $providers = ModelResolver::usableProviders($key->iam_account_id);

        // Only advertise aliases that currently resolve to an available provider.
        $aliases = LlmAliases::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('iam_account_id', $key->iam_account_id)->orWhereNull('iam_account_id'))
            ->where('is_active', true)
            ->whereIn('target_provider', $providers->pluck('slug')->push(ModelResolver::publicPrefix()))
            ->pluck('alias');

        // Platform calls are billed from the upstream cost or the synced prices, so unpriced models are listed too.
        $models = LlmModels::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('public_id')
            ->whereIn('ai_llm_provider_id', $providers->pluck('id'))
            ->pluck('public_id');

        return [
            'object' => 'list',
            'data'   => $aliases->merge($models)->unique()->sort()->values()
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
        $estimate = self::assertCredit($target, $key, self::prepare($payload, $target));
        LlmSpendLimitService::assertWithin($key->iam_account_id, $target->aliasRecord, $estimate);

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
            'cache_read_tokens' => $result['cache_read_tokens'] ?? 0,
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
