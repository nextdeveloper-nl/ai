<?php

namespace NextDeveloper\AI\Services\Llm\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Driver for OpenAI-compatible upstreams (OpenAI, LiteLLM, vLLM, OpenRouter, ...).
 * $baseUrl is expected to include the version segment, e.g. https://api.openai.com/v1
 */
class OpenAiCompatibleDriver implements LlmDriver
{
    public function chat(array $payload, string $baseUrl, string $apiKey, int $timeout): array
    {
        return $this->chatAsync($payload, $baseUrl, $apiKey, $timeout)->wait();
    }

    public function chatAsync(array $payload, string $baseUrl, string $apiKey, int $timeout): PromiseInterface
    {
        $payload['stream'] = false;

        $map = function ($response) {
            $body = json_decode((string) $response->getBody(), true) ?? [];

            return [
                'status'        => $response->getStatusCode(),
                'body'          => $body,
                'input_tokens'  => (int) ($body['usage']['prompt_tokens'] ?? 0),
                'output_tokens' => (int) ($body['usage']['completion_tokens'] ?? 0),
                'cache_read_tokens' => (int) ($body['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
                'upstream_cost' => $this->reportedCost($body['usage'] ?? [], $response),
            ];
        };

        return $this->client($timeout)->postAsync(rtrim($baseUrl, '/') . '/chat/completions', [
            'headers' => $this->headers($apiKey),
            'json'    => $payload,
        ])->then($map, function ($e) use ($map) {
            // Upstream answered with an error: pass its body and status code through to the caller.
            if ($e instanceof RequestException && $e->hasResponse()) {
                return $map($e->getResponse());
            }

            throw $e;
        });
    }

    public function stream(array $payload, string $baseUrl, string $apiKey, int $timeout, callable $onChunk): array
    {
        $payload['stream'] = true;
        // Ask the upstream to report token usage in the last chunk, we need it for metering.
        $payload['stream_options'] = array_merge($payload['stream_options'] ?? [], ['include_usage' => true]);

        $startedAt = microtime(true);
        $ttft = null;
        $input = 0;
        $output = 0;
        $cacheRead = 0;
        $cost = null;

        try {
            $response = $this->client($timeout)->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'headers' => $this->headers($apiKey),
                'json'    => $payload,
                'stream'  => true,
            ]);
        } catch (RequestException $e) {
            if (!$e->hasResponse()) {
                throw $e;
            }
            $response = $e->getResponse();
            // Error bodies are not SSE; forward them as a single data event so the client sees the reason.
            $onChunk('data: ' . (string) $response->getBody() . "\n\n");

            return ['status' => $response->getStatusCode(), 'input_tokens' => 0, 'output_tokens' => 0, 'ttft_ms' => null, 'upstream_cost' => null];
        }

        $cost = $this->reportedCost([], $response);
        $stream = $response->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);

            // SSE events are separated by a blank line.
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                if ($ttft === null) {
                    $ttft = (int) round((microtime(true) - $startedAt) * 1000);
                }

                if (str_starts_with($event, 'data:')) {
                    $json = json_decode(trim(substr($event, 5)), true);
                    if (is_array($json) && isset($json['usage'])) {
                        $input = (int) ($json['usage']['prompt_tokens'] ?? $input);
                        $output = (int) ($json['usage']['completion_tokens'] ?? $output);
                        $cacheRead = (int) ($json['usage']['prompt_tokens_details']['cached_tokens'] ?? $cacheRead);
                        $cost = $this->reportedCost($json['usage'], null) ?? $cost;
                    }
                }

