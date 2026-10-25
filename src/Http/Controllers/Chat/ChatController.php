<?php

namespace NextDeveloper\AI\Http\Controllers\Chat;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use NextDeveloper\AI\Http\Requests\Chat\ChatRequest;
use NextDeveloper\AI\Services\Llm\LlmProxyException;
use NextDeveloper\AI\Services\Llm\LlmProxyService;
use NextDeveloper\AI\Services\LlmChatService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * POST /ai/llm/chat: chat completions for logged-in users (IAM token), same body and error shape as
 * /ai/v1/chat/completions. Controller only delegates; the logic lives in LlmChatService/LlmProxyService.
 */
class ChatController extends Controller
{
    public function chat(ChatRequest $request)
    {
        $key = LlmChatService::keyForCurrentUser();
        $payload = $request->all();

        try {
            if (!empty($payload['stream'])) {
                // Resolve and pre-check before the stream starts, so errors still get a proper HTTP status.
                LlmProxyService::preflight($payload, $key);

                return new StreamedResponse(function () use ($payload, $key) {
                    $write = function (string $chunk) {
                        echo $chunk;
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    };

                    // The upstream [DONE] is held back so the meta event can come before it.
                    $meta = LlmProxyService::stream($payload, $key, function (string $chunk) use ($write) {
                        if (trim($chunk) !== 'data: [DONE]') {
                            $write($chunk);
                        }
                    });

                    if ($meta) {
                        $write("event: ai_meta\ndata: " . json_encode(LlmProxyService::publicMeta($meta)) . "\n\n");
                    }

                    $write("data: [DONE]\n\n");
                }, 200, [
                    'Content-Type'      => 'text/event-stream',
                    'Cache-Control'     => 'no-cache',
                    'X-Accel-Buffering' => 'no',
                ]);
            }

            [$status, $body, $meta] = LlmProxyService::chat($payload, $key);

            $body['ai_meta'] = LlmProxyService::publicMeta($meta);

            return new JsonResponse($body, $status, [
                'X-Request-Id' => $meta['request_id'],
                'X-Cost'       => (string) $meta['cost'],
                'X-Latency-Ms' => (string) $meta['latency_ms'],
            ]);
        } catch (LlmProxyException $e) {
            return new JsonResponse($e->toArray(), $e->status());
        }
    }
}
