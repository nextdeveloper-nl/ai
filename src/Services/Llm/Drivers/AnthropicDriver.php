<?php

namespace NextDeveloper\AI\Services\Llm\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Driver for Anthropic's Messages API. Translates the OpenAI chat-completions shape in both directions.
 * $baseUrl is expected to include the version segment, e.g. https://api.anthropic.com/v1
 */
class AnthropicDriver implements LlmDriver
{
    private const VERSION = '2023-06-01';
    private const DEFAULT_MAX_TOKENS = 4096;

    public function chat(array $payload, string $baseUrl, string $apiKey, int $timeout): array
    {
        return $this->chatAsync($payload, $baseUrl, $apiKey, $timeout)->wait();
    }

    public function chatAsync(array $payload, string $baseUrl, string $apiKey, int $timeout): PromiseInterface
    {
        $map = function ($response) use ($payload) {
            $body = json_decode((string) $response->getBody(), true) ?? [];
            $text = collect($body['content'] ?? [])->where('type', 'text')->pluck('text')->implode('');
            $input = (int) ($body['usage']['input_tokens'] ?? 0);
            $output = (int) ($body['usage']['output_tokens'] ?? 0);

            return [
                'status' => $response->getStatusCode(),
                'body'   => [
                    'id'      => $body['id'] ?? ('chatcmpl-' . uniqid()),
                    'object'  => 'chat.completion',
                    'created' => time(),
                    'model'   => $body['model'] ?? $payload['model'],
                    'choices' => [[
                        'index'         => 0,
                        'message'       => ['role' => 'assistant', 'content' => $text],
                        'finish_reason' => $this->finishReason($body['stop_reason'] ?? null),
                    ]],
                    'usage' => ['prompt_tokens' => $input, 'completion_tokens' => $output, 'total_tokens' => $input + $output],
                ],
                'input_tokens'  => $input,
                'output_tokens' => $output,
            ];
        };

        return $this->client($timeout)->postAsync(rtrim($baseUrl, '/') . '/messages', [
            'headers' => $this->headers($apiKey),
            'json'    => $this->toAnthropic($payload, false),
        ])->then($map, function ($e) {
            if ($e instanceof RequestException && $e->hasResponse()) {
                // Upstream error: pass status and body through untouched.
                return [
                    'status'        => $e->getResponse()->getStatusCode(),
                    'body'          => json_decode((string) $e->getResponse()->getBody(), true) ?? [],
                    'input_tokens'  => 0,
                    'output_tokens' => 0,
                ];
            }

            throw $e;
        });
    }

