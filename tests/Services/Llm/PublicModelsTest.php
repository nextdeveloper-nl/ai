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

    /**
     * @param array<string, mixed> $attributes
     */
    private function row(string $class, array $attributes): object
    {
        return $this->bare($class, $attributes);
    }

    public function test_unavailable_models_are_told_apart(): void
    {
        $active = $this->row(LlmProviders::class, ['id' => 1, 'slug' => 'greenference', 'is_active' => true, 'deleted_at' => null]);
        $off = $this->row(LlmProviders::class, ['id' => 2, 'slug' => 'other', 'is_active' => false, 'deleted_at' => null]);
        $model = fn (int $provider, bool $isActive) => $this->row(LlmModels::class, ['ai_llm_provider_id' => $provider, 'model_id' => 'm', 'is_active' => $isActive]);

        $this->assertSame('no_model', ModelResolver::classify([], [], [1])['code']);
        $this->assertSame('model_disabled', ModelResolver::classify([$model(1, false)], [1 => $active], [1])['code']);

        $inactive = ModelResolver::classify([$model(2, true)], [2 => $off], [1]);
        $this->assertSame('provider_unavailable', $inactive['code']);
        $this->assertStringContainsString("provider 'other' is inactive", $inactive['detail']);

        $foreign = ModelResolver::classify([$model(1, true)], [1 => $active], []);
        $this->assertStringContainsString('belongs to another account', $foreign['detail']);
    }

    public function test_public_models_of_account_providers_are_usable_and_billed(): void
    {
        $owned = $this->row(LlmProviders::class, ['id' => 1, 'slug' => 'greenference', 'is_active' => true, 'deleted_at' => null, 'iam_account_id' => 5]);
        $public = $this->row(LlmModels::class, ['ai_llm_provider_id' => 1, 'model_id' => 'm', 'is_active' => true, 'is_public' => true]);
        $private = $this->row(LlmModels::class, ['ai_llm_provider_id' => 1, 'model_id' => 'm', 'is_active' => true, 'is_public' => false]);

        $this->assertStringContainsString('usable', ModelResolver::classify([$public], [1 => $owned], [])['detail']);
        $this->assertStringContainsString('belongs to another account', ModelResolver::classify([$private], [1 => $owned], [])['detail']);

        $this->assertTrue((new LlmTarget($owned, 'm', $public, 'x'))->isBilled());
        $this->assertFalse((new LlmTarget($owned, 'm', $private, 'x'))->isBilled());
    }

    public function test_empty_messages_are_left_out_of_the_conversation(): void
    {
        $user = ['role' => 'user', 'content' => 'what is your name ?'];
        $call = ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'a']]];
        $result = ['role' => 'tool', 'content' => '', 'tool_call_id' => 'a'];

        $this->assertSame(
            [$user, ['role' => 'user', 'content' => 'and who made you?']],
            LlmProxyService::withoutEmptyMessages([$user, ['role' => 'assistant', 'content' => ''], ['role' => 'assistant', 'content' => null], ['role' => 'assistant', 'content' => '  '], ['role' => 'user', 'content' => 'and who made you?']])
        );
        $this->assertSame([$user, $call, $result], LlmProxyService::withoutEmptyMessages([$user, $call, $result]));
        $this->assertSame([['role' => 'user', 'content' => '']], LlmProxyService::withoutEmptyMessages([['role' => 'user', 'content' => '']]));
    }

    public function test_errors_tell_the_caller_what_to_do_and_never_name_the_provider(): void
    {
        $method = new ReflectionMethod(LlmProxyService::class, 'publicAnswer');
        $primary = $this->offer('greenference', 1, 1);

        foreach ([[429, 'Wait a few seconds'], [400, 'none should be empty'], [503, 'contact support']] as [$status, $hint]) {
            $message = $method->invoke(null, $status, ['error' => ['message' => 'greenference said no']], $primary)[1]['error']['message'];

            $this->assertStringContainsString($hint, $message);
            $this->assertStringNotContainsStringIgnoringCase('greenference', $message);
        }
    }
}
