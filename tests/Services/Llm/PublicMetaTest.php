<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use NextDeveloper\AI\Services\Llm\LlmProxyService;
use PHPUnit\Framework\TestCase;

class PublicMetaTest extends TestCase
{
    public function test_the_model_that_answered_is_its_public_id_not_the_alias(): void
    {
        $meta = LlmProxyService::publicMeta([
            'alias' => 'my-alias', 'requested_model' => 'my-alias', 'provider' => 'greenference',
            'model' => 'greenference/qwen3-8b', 'public_model' => 'plusclouds/qwen3-8b', 'attempts' => [],
        ]);

        $this->assertSame('plusclouds/qwen3-8b', $meta['model']);
        $this->assertArrayNotHasKey('provider', $meta);
        $this->assertStringNotContainsString('greenference', json_encode($meta));
    }

    public function test_without_a_public_id_the_requested_name_is_shown(): void
    {
        $meta = LlmProxyService::publicMeta(['requested_model' => 'my-alias', 'model' => 'greenference/qwen3-8b', 'public_model' => null, 'attempts' => []]);

        $this->assertSame('my-alias', $meta['model']);
    }
}
