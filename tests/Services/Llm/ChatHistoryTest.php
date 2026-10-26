<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\LlmChatHistoryService;
use NextDeveloper\AI\Services\LlmChatStorageBilling;
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

    public function test_storage_fee_is_charged_on_stored_megabytes_per_hour(): void
    {
        // 10 MB at 0.002 per MB-hour.
        $this->assertSame('0.02000000', LlmChatStorageBilling::amountFor(10 * 1048576, '0.002'));
        // 1 KB at the same price is a fraction of a cent; rounded down, never up.
        $this->assertSame('0.00000195', LlmChatStorageBilling::amountFor(1024, '0.002'));
        $this->assertSame('0.00000000', LlmChatStorageBilling::amountFor(0, '0.002'));
        $this->assertSame('0.00000000', LlmChatStorageBilling::amountFor(-5, '0.002'));
    }
}
