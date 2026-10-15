<?php

namespace NextDeveloper\AI\Tests\Services\Llm;

use NextDeveloper\AI\Services\Llm\AnthropicMessagesAdapter;
use NextDeveloper\AI\Services\Llm\AnthropicStreamTranslator;
use PHPUnit\Framework\TestCase;

class AnthropicMessagesAdapterTest extends TestCase
{
    public function test_request_maps_system_messages_and_parameters(): void
    {
        $payload = AnthropicMessagesAdapter::toOpenAiRequest([
            'model'          => 'litellm/crm-general',
            'system'         => [['type' => 'text', 'text' => 'Be brief.']],
            'max_tokens'     => 50,
            'stop_sequences' => ['END'],
            'messages'       => [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hello']]],
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Again']]],
            ],
        ]);

        self::assertSame('system', $payload['messages'][0]['role']);
        self::assertSame('Be brief.', $payload['messages'][0]['content']);
        self::assertSame('Hello', $payload['messages'][2]['content']);
        self::assertSame('Again', $payload['messages'][3]['content']);
        self::assertSame(50, $payload['max_tokens']);
        self::assertSame(['END'], $payload['stop']);
    }

    public function test_tool_use_and_tool_result_round_trip_to_openai_shape(): void
    {
        $payload = AnthropicMessagesAdapter::toOpenAiRequest([
            'model'       => 'm',
            'max_tokens'  => 10,
            'tools'       => [['name' => 'weather', 'description' => 'Get weather', 'input_schema' => ['type' => 'object']]],
            'tool_choice' => ['type' => 'any'],
            'messages'    => [
                ['role' => 'assistant', 'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'weather', 'input' => ['city' => 'Rome']]]],
                ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => '21C']]],
            ],
        ]);

        self::assertSame('required', $payload['tool_choice']);
        self::assertSame('weather', $payload['tools'][0]['function']['name']);
        self::assertSame('toolu_1', $payload['messages'][0]['tool_calls'][0]['id']);
        self::assertSame('{"city":"Rome"}', $payload['messages'][0]['tool_calls'][0]['function']['arguments']);
        self::assertSame(['role' => 'tool', 'tool_call_id' => 'toolu_1', 'content' => '21C'], $payload['messages'][1]);
    }

    public function test_image_block_becomes_data_url(): void
    {
        $payload = AnthropicMessagesAdapter::toOpenAiRequest([
            'model' => 'm', 'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'AAA']],
                ['type' => 'text', 'text' => 'what is this'],
            ]]],
        ]);

        self::assertSame('data:image/png;base64,AAA', $payload['messages'][0]['content'][0]['image_url']['url']);
    }

    public function test_response_maps_text_usage_and_stop_reason(): void
    {
        $message = AnthropicMessagesAdapter::fromOpenAiResponse([
            'id'      => 'chatcmpl-1',
            'choices' => [['finish_reason' => 'length', 'message' => ['content' => 'Hi there']]],
            'usage'   => ['prompt_tokens' => 7, 'completion_tokens' => 3],
        ], 'litellm/crm-general');

        self::assertSame('message', $message['type']);
        self::assertSame('litellm/crm-general', $message['model']);
        self::assertSame([['type' => 'text', 'text' => 'Hi there']], $message['content']);
        self::assertSame('max_tokens', $message['stop_reason']);
        self::assertSame(['input_tokens' => 7, 'output_tokens' => 3], $message['usage']);
        self::assertStringStartsWith('msg_', $message['id']);
    }

    public function test_response_maps_tool_calls_to_tool_use(): void
    {
        $message = AnthropicMessagesAdapter::fromOpenAiResponse([
            'choices' => [['finish_reason' => 'tool_calls', 'message' => ['content' => null, 'tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Rome"}']],
            ]]]],
        ], 'm');

        self::assertSame('tool_use', $message['stop_reason']);
        self::assertSame(['type' => 'tool_use', 'id' => 'call_1', 'name' => 'weather', 'input' => ['city' => 'Rome']], $message['content'][0]);
    }

    public function test_error_types_follow_http_status(): void
    {
        self::assertSame('authentication_error', AnthropicMessagesAdapter::errorBody('x', 401)['error']['type']);
        self::assertSame('not_found_error', AnthropicMessagesAdapter::errorBody('x', 404)['error']['type']);
        self::assertSame('rate_limit_error', AnthropicMessagesAdapter::errorBody('x', 429)['error']['type']);
        self::assertSame('api_error', AnthropicMessagesAdapter::errorBody('x', 502)['error']['type']);
        self::assertSame('invalid_request_error', AnthropicMessagesAdapter::errorBody('x', 400)['error']['type']);
    }

    public function test_stream_translates_text_chunks_into_anthropic_events(): void
    {
        $t = new AnthropicStreamTranslator('litellm/crm-general');
        $chunk = fn (array $d) => 'data: ' . json_encode($d) . "\n\n";

        $out = array_merge(
            $t->feed($chunk(['id' => 'c1', 'choices' => [['delta' => ['role' => 'assistant']]]])),
            $t->feed($chunk(['id' => 'c1', 'choices' => [['delta' => ['content' => 'Hel']]]])),
            $t->feed($chunk(['id' => 'c1', 'choices' => [['delta' => ['content' => 'lo']]]])),
            $t->feed($chunk(['id' => 'c1', 'choices' => [['delta' => [], 'finish_reason' => 'stop']]])),
            $t->feed($chunk(['id' => 'c1', 'choices' => [], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2]])),
            $t->feed("data: [DONE]\n\n"),
            $t->finish()
        );

        $events = array_map(fn ($e) => explode("\n", $e)[0], $out);

        self::assertSame([
            'event: message_start',
            'event: content_block_start',
            'event: content_block_delta',
            'event: content_block_delta',
            'event: content_block_stop',
            'event: message_delta',
            'event: message_stop',
        ], $events);
        self::assertStringContainsString('"text":"Hel"', $out[2]);
        self::assertStringContainsString('"stop_reason":"end_turn"', $out[5]);
        self::assertStringContainsString('"output_tokens":2', $out[5]);
    }

    public function test_stream_translates_tool_call_chunks(): void
    {
        $t = new AnthropicStreamTranslator('m');
        $chunk = fn (array $d) => 'data: ' . json_encode($d) . "\n\n";

        $out = array_merge(
            $t->feed($chunk(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_1', 'function' => ['name' => 'weather', 'arguments' => '']]]]]]])),
            $t->feed($chunk(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => '{"city":']]]]]]])),
            $t->feed($chunk(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => '"Rome"}']]]]]]])),
            $t->feed($chunk(['choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]])),
            $t->finish()
        );

        $joined = implode('', $out);
        self::assertStringContainsString('"type":"tool_use"', $joined);
        self::assertStringContainsString('"partial_json":"{\"city\":"', $joined);
        self::assertStringContainsString('"stop_reason":"tool_use"', $joined);
    }

    public function test_stream_error_event_and_empty_stream_still_close_cleanly(): void
    {
        $t = new AnthropicStreamTranslator('m');

        $error = $t->feed('data: ' . json_encode(['error' => ['message' => 'boom']]) . "\n\n");
        self::assertStringStartsWith('event: error', $error[0]);

        $closing = $t->finish();
        self::assertStringStartsWith('event: message_start', $closing[0]);
        self::assertStringStartsWith('event: message_stop', end($closing));
        self::assertSame([], $t->finish());
    }
}