    public function stream(array $payload, string $baseUrl, string $apiKey, int $timeout, callable $onChunk): array
    {
        $startedAt = microtime(true);
        $ttft = null;
        $input = 0;
        $output = 0;
        $id = 'chatcmpl-' . uniqid();

        try {
            $response = $this->client($timeout)->post(rtrim($baseUrl, '/') . '/messages', [
                'headers' => $this->headers($apiKey),
                'json'    => $this->toAnthropic($payload, true),
                'stream'  => true,
            ]);
        } catch (RequestException $e) {
            if (!$e->hasResponse()) {
                throw $e;
            }
            $response = $e->getResponse();
            // Never forward the upstream body: it can be a multi-line page that names the provider.
            $onChunk('data: ' . json_encode(['error' => ['message' => 'Upstream error.', 'code' => $response->getStatusCode()]]) . "\n\n");

            return ['status' => $response->getStatusCode(), 'input_tokens' => 0, 'output_tokens' => 0, 'ttft_ms' => null];
        }

        $chunk = fn (array $delta, ?string $finish = null) => 'data: ' . json_encode([
            'id' => $id, 'object' => 'chat.completion.chunk', 'created' => time(), 'model' => $payload['model'],
            'choices' => [['index' => 0, 'delta' => $delta, 'finish_reason' => $finish]],
        ]) . "\n\n";

        $stream = $response->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                // Anthropic events carry "event: x" and "data: {json}" lines; the json has its own "type".
                if (!preg_match('/^data:\s*(.+)$/m', $event, $m)) {
                    continue;
                }
                $json = json_decode($m[1], true);
                if (!is_array($json)) {
                    continue;
                }

                switch ($json['type'] ?? '') {
                    case 'message_start':
                        $input = (int) ($json['message']['usage']['input_tokens'] ?? 0);
                        $onChunk($chunk(['role' => 'assistant', 'content' => '']));
                        break;
                    case 'content_block_delta':
                        if (($json['delta']['type'] ?? '') === 'text_delta') {
                            if ($ttft === null) {
                                $ttft = (int) round((microtime(true) - $startedAt) * 1000);
                            }
                            $onChunk($chunk(['content' => $json['delta']['text'] ?? '']));
                        }
                        break;
                    case 'message_delta':
                        $output = (int) ($json['usage']['output_tokens'] ?? $output);
                        if (!empty($json['delta']['stop_reason'])) {
                            $onChunk($chunk([], $this->finishReason($json['delta']['stop_reason'])));
                        }
                        break;
                    case 'message_stop':
                        // Final usage chunk in OpenAI style, then the terminator.
                        $onChunk('data: ' . json_encode([
                            'id' => $id, 'object' => 'chat.completion.chunk', 'created' => time(), 'model' => $payload['model'],
                            'choices' => [],
                            'usage' => ['prompt_tokens' => $input, 'completion_tokens' => $output, 'total_tokens' => $input + $output],
                        ]) . "\n\n");
                        $onChunk("data: [DONE]\n\n");
                        break;
                }
            }
        }

        return ['status' => $response->getStatusCode(), 'input_tokens' => $input, 'output_tokens' => $output, 'ttft_ms' => $ttft];
    }

    public function fetchModels(string $baseUrl, string $apiKey, int $timeout): array
    {
        $response = $this->client($timeout)->get(rtrim($baseUrl, '/') . '/models', [
            'headers' => $this->headers($apiKey),
            'query'   => ['limit' => 1000],
        ]);
        $data = json_decode((string) $response->getBody(), true)['data'] ?? [];

        return array_values(array_filter(array_map(
            fn ($m) => isset($m['id']) ? ['model_id' => $m['id'], 'display_name' => $m['display_name'] ?? $m['id']] : null,
            $data
        )));
    }

    /**
     * OpenAI request -> Anthropic request. System messages are lifted into the top-level "system" field.
     */
    private function toAnthropic(array $payload, bool $stream): array
    {
        $system = [];
        $messages = [];

        foreach ($payload['messages'] ?? [] as $message) {
            $content = is_array($message['content'] ?? null)
                ? collect($message['content'])->where('type', 'text')->pluck('text')->implode("\n")
                : (string) ($message['content'] ?? '');

            if (($message['role'] ?? '') === 'system') {
                $system[] = $content;
                continue;
            }

            $messages[] = ['role' => $message['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $content];
        }

        $request = [
            'model'      => $payload['model'],
            'messages'   => $messages,
            'max_tokens' => (int) ($payload['max_tokens'] ?? $payload['max_completion_tokens'] ?? self::DEFAULT_MAX_TOKENS),
            'stream'     => $stream,
        ];

        if ($system) {
            $request['system'] = implode("\n", $system);
        }
        foreach (['temperature', 'top_p'] as $field) {
            if (isset($payload[$field])) {
                $request[$field] = $payload[$field];
            }
        }
        if (isset($payload['stop'])) {
            $request['stop_sequences'] = (array) $payload['stop'];
        }

        return $request;
    }

    private function finishReason(?string $stopReason): string
    {
        return match ($stopReason) {
            'max_tokens' => 'length',
            default      => 'stop',
        };
    }

    private function headers(string $apiKey): array
    {
        return ['x-api-key' => $apiKey, 'anthropic-version' => self::VERSION, 'Accept' => 'application/json'];
    }

    private function client(int $timeout): Client
    {
        return new Client(['timeout' => $timeout, 'connect_timeout' => 10]);
    }
}
