<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use NextDeveloper\AI\Http\Transformers\LlmModelsTransformer;
use PHPUnit\Framework\TestCase;

class ModelsTransformerMaskTest extends TestCase
{
    private const PAYLOAD = ['id' => 'x', 'model_id' => 'greenference/glm-5.3-flash'];

    public function test_everybody_gets_the_public_name_instead_of_the_upstream_id(): void
    {
        $out = LlmModelsTransformer::maskUpstream(self::PAYLOAD, 'plusclouds/glm-5.3-flash');

        $this->assertSame('plusclouds/glm-5.3-flash', $out['model_id']);
        $this->assertSame('plusclouds/glm-5.3-flash', $out['public_id']);
        $this->assertStringNotContainsString('greenference', json_encode($out));
    }

    public function test_models_without_a_public_id_are_left_alone(): void
    {
        $out = LlmModelsTransformer::maskUpstream(self::PAYLOAD, null);

        $this->assertSame('greenference/glm-5.3-flash', $out['model_id']);
    }
}
