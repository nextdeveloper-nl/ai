<?php

namespace NextDeveloper\AI\Services\Llm\Drivers;

/**
 * Contract for an upstream LLM provider driver. Every driver speaks the OpenAI chat-completions
 * shape towards our callers, whatever the upstream protocol is.
 */
interface LlmDriver
{
    /**
     * Non-streaming chat completion.
     *
     * @param array  $payload OpenAI-shaped request body (model already resolved to the upstream model id).
     * @return array{status:int, body:array, input_tokens:int, output_tokens:int}
     */
    public function chat(array $payload, string $baseUrl, string $apiKey, int $timeout): array;

    /**
     * Same as chat() but returns a Guzzle promise resolving to the chat() result array.
     * Used by broadcasts to call several upstreams concurrently.
     */
    public function chatAsync(array $payload, string $baseUrl, string $apiKey, int $timeout): \GuzzleHttp\Promise\PromiseInterface;

    /**
     * Streaming chat completion. $onChunk receives complete OpenAI-shaped SSE lines ("data: {...}\n\n").
     *
     * @param callable(string):void $onChunk
     * @return array{status:int, input_tokens:int, output_tokens:int, ttft_ms:?int}
     */
    public function stream(array $payload, string $baseUrl, string $apiKey, int $timeout, callable $onChunk): array;

    /**
     * Lists the upstream models.
     *
     * @return array<int, array{model_id:string, display_name:?string}>
     */
    public function fetchModels(string $baseUrl, string $apiKey, int $timeout): array;
}
