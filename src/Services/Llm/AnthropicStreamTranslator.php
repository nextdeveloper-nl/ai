<?php

namespace NextDeveloper\AI\Services\Llm;

/**
 * Turns the OpenAI chat.completion.chunk SSE stream into the Anthropic Messages event stream
 * (message_start, content_block_start/delta/stop, message_delta, message_stop).
 *
 * Feed it every raw SSE string the proxy emits; it returns the Anthropic SSE strings to forward.
 * Call finish() once the upstream is done (safe to call repeatedly).
 */
class AnthropicStreamTranslator
{
    private bool $started = false;

    private bool $finished = false;

    private ?string $openBlock = null;

    private int $blockIndex = -1;

    /** @var array<int, int> OpenAI tool call index -> Anthropic block index */
    private array $toolBlocks = [];

    private ?string $finishReason = null;

    private int $inputTokens = 0;

    private int $outputTokens = 0;

    public function __construct(private string $requestedModel)
    {
    }

    /**
     * @return array<int, string>
     */
    public function feed(string $sse): array
    {
        $out = [];

        foreach (preg_split("/\n\n/", trim($sse)) as $event) {
            $event = trim($event);

            if (!str_starts_with($event, 'data:')) {
                continue;
            }

            $data = trim(substr($event, 5));

            if ($data === '[DONE]') {
                array_push($out, ...$this->finish());

                continue;
            }

            $json = json_decode($data, true);

            if (!is_array($json)) {
                continue;
            }

            if (isset($json['error'])) {
                $out[] = $this->sse('error', AnthropicMessagesAdapter::errorBody(
                    $json['error']['message'] ?? 'Upstream error.',
                    (int) ($json['error']['status'] ?? 500)
                ));

                continue;
            }

            array_push($out, ...$this->chunk($json));
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public function finish(): array
    {
        if ($this->finished) {
            return [];
        }

        $this->finished = true;
        $out = $this->start();
        array_push($out, ...$this->closeBlock());

        $out[] = $this->sse('message_delta', [
            'type'  => 'message_delta',
            'delta' => ['stop_reason' => AnthropicMessagesAdapter::stopReason($this->finishReason), 'stop_sequence' => null],
            'usage' => ['input_tokens' => $this->inputTokens, 'output_tokens' => $this->outputTokens],
        ]);
        $out[] = $this->sse('message_stop', ['type' => 'message_stop']);

        return $out;
    }

    /**
     * @param array<string, mixed> $json
     * @return array<int, string>
     */
    private function chunk(array $json): array
    {
        $out = $this->start($json['id'] ?? null);

        if (isset($json['usage'])) {
            $this->inputTokens = (int) ($json['usage']['prompt_tokens'] ?? $this->inputTokens);
            $this->outputTokens = (int) ($json['usage']['completion_tokens'] ?? $this->outputTokens);
        }

        $choice = $json['choices'][0] ?? [];
        $delta = $choice['delta'] ?? [];

        if (isset($delta['content']) && $delta['content'] !== '') {
            if ($this->openBlock !== 'text') {
                array_push($out, ...$this->closeBlock());
                $this->openBlock = 'text';
                $this->blockIndex++;
                $out[] = $this->sse('content_block_start', [
                    'type' => 'content_block_start', 'index' => $this->blockIndex,
                    'content_block' => ['type' => 'text', 'text' => ''],
                ]);
            }

            $out[] = $this->sse('content_block_delta', [
                'type' => 'content_block_delta', 'index' => $this->blockIndex,
                'delta' => ['type' => 'text_delta', 'text' => $delta['content']],
            ]);
        }

        foreach ($delta['tool_calls'] ?? [] as $call) {
            $openAiIndex = (int) ($call['index'] ?? 0);

            if (!isset($this->toolBlocks[$openAiIndex])) {
                array_push($out, ...$this->closeBlock());
                $this->openBlock = 'tool';
                $this->toolBlocks[$openAiIndex] = ++$this->blockIndex;
                $out[] = $this->sse('content_block_start', [
                    'type' => 'content_block_start', 'index' => $this->blockIndex,
                    'content_block' => [
                        'type' => 'tool_use', 'id' => $call['id'] ?? 'toolu_' . substr(md5((string) microtime(true)), 0, 24),
                        'name' => $call['function']['name'] ?? '', 'input' => new \stdClass(),
                    ],
                ]);
            }

            $arguments = $call['function']['arguments'] ?? '';
            if ($arguments !== '') {
                $out[] = $this->sse('content_block_delta', [
                    'type' => 'content_block_delta', 'index' => $this->toolBlocks[$openAiIndex],
                    'delta' => ['type' => 'input_json_delta', 'partial_json' => $arguments],
                ]);
            }
        }

        if (!empty($choice['finish_reason'])) {
            $this->finishReason = $choice['finish_reason'];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function start(?string $upstreamId = null): array
    {
        if ($this->started) {
            return [];
        }

        $this->started = true;

        return [$this->sse('message_start', [
            'type'    => 'message_start',
            'message' => [
                'id' => AnthropicMessagesAdapter::messageId($upstreamId), 'type' => 'message', 'role' => 'assistant',
                'model' => $this->requestedModel, 'content' => [], 'stop_reason' => null, 'stop_sequence' => null,
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
            ],
        ])];
    }

    /**
     * @return array<int, string>
     */
    private function closeBlock(): array
    {
        if ($this->openBlock === null) {
            return [];
        }

        $this->openBlock = null;

        return [$this->sse('content_block_stop', ['type' => 'content_block_stop', 'index' => $this->blockIndex])];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sse(string $event, array $data): string
    {
        return "event: {$event}\ndata: " . json_encode($data) . "\n\n";
    }
}
