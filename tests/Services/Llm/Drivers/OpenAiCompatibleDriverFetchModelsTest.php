<?php

namespace NextDeveloper\AI\Tests\Services\Llm\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Services\Llm\Drivers\OpenAiCompatibleDriver;
use NextDeveloper\AI\Services\LlmModelsService;
use PHPUnit\Framework\TestCase;

class OpenAiCompatibleDriverFetchModelsTest extends TestCase
{
    /**
     * @param list<Response> $responses
     */
    private function driver(array $responses): OpenAiCompatibleDriver
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        return new class($client) extends OpenAiCompatibleDriver
        {
            public function __construct(private Client $mock)
            {
            }

            protected function client(int $timeout): Client
            {
                return $this->mock;
            }
        };
    }

    /**
     * The model constructor needs the application (config); the price math does not.
     *
     * @param array<string, mixed> $attributes
     */
    private function model(array $attributes): LlmModels
    {
        $model = (new \ReflectionClass(LlmModels::class))->newInstanceWithoutConstructor();
        $model->setRawAttributes($attributes);

        return $model;
    }

    public function test_it_reads_prices_context_and_capabilities_listed_on_models(): void
    {
        $listing = ['data' => [
            [
                'id' => 'greenference/gemma-4-31b-it',
                'name' => 'Google: Gemma 4 31B It',
                'context_length' => 262144,
                'max_output_length' => 262141,
                'quantization' => 'fp8',
                'is_ready' => true,
                'pricing' => ['prompt' => '0.0000001485', 'completion' => '0.00000042', 'input_cache_read' => '0.00000005775', 'input_cache_write' => '0'],
                'supported_features' => ['function_calling', 'tools', 'json_mode'],
                'input_modalities' => ['text'],
            ],
            ['id' => 'greenference/not-ready', 'is_ready' => false, 'pricing' => ['prompt' => '0.000001', 'completion' => '0.000001']],
        ]];

        $models = $this->driver([new Response(200, [], json_encode($listing))])
            ->fetchModels('https://llm.test/v1', 'key', 5);

        $this->assertCount(1, $models, 'A model that is not ready is left out.');

        $model = $models[0];
        $this->assertSame('greenference/gemma-4-31b-it', $model['model_id']);
        $this->assertSame(0.1485, $model['input_price_per_1m']);
        $this->assertSame(0.42, $model['output_price_per_1m']);
        $this->assertSame(0.05775, $model['cache_read_price_per_1m']);
        $this->assertSame(0.0, $model['cache_write_price_per_1m'], 'A listed price of 0 is a real price, not a missing one.');
        $this->assertSame(262144, $model['context_window']);
        $this->assertSame(262141, $model['capabilities']['max_output_tokens']);
        $this->assertTrue($model['capabilities']['tools']);
        $this->assertSame('fp8', $model['capabilities']['quantization']);
    }

    public function test_cost_prices_cached_tokens_at_their_own_price(): void
    {
        $model = $this->model([
            'input_price_per_1m' => 1,
            'output_price_per_1m' => 5,
            'cache_read_price_per_1m' => 0.1,
            'cache_write_price_per_1m' => 1.25,
        ]);

        // 1M input of which 600k read from cache and 100k written; 200k output.
        $cost = LlmModelsService::calculateCost($model, 1_000_000, 200_000, 600_000, 100_000);

        $this->assertSame(round(0.3 * 1 + 0.6 * 0.1 + 0.1 * 1.25 + 0.2 * 5, 8), $cost);
    }

    public function test_cached_tokens_cost_input_price_when_no_cache_price_is_set(): void
    {
        $model = $this->model(['input_price_per_1m' => 1, 'output_price_per_1m' => 5]);

        $this->assertSame(1.0, LlmModelsService::calculateCost($model, 1_000_000, 0, 500_000, 0));
    }
}
