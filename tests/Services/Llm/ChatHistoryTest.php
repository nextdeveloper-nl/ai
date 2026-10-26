<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\LlmChatHistoryService;
use PHPUnit\Framework\TestCase;

class ChatHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        $app->instance('encrypter', new Encrypter(str_repeat('k', 32), 'AES-256-CBC'));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    public function test_meta_keeps_only_whitelisted_scalars(): void
    {
        $meta = LlmChatHistoryService::sanitizeMeta([
            'model' => 'plusclouds/glm-5.3-flash',
            'requested' => 'generic',
            'promptTokens' => 12,
            'cost' => 0.0001,
            'servedByFallback' => false,
            'provider' => 'greenference',
            'upstreamModel' => 'greenference/glm-5.3-flash',
            'attempts' => [['provider' => 'greenference']],
            'error' => ['message' => 'greenference said: <your conversation>', 'code' => 'upstream_error'],
        ]);

        $this->assertSame(['model', 'requested', 'promptTokens', 'cost', 'servedByFallback', 'error'], array_keys($meta));
        $this->assertSame('upstream_error', $meta['error'], 'Only the error code is kept, never its text.');
        $this->assertStringNotContainsStringIgnoringCase('greenference', json_encode($meta));
    }

    public function test_a_text_error_is_shortened_and_nested_values_are_dropped(): void
    {
        $meta = LlmChatHistoryService::sanitizeMeta(['error' => str_repeat('x', 500), 'model' => ['nested' => 'x']]);

        $this->assertSame(60, strlen($meta['error']));
        $this->assertArrayNotHasKey('model', $meta);
    }

    public function test_text_is_stored_encrypted_and_reads_back(): void
    {
        $encrypted = LlmKeyHelper::encrypt('my secret conversation');

        $this->assertStringNotContainsString('secret', $encrypted);
        $this->assertSame('my secret conversation', LlmKeyHelper::decrypt($encrypted));
    }

    public function test_search_ignores_case(): void
    {
        $this->assertTrue(LlmChatHistoryService::contains('Merhaba DÜNYA', 'dünya'));
        $this->assertFalse(LlmChatHistoryService::contains('hello', 'world'));
    }

    public function test_the_account_default_comes_from_its_configuration(): void
    {
        $this->assertTrue(LlmChatHistoryService::keepFromConfiguration('{"llm_chat":{"keep_history":true},"other":1}'));
        $this->assertTrue(LlmChatHistoryService::keepFromConfiguration(['llm_chat' => ['keep_history' => true]]));
        $this->assertFalse(LlmChatHistoryService::keepFromConfiguration('{"llm_chat":{"keep_history":false}}'));
        $this->assertFalse(LlmChatHistoryService::keepFromConfiguration('{"assistants":{}}'), 'Off unless the account turned it on.');
        $this->assertFalse(LlmChatHistoryService::keepFromConfiguration(null));
        $this->assertFalse(LlmChatHistoryService::keepFromConfiguration('not json'));
    }
}
