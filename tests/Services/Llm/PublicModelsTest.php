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
}
