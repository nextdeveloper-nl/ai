<?php

namespace NextDeveloper\AI\Http\Controllers\Proxy;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use NextDeveloper\AI\Services\Llm\AnthropicMessagesAdapter;
use NextDeveloper\AI\Services\Llm\AnthropicStreamTranslator;
use NextDeveloper\AI\Services\Llm\LlmProxyException;
use NextDeveloper\AI\Services\Llm\LlmProxyService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * OpenAI-compatible proxy endpoints, authenticated with an AI API key (see AuthenticateLlmApiKey).
 * Controller only validates and delegates; all logic lives in the services.
 */
class ProxyController extends Controller
{
    /**
     * POST /ai/v1/chat/completions
     */
    public function chat(Request $request)
    {
        $key = $request->attributes->get('ai_llm_api_key');
        $payload = $request->all();

        try {
            if (!empty($payload['stream'])) {
                // Resolve and pre-check before the stream starts, so errors still get a proper HTTP status.
                LlmProxyService::preflight($payload, $key);

                return new StreamedResponse(function () use ($payload, $key) {
                    LlmProxyService::stream($payload, $key, function (string $chunk) {
                        echo $chunk;
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    });
                }, 200, [
                    'Content-Type'      => 'text/event-stream',
                    'Cache-Control'     => 'no-cache',
                    'X-Accel-Buffering' => 'no',
                ]);
            }

            [$status, $body] = LlmProxyService::chat($payload, $key);

            return new JsonResponse($body, $status);
        } catch (LlmProxyException $e) {
            return new JsonResponse($e->toArray(), $e->status());
        }
    }

    /**
     * POST /ai/v1/messages (Anthropic Messages API, e.g. for the Anthropic SDKs and Claude Code).
     * Same pipeline as chat(); only the wire format is translated.
     */
    public function messages(Request $request)
    {
        $key = $request->attributes->get('ai_llm_api_key');
        $requested = (string) $request->input('model', '');

        try {
            if (empty($request->input('messages')) || $requested === '' || !$request->input('max_tokens')) {
                throw new LlmProxyException("'model', 'messages' and 'max_tokens' are required.", 400, 'invalid_request_error', 'invalid_request');
            }

            $payload = AnthropicMessagesAdapter::toOpenAiRequest($request->all());

            if (!empty($payload['stream'])) {
                LlmProxyService::preflight($payload, $key);
                $translator = new AnthropicStreamTranslator($requested);

                return new StreamedResponse(function () use ($payload, $key, $translator) {
                    $write = function (array $events) {
                        foreach ($events as $event) {
                            echo $event;
                        }
                        if ($events && ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    };

                    LlmProxyService::stream($payload, $key, fn (string $chunk) => $write($translator->feed($chunk)));
                    $write($translator->finish());
                }, 200, [
                    'Content-Type'      => 'text/event-stream',
                    'Cache-Control'     => 'no-cache',
                    'X-Accel-Buffering' => 'no',
                ]);
            }

            [$status, $body] = LlmProxyService::chat($payload, $key);

            if ($status >= 400) {
                return new JsonResponse(AnthropicMessagesAdapter::errorBody($body['error']['message'] ?? 'Upstream error.', $status), $status);
            }

            return new JsonResponse(AnthropicMessagesAdapter::fromOpenAiResponse($body, $requested));
        } catch (LlmProxyException $e) {
            return new JsonResponse(AnthropicMessagesAdapter::errorBody($e->getMessage(), $e->status()), $e->status());
        }
    }

    /**
     * GET /ai/v1/models
     */
    public function models(Request $request): JsonResponse
    {
        $key = $request->attributes->get('ai_llm_api_key');

        return new JsonResponse(LlmProxyService::listModels($key));
    }

    /**
     * POST /ai/v1/broadcast/{group}
     */
    public function broadcast(Request $request, string $group): JsonResponse
    {
        $key = $request->attributes->get('ai_llm_api_key');

        try {
            $payload = $request->all();
            unset($payload['model']);

            if (empty($payload['messages'])) {
                throw new LlmProxyException("'messages' is required.", 400, 'invalid_request_error', 'invalid_request');
            }

            return new JsonResponse(LlmProxyService::broadcast($group, $payload, $key));
        } catch (LlmProxyException $e) {
            return new JsonResponse($e->toArray(), $e->status());
        }
    }
}