                $onChunk($event . "\n\n");
            }
        }

        return ['status' => $response->getStatusCode(), 'input_tokens' => $input, 'output_tokens' => $output, 'cache_read_tokens' => $cacheRead, 'ttft_ms' => $ttft, 'upstream_cost' => $cost];
    }

    /**
     * Lists the upstream models. When the upstream is a LiteLLM proxy, /model/info is merged in so prices
     * (per 1M tokens), context window and the real upstream provider come along; a zero or missing price is
     * reported as null, never as "free".
     */
    public function fetchModels(string $baseUrl, string $apiKey, int $timeout): array
    {
        $base = rtrim($baseUrl, '/');
        $client = $this->client($timeout);
        $response = $client->get($base . '/models', ['headers' => $this->headers($apiKey)]);
        $data = json_decode((string) $response->getBody(), true)['data'] ?? [];

        $info = [];

        try {
            $infoResponse = $client->get(preg_replace('#/v\d+$#', '', $base) . '/model/info', ['headers' => $this->headers($apiKey)]);

            foreach (json_decode((string) $infoResponse->getBody(), true)['data'] ?? [] as $entry) {
                $name = $entry['model_name'] ?? null;
                $details = $entry['model_info'] ?? [];
                $hasPrice = !empty($details['input_cost_per_token']) && !empty($details['output_cost_per_token']);

                // LiteLLM can list a name more than once (load balancing); keep the entry that carries prices.
                if ($name && (!isset($info[$name]) || $hasPrice)) {
                    $info[$name] = $details;
                }
            }
        } catch (\Throwable $e) {
            // Not a LiteLLM proxy (or no admin access): plain model list only.
        }

        $models = [];

        foreach ($data as $m) {
            if (!isset($m['id'])) {
                continue;
            }

            // A model the upstream itself marks as not ready is left out, so the sync switches it off.
            if (($m['is_ready'] ?? true) === false) {
                continue;
            }

            $details = $info[$m['id']] ?? [];
            $pricing = is_array($m['pricing'] ?? null) ? $m['pricing'] : [];

            // Upstreams that list prices per token on /models (OpenRouter style) win over LiteLLM's /model/info.
            $in = (float) ($pricing['prompt'] ?? $details['input_cost_per_token'] ?? 0);
            $out = (float) ($pricing['completion'] ?? $details['output_cost_per_token'] ?? 0);
            $cacheRead = (float) ($pricing['input_cache_read'] ?? $details['cache_read_input_token_cost'] ?? 0);
            $cacheWrite = (float) ($pricing['input_cache_write'] ?? $details['cache_creation_input_token_cost'] ?? 0);

            $models[] = [
                'model_id'                => $m['id'],
                'display_name'            => $m['name'] ?? $m['id'],
                'input_price_per_1m'      => $in > 0 ? round($in * 1_000_000, 6) : null,
                'output_price_per_1m'     => $out > 0 ? round($out * 1_000_000, 6) : null,
                // Cache prices of 0 are real (free cache writes), so only a missing price is null.
                'cache_read_price_per_1m' => isset($pricing['input_cache_read']) || $cacheRead > 0 ? round($cacheRead * 1_000_000, 6) : null,
                'cache_write_price_per_1m' => isset($pricing['input_cache_write']) || $cacheWrite > 0 ? round($cacheWrite * 1_000_000, 6) : null,
                'context_window'          => $m['context_length'] ?? $details['max_input_tokens'] ?? null,
                'capabilities'            => $this->capabilities($details) + $this->listedCapabilities($m),
            ];
        }

        return $models;
    }

    /**
     * Capabilities an upstream lists on /models itself (output limit, features, quantization, modalities).
     *
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    protected function listedCapabilities(array $model): array
    {
        $features = (array) ($model['supported_features'] ?? []);

        return array_filter([
            'max_output_tokens' => $model['max_output_length'] ?? null,
            'tools'             => $features ? in_array('tools', $features, true) || in_array('function_calling', $features, true) : null,
            'json'              => $features ? in_array('json_mode', $features, true) : null,
            'reasoning'         => $features ? in_array('supports_reasoning', $features, true) : null,
            'quantization'      => $model['quantization'] ?? null,
            'input_modalities'  => $model['input_modalities'] ?? null,
            'output_modalities' => $model['output_modalities'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Capability flags the upstream reports for a model (LiteLLM model info); empty when it reports nothing.
     *
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    protected function capabilities(array $details): array
    {
        if (!$details) {
            return [];
        }

        $mode = $details['mode'] ?? null;

        return array_filter([
            'upstream_provider' => $details['litellm_provider'] ?? null,
            'mode'              => $mode,
            'chat'              => $mode ? $mode === 'chat' : null,
            'embeddings'        => $mode ? $mode === 'embedding' : null,
            'vision'            => $details['supports_vision'] ?? null,
            'tools'             => $details['supports_function_calling'] ?? null,
            'json'              => $details['supports_response_schema'] ?? null,
            'max_output_tokens' => $details['max_output_tokens'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Cost in USD the upstream itself reported for this call (LiteLLM: usage.cost or x-litellm-response-cost).
     */
    protected function reportedCost(array $usage, $response): ?float
    {
        $cost = $usage['cost'] ?? ($response ? $response->getHeaderLine('x-litellm-response-cost') : null);

        return is_numeric($cost) && (float) $cost > 0 ? (float) $cost : null;
    }

    protected function headers(string $apiKey): array
    {
        return ['Authorization' => 'Bearer ' . $apiKey, 'Accept' => 'application/json'];
    }

    protected function client(int $timeout): Client
    {
        return new Client(['timeout' => $timeout, 'connect_timeout' => 10]);
    }
}
