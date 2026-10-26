<?php

namespace NextDeveloper\AI\Http\Controllers\LlmChats;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use NextDeveloper\AI\Services\Llm\LlmProxyException;
use NextDeveloper\AI\Services\LlmChatHistoryService;

/**
 * /ai/llm/chats: the chat history of the logged-in user (IAM token), every call scoped to that user. The controller
 * validates and delegates; the logic lives in LlmChatHistoryService.
 */
class LlmChatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->respond(fn () => LlmChatHistoryService::list($request->only(['search', 'page', 'per_page'])));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            return LlmChatHistoryService::create($this->validated($request, [
                'title'       => 'nullable|string|max:200',
                'model'       => 'nullable|string|max:200',
                'system'      => 'nullable|string',
                'temperature' => 'nullable|numeric|min:0|max:2',
                'max_tokens'  => 'nullable|integer|min:1',
            ]));
        }, 201);
    }

    public function show(string $chat): JsonResponse
    {
        return $this->respond(fn () => LlmChatHistoryService::show($chat));
    }

    public function update(Request $request, string $chat): JsonResponse
    {
        return $this->respond(function () use ($request, $chat) {
            return LlmChatHistoryService::update($chat, $this->validated($request, [
                'title'       => 'nullable|string|max:200',
                'model'       => 'nullable|string|max:200',
                'system'      => 'nullable|string',
                'temperature' => 'nullable|numeric|min:0|max:2',
                'max_tokens'  => 'nullable|integer|min:1',
            ]));
        });
    }

    public function destroy(string $chat): JsonResponse
    {
        return $this->respond(function () use ($chat) {
            LlmChatHistoryService::delete($chat);

            return null;
        });
    }

    public function destroyAll(): JsonResponse
    {
        return $this->respond(fn () => ['deleted' => LlmChatHistoryService::clearAll()]);
    }

    public function storeMessage(Request $request, string $chat): JsonResponse
    {
        return $this->respond(function () use ($request, $chat) {
            return LlmChatHistoryService::appendMessage($chat, $this->validated($request, [
                'role'      => 'required|string|in:user,assistant',
                'content'   => 'required|string',
                'reasoning' => 'nullable|string',
                'meta'      => 'nullable|array',
            ]));
        }, 201);
    }

    public function destroyMessage(string $chat, string $message): JsonResponse
    {
        return $this->respond(function () use ($chat, $message) {
            LlmChatHistoryService::deleteMessage($chat, $message);

            return null;
        });
    }

    public function settings(): JsonResponse
    {
        return $this->respond(fn () => LlmChatHistoryService::settings());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $data = $this->validated($request, ['keep_history' => 'required|boolean', 'delete_existing' => 'nullable|boolean']);

            return LlmChatHistoryService::updateSettings((bool) $data['keep_history'], (bool) ($data['delete_existing'] ?? false));
        });
    }

    /**
     * Runs the action and answers in the proxy's error shape ({error: {message, type, code}}) when it refuses.
     */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            $result = $action();

            return $result === null ? new JsonResponse(null, 204) : new JsonResponse($result, $status);
        } catch (LlmProxyException $e) {
            return new JsonResponse($e->toArray(), $e->status());
        }
    }

    /**
     * @param array<string, string> $rules
     * @return array<string, mixed>
     *
     * @throws LlmProxyException 422 with the first failing field
     */
    private function validated(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new LlmProxyException($validator->errors()->first(), 422, 'invalid_request_error', 'invalid_request');
        }

        return $validator->validated();
    }
}
