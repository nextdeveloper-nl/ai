<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Services\Llm\LlmProxyService;
use NextDeveloper\AI\Services\Llm\LlmTarget;
use NextDeveloper\AI\Services\Llm\ModelResolver;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PublicModelsTest extends TestCase
{
    /**
     * The generated models need the application (config) to be constructed; these tests only need their attributes.
     *
     * @param class-string $class
     * @param array<string, mixed> $attributes
     */
    private function bare(string $class, array $attributes): object
    {
        $model = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $model->setRawAttributes($attributes);

        return $model;
    }

    private function offer(string $slug, ?float $in, ?float $out): LlmTarget
    {
        return new LlmTarget(
            $this->bare(LlmProviders::class, ['slug' => $slug]),
            'm',
            $this->bare(LlmModels::class, ['input_price_per_1m' => $in, 'output_price_per_1m' => $out]),
            'plusclouds/m',
            null,
            null,
            'plusclouds/m'
        );
    }

    /**
     * @return array<int, string>
     */
    private function order(array $offers): array
    {
        return array_map(fn (LlmTarget $t) => $t->provider->slug, ModelResolver::cheapestFirst($offers));
    }

    public function test_cheapest_blended_price_comes_first_and_unpriced_last(): void
    {
        $order = $this->order([
            $this->offer('unpriced', null, null),
            $this->offer('dear', 1.0, 4.0),
            // Blended (3 * in + out) / 4: 0.5 against 0.625 for "dear-output", even though its input is higher.
            $this->offer('cheap', 0.2, 0.2),
            $this->offer('dear-output', 0.1, 2.2),
        ]);

        $this->assertSame(['cheap', 'dear-output', 'dear', 'unpriced'], $order);
    }

    public function test_equal_prices_keep_their_order(): void
    {
        $this->assertSame(['a', 'b', 'c'], $this->order([$this->offer('a', 1, 1), $this->offer('b', 1, 1), $this->offer('c', 1, 1)]));
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function answer(int $status, array $body): array
    {
        $method = new ReflectionMethod(LlmProxyService::class, 'publicAnswer');

        return $method->invoke(null, $status, $body, $this->offer('greenference', 1, 1));
    }

    public function test_success_names_the_requested_model_not_the_upstream_one(): void
    {
        [$status, $body] = $this->answer(200, ['model' => 'greenference/gemma-4-31b-it', 'choices' => []]);

        $this->assertSame(200, $status);
        $this->assertSame('plusclouds/m', $body['model']);
    }

    public function test_upstream_errors_are_generic_and_never_name_the_provider(): void
    {
        foreach ([[401, 502], [403, 502], [500, 502], [503, 502], [429, 429], [422, 400], [400, 400]] as [$upstream, $expected]) {
            [$status, $body] = $this->answer($upstream, ['error' => ['message' => 'greenference key invalid for greenference/gemma']]);

            $this->assertSame($expected, $status, "upstream {$upstream}");
            $this->assertStringNotContainsStringIgnoringCase('greenference', json_encode($body));
        }
    }

    public function test_stream_events_are_rewritten(): void
    {
        $method = new ReflectionMethod(LlmProxyService::class, 'publicChunk');
        $primary = $this->offer('greenference', 1, 1);

        $chunk = $method->invoke(null, 'data: ' . json_encode(['model' => 'greenference/x', 'choices' => [['delta' => ['content' => 'hi']]]]) . "\n\n", $primary);
        $this->assertSame('plusclouds/m', json_decode(substr($chunk, 5), true)['model']);
        $this->assertStringContainsString('"content":"hi"', $chunk);

        $error = $method->invoke(null, 'data: ' . json_encode(['error' => ['message' => 'greenference down', 'code' => 500]]) . "\n\n", $primary);
        $this->assertStringNotContainsStringIgnoringCase('greenference', $error);

        $this->assertSame("data: [DONE]\n\n", $method->invoke(null, "data: [DONE]\n\n", $primary));
    }

    public function test_stream_data_that_is_not_json_is_replaced_not_forwarded(): void
    {
        $method = new ReflectionMethod(LlmProxyService::class, 'publicChunk');
        $primary = $this->offer('greenference', 1, 1);

        $page = $method->invoke(null, "data: <html>greenference gateway error</html>\n\n", $primary);
        $this->assertStringNotContainsStringIgnoringCase('greenference', $page);
        $this->assertStringContainsString('upstream_error', $page);

        // Several lines in one event: the model is rewritten wherever it is, the event field is kept.
        $event = $method->invoke(null, "event: message\ndata: " . json_encode(['model' => 'greenference/x']) . "\ndata: " . json_encode(['error' => ['message' => 'greenference']]) . "\n\n", $primary);
        $this->assertStringContainsString('event: message', $event);
        $this->assertStringNotContainsStringIgnoringCase('greenference', $event);
    }

    public function test_public_meta_hides_provider_model_and_attempt_details(): void
    {
        $meta = LlmProxyService::publicMeta([
            'request_id' => 'r', 'provider' => 'greenference', 'model' => 'greenference/x', 'requested_model' => 'plusclouds/x', 'cost' => 0.1,
            'attempts' => [['provider' => 'greenference', 'model' => 'greenference/x', 'status' => 'failed', 'status_code' => 500, 'error' => 'greenference down', 'latency_ms' => 12]],
        ]);

        $this->assertArrayNotHasKey('provider', $meta);
        $this->assertSame('plusclouds/x', $meta['model']);
        $this->assertSame([['status' => 'failed', 'status_code' => 500, 'latency_ms' => 12]], $meta['attempts']);
        $this->assertStringNotContainsStringIgnoringCase('greenference', json_encode($meta));
    }

    public function test_lines_without_an_sse_field_replace_the_whole_event(): void
    {
        $method = new ReflectionMethod(LlmProxyService::class, 'publicChunk');
        $primary = $this->offer('greenference', 1, 1);

        // A multi-line upstream page: only its first line carries "data:".
        $chunk = $method->invoke(null, "data: <html>\n<body>greenference gateway error</body>\n</html>\n\n", $primary);

        $this->assertStringNotContainsStringIgnoringCase('greenference', $chunk);
        $this->assertStringContainsString('upstream_error', $chunk);

        // Comments and SSE fields still pass.
        $kept = $method->invoke(null, ": keep-alive\nevent: message\nid: 7\ndata: " . json_encode(['model' => 'x']) . "\n\n", $primary);
        $this->assertStringContainsString(': keep-alive', $kept);
        $this->assertStringContainsString('id: 7', $kept);
    }
}
