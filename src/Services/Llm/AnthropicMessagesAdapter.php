<?php

namespace NextDeveloper\AI\Services\Llm;

use Illuminate\Support\Str;

/**
 * Translates between the Anthropic Messages API (POST /ai/v1/messages) and the OpenAI chat completions
 * format the proxy works with internally. Stateless helpers for requests/responses/errors; the streaming
 * translation lives in AnthropicStreamTranslator.
 */
class AnthropicMessagesAdapter
{
    /**
     * Anthropic Messages request -> OpenAI chat completions request.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public static function toOpenAiRequest(array $request): array
    {
        $messages = [];

        $system = self::flattenText($request['system'] ?? null);
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($request['messages'] ?? [] as $message) {
            array_push($messages, ...self::convertMessage($message));
        }

        $payload = ['model' => $request['model'] ?? null, 'messages' => $messages];

        foreach (['max_tokens', 'temperature', 'top_p', 'stream'] as $field) {
            if (isset($request[$field])) {
                $payload[$field] = $request[$field];
            }
        }

        if (!empty($request['stop_sequences'])) {
            $payload['stop'] = $request['stop_sequences'];
        }

        if (!empty($request['metadata']['user_id'])) {
            $payload['user'] = $request['metadata']['user_id'];
        }

        if (!empty($request['tools'])) {
            $payload['tools'] = array_map(fn ($tool) => ['type' => 'function', 'function' => array_filter([
                'name'        => $tool['name'],
                'description' => $tool['description'] ?? null,
                'parameters'  => $tool['input_schema'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ], fn ($v) => $v !== null)], $request['tools']);
        }

        if (isset($request['tool_choice']['type'])) {
            $payload['tool_choice'] = match ($request['tool_choice']['type']) {
                'any'   => 'required',
                'tool'  => ['type' => 'function', 'function' => ['name' => $request['tool_choice']['name'] ?? '']],
                'none'  => 'none',
                default => 'auto',
            };
        }

        return $payload;
    }

    /**
     * OpenAI chat completion -> Anthropic message.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function fromOpenAiResponse(array $body, string $requestedModel): array
    {
        $choice = $body['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $content = [];

        $text = self::flattenText($message['content'] ?? null);
        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        foreach ($message['tool_calls'] ?? [] as $call) {
            $content[] = [
                'type'  => 'tool_use',
                'id'    => $call['id'] ?? 'toolu_' . Str::random(24),
                'name'  => $call['function']['name'] ?? '',
                'input' => self::decodeArguments($call['function']['arguments'] ?? ''),
            ];
        }

        if ($content === []) {
            $content[] = ['type' => 'text', 'text' => ''];
        }

        return [
            'id'            => self::messageId($body['id'] ?? null),
            'type'          => 'message',
            'role'          => 'assistant',
            'model'         => $requestedModel,
            'content'       => $content,
            'stop_reason'   => self::stopReason($choice['finish_reason'] ?? null),
            'stop_sequence' => null,
            'usage'         => [
                'input_tokens'  => (int) ($body['usage']['prompt_tokens'] ?? 0),
                'output_tokens' => (int) ($body['usage']['completion_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * Anthropic error envelope for an HTTP status.
     *
     * @return array{type: string, error: array{type: string, message: string}}
     */
    public static function errorBody(string $message, int $status): array
    {
        return ['type' => 'error', 'error' => ['type' => self::errorType($status), 'message' => $message]];
    }

    public static function errorType(int $status): string
    {
        return match (true) {
            $status === 401 => 'authentication_error',
            $status === 402 => 'billing_error',
            $status === 403 => 'permission_error',
            $status === 404 => 'not_found_error',
            $status === 413 => 'request_too_large',
            $status === 429 => 'rate_limit_error',
            $status === 529 => 'overloaded_error',
            $status >= 500  => 'api_error',
            default         => 'invalid_request_error',
        };
    }

    public static function stopReason(?string $finishReason): string
    {
        return match ($finishReason) {
            'length'     => 'max_tokens',
            'tool_calls', 'function_call' => 'tool_use',
            default      => 'end_turn',
        };
    }

    public static function messageId(?string $upstreamId = null): string
    {
        return 'msg_' . preg_replace('/[^A-Za-z0-9]/', '', $upstreamId ?: Str::random(24));
    }

    /**
     * @param array<string, mixed> $message
     * @return array<int, array<string, mixed>> one Anthropic message can become several OpenAI messages
     */
    private static function convertMessage(array $message): array
    {
        $role = $message['role'] ?? 'user';
        $content = $message['content'] ?? '';

        if (is_string($content)) {
            return [['role' => $role, 'content' => $content]];
        }

        $parts = [];
        $text = [];
        $toolCalls = [];
        $toolResults = [];

        foreach ($content as $block) {
            switch ($block['type'] ?? 'text') {
                case 'text':
                    $text[] = $block['text'] ?? '';
                    $parts[] = ['type' => 'text', 'text' => $block['text'] ?? ''];
                    break;
                case 'image':
                    $source = $block['source'] ?? [];
                    $url = ($source['type'] ?? '') === 'base64'
                        ? 'data:' . ($source['media_type'] ?? 'image/png') . ';base64,' . ($source['data'] ?? '')
                        : ($source['url'] ?? '');
                    $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
                    break;
                case 'tool_use':
                    $toolCalls[] = [
                        'id'       => $block['id'] ?? 'toolu_' . Str::random(24),
                        'type'     => 'function',
                        'function' => ['name' => $block['name'] ?? '', 'arguments' => json_encode($block['input'] ?? new \stdClass())],
                    ];
                    break;
                case 'tool_result':
                    $toolResults[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $block['tool_use_id'] ?? '',
                        'content'      => self::flattenText($block['content'] ?? '') ?: (($block['is_error'] ?? false) ? 'error' : ''),
                    ];
                    break;
                // thinking / redacted_thinking / other block types have no OpenAI equivalent and are dropped.
            }
        }

        // Tool results answer the previous assistant tool calls, so they come before any new user text.
        $converted = $toolResults;

        if ($role === 'assistant') {
            $assistant = ['role' => 'assistant', 'content' => implode("\n", $text) ?: null];
            if ($toolCalls) {
                $assistant['tool_calls'] = $toolCalls;
            }
            $converted[] = $assistant;
        } elseif ($parts) {
            $hasImage = (bool) array_filter($parts, fn ($p) => $p['type'] === 'image_url');
            $converted[] = ['role' => 'user', 'content' => $hasImage ? $parts : implode("\n", $text)];
        }

        return $converted;
    }

    /**
     * @param mixed $value string, or a list of {type: text, text} blocks
     */
    private static function flattenText(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            return '';
        }

        return implode("\n", array_map(
            fn ($block) => is_array($block) ? ($block['text'] ?? '') : (string) $block,
            $value
        ));
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    private static function decodeArguments(string $arguments): array|\stdClass
    {
        $decoded = json_decode($arguments, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : new \stdClass();
    }
}
