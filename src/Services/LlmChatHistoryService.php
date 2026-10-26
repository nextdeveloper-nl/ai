<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NextDeveloper\AI\Database\Models\LlmChatMessages;
use NextDeveloper\AI\Database\Models\LlmChats;
use NextDeveloper\AI\Database\Models\LlmChatSettings;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\Llm\LlmProxyException;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Server-side chat history of the logged-in user. Every query is scoped to the session user, so a user can never
 * reach the chats of anybody else, not even in the same account. Message text and the system prompt are encrypted;
 * what the account keeps is paid for per hour (see LlmChatStorageBilling).
 */
class LlmChatHistoryService
{
    /** Meta fields a client may store; anything else (provider, upstream model, attempts, ...) is dropped. */
    private const META_KEYS = ['model', 'requested', 'promptTokens', 'completionTokens', 'latencyMs', 'servedByFallback', 'cost', 'requestId', 'error'];

    /**
     * @return array{user: int, account: int}
     */
    public static function owner(): array
    {
        LlmChatService::assertAiRole();

        return ['user' => UserHelper::me()->id, 'account' => UserHelper::currentAccount()->id];
    }

    public static function keepHistory(int $userId): bool
    {
        return (bool) LlmChatSettings::where('iam_user_id', $userId)->value('keep_history');
    }

    /**
     * @return array{keep_history: bool, storage_unpaid: bool, price_per_mb_hour: string, stored_bytes: int}
     */
    public static function settings(): array
    {
        $owner = self::owner();

        return [
            'keep_history'      => self::keepHistory($owner['user']),
            'storage_unpaid'    => LlmChatStorageBilling::isUnpaid($owner['account']),
            'price_per_mb_hour' => (string) config('ai.llm.chat_storage_price_per_mb_hour', '0'),
            'stored_bytes'      => LlmChatStorageBilling::storedBytes($owner['account']),
        ];
    }

    /**
     * Turns history on or off. Turning it off can also delete what is kept, so the fee stops.
     *
     * @return array<string, mixed>
     */
    public static function updateSettings(bool $keepHistory, bool $deleteExisting = false): array
    {
        $owner = self::owner();

        LlmChatSettings::updateOrCreate(
            ['iam_user_id' => $owner['user']],
            ['iam_account_id' => $owner['account'], 'keep_history' => $keepHistory]
        );

        if (!$keepHistory && $deleteExisting) {
            self::clearAll();
        }

        return self::settings();
    }

    /**
     * New chats and messages need history to be switched on and the storage fee to be paid. Reading, renaming and
     * deleting always work, so nobody is locked out of their own data.
     *
     * @throws LlmProxyException
     */
    public static function assertCanStore(): void
    {
        $owner = self::owner();

        if (!self::keepHistory($owner['user'])) {
            throw new LlmProxyException(
                'Chat history is switched off. Turn on "keep chat history" in your chat settings to store chats on the server.',
                409, 'invalid_request_error', 'chat_history_disabled'
            );
        }

        if (LlmChatStorageBilling::isUnpaid($owner['account'])) {
            throw new LlmProxyException(
                'Your account could not pay the chat storage fee, so stored chats are read-only. Add credit to store new messages; unpaid chats are deleted after the grace period.',
                402, 'insufficient_quota', 'chat_storage_unpaid'
            );
        }
    }

    /**
     * @param array{search?: string|null, page?: int, per_page?: int} $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public static function list(array $filters): array
    {
        $owner = self::owner();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = LlmChats::where('iam_user_id', $owner['user'])->orderByDesc('updated_at')->orderByDesc('id');

        if ($search !== '') {
            $ids = self::matchingChatIds($owner['user'], $search);
            $query->where(fn ($q) => $q->where('title', 'ilike', '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%')->orWhereIn('id', $ids));
        }

        $total = (clone $query)->count();
        $chats = $query->forPage($page, $perPage)->get();
        $summary = self::summaries($chats->pluck('id')->all());

        return [
            'data' => $chats->map(fn (LlmChats $chat) => self::present($chat) + $summary[$chat->id])->all(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function create(array $data): array
    {
        self::assertCanStore();
        $owner = self::owner();

        $chat = LlmChats::create([
            'iam_user_id'      => $owner['user'],
            'iam_account_id'   => $owner['account'],
            'title'            => isset($data['title']) ? Str::limit((string) $data['title'], 200, '') : null,
            'model'            => $data['model'] ?? null,
            'system_encrypted' => filled($data['system'] ?? null) ? LlmKeyHelper::encrypt((string) $data['system']) : null,
            'temperature'      => $data['temperature'] ?? null,
            'max_tokens'       => $data['max_tokens'] ?? null,
        ]);

        return self::present($chat) + ['message_count' => 0, 'last_message' => null, 'messages' => []];
    }

    /**
     * @return array<string, mixed>
     */
    public static function show(string $uuid): array
    {
        $chat = self::find($uuid);
        $messages = LlmChatMessages::where('ai_llm_chat_id', $chat->id)->orderBy('position')->get();

        return self::present($chat) + [
            'message_count' => $messages->count(),
            'messages'      => $messages->map(fn (LlmChatMessages $m) => self::presentMessage($m))->all(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function update(string $uuid, array $data): array
    {
        $chat = self::find($uuid);

        foreach (['title' => 'title', 'model' => 'model', 'temperature' => 'temperature', 'max_tokens' => 'max_tokens'] as $field => $column) {
            if (array_key_exists($field, $data)) {
                $chat->{$column} = $field === 'title' && $data[$field] !== null ? Str::limit((string) $data[$field], 200, '') : $data[$field];
            }
        }

        if (array_key_exists('system', $data)) {
            $chat->system_encrypted = filled($data['system']) ? LlmKeyHelper::encrypt((string) $data['system']) : null;
        }

        $chat->save();

        return self::present($chat);
    }

    public static function delete(string $uuid): void
    {
        $chat = self::find($uuid);

        // The text goes now (it is what the fee is charged on); the chat row stays soft deleted.
        LlmChatMessages::where('ai_llm_chat_id', $chat->id)->delete();
        $chat->delete();
    }

    /**
     * Deletes every chat of the user.
     */
    public static function clearAll(): int
    {
        $owner = self::owner();
        $ids = LlmChats::where('iam_user_id', $owner['user'])->pluck('id');

        LlmChatMessages::whereIn('ai_llm_chat_id', $ids)->delete();

        return LlmChats::whereIn('id', $ids)->delete();
    }

    /**
     * Appends one message; the position is the next free one, so two quick appends never overwrite each other.
     *
     * @param array<string, mixed> $data role, content, reasoning (optional), meta (optional)
     * @return array<string, mixed>
     */
    public static function appendMessage(string $uuid, array $data): array
    {
        self::assertCanStore();
        $chat = self::find($uuid);

        return DB::transaction(function () use ($chat, $data) {
            // Lock the chat row so concurrent appends take turns.
            LlmChats::where('id', $chat->id)->lockForUpdate()->first();

            $content = LlmKeyHelper::encrypt((string) $data['content']);
            $reasoning = filled($data['reasoning'] ?? null) ? LlmKeyHelper::encrypt((string) $data['reasoning']) : null;

            $message = LlmChatMessages::create([
                'ai_llm_chat_id'      => $chat->id,
                'role'                => $data['role'],
                'content_encrypted'   => $content,
                'reasoning_encrypted' => $reasoning,
                'meta'                => self::sanitizeMeta((array) ($data['meta'] ?? [])),
                'position'            => (int) LlmChatMessages::where('ai_llm_chat_id', $chat->id)->max('position') + 1,
                'content_bytes'       => strlen($content) + strlen((string) $reasoning),
            ]);

            $chat->touch();

            return self::presentMessage($message);
        });
    }

    public static function deleteMessage(string $uuid, string $messageUuid): void
    {
        $chat = self::find($uuid);

        $deleted = LlmChatMessages::where('ai_llm_chat_id', $chat->id)->where('uuid', $messageUuid)->delete();

        if (!$deleted) {
            throw new LlmProxyException('That message does not exist in this chat.', 404, 'invalid_request_error', 'message_not_found');
        }

        $chat->touch();
    }

    /**
     * Keeps only the meta fields a client may store, so a client cannot park upstream details (or text) in it.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function sanitizeMeta(array $meta): array
    {
        $clean = array_intersect_key($meta, array_flip(self::META_KEYS));

        // Only the code of an error is kept: its text can quote the conversation or name the provider.
        if (isset($clean['error'])) {
            $clean['error'] = is_array($clean['error']) ? (string) ($clean['error']['code'] ?? 'error') : Str::limit((string) $clean['error'], 60, '');
        }

        foreach ($clean as $key => $value) {
            if (is_array($value) || is_object($value)) {
                unset($clean[$key]);
            }
        }

        return $clean;
    }

    /**
     * @throws LlmProxyException
     */
    private static function find(string $uuid): LlmChats
    {
        $owner = self::owner();

        // Someone else's chat looks exactly like one that does not exist.
        $chat = LlmChats::where('uuid', $uuid)->where('iam_user_id', $owner['user'])->first();

        if (!$chat) {
            throw new LlmProxyException('That chat does not exist.', 404, 'invalid_request_error', 'chat_not_found');
        }

        return $chat;
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(LlmChats $chat): array
    {
        return [
            'id'          => $chat->uuid,
            'title'       => $chat->title,
            'model'       => $chat->model,
            'system'      => $chat->system_encrypted ? LlmKeyHelper::decrypt($chat->system_encrypted) : null,
            'temperature' => $chat->temperature,
            'max_tokens'  => $chat->max_tokens,
            'created_at'  => $chat->created_at?->toIso8601String(),
            'updated_at'  => $chat->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function presentMessage(LlmChatMessages $message): array
    {
        return [
            'id'         => $message->uuid,
            'role'       => $message->role,
            'content'    => LlmKeyHelper::decrypt($message->content_encrypted),
            'reasoning'  => $message->reasoning_encrypted ? LlmKeyHelper::decrypt($message->reasoning_encrypted) : null,
            'meta'       => $message->meta ?? [],
            'position'   => $message->position,
            'created_at' => $message->created_at ? \Illuminate\Support\Carbon::parse($message->created_at)->toIso8601String() : null,
        ];
    }

    /**
     * Message count and a preview of the last message for each chat of a page, in one query.
     *
     * @param array<int, int> $chatIds
     * @return array<int, array{message_count: int, last_message: array<string, mixed>|null}>
     */
    private static function summaries(array $chatIds): array
    {
        $summary = array_fill_keys($chatIds, ['message_count' => 0, 'last_message' => null]);

        if (!$chatIds) {
            return $summary;
        }

        $counts = LlmChatMessages::whereIn('ai_llm_chat_id', $chatIds)->groupBy('ai_llm_chat_id')->selectRaw('ai_llm_chat_id, count(*) as total')->pluck('total', 'ai_llm_chat_id');
        $last = LlmChatMessages::whereIn('id', LlmChatMessages::whereIn('ai_llm_chat_id', $chatIds)->groupBy('ai_llm_chat_id')->selectRaw('max(id)'))->get();

        foreach ($counts as $chatId => $total) {
            $summary[$chatId]['message_count'] = (int) $total;
        }

        foreach ($last as $message) {
            $summary[$message->ai_llm_chat_id]['last_message'] = [
                'role'       => $message->role,
                'preview'    => Str::limit(LlmKeyHelper::decrypt($message->content_encrypted), 120),
                'created_at' => $message->created_at ? \Illuminate\Support\Carbon::parse($message->created_at)->toIso8601String() : null,
            ];
        }

        return $summary;
    }

    /**
     * Content is encrypted, so it cannot be searched in SQL: the newest messages of the user are decrypted and
     * matched here, up to ai.llm.chat_search_scan_limit of them.
     *
     * @return array<int, int>
     */
    private static function matchingChatIds(int $userId, string $needle): array
    {
        $limit = (int) config('ai.llm.chat_search_scan_limit', 2000);

        $messages = LlmChatMessages::join('ai_llm_chats', 'ai_llm_chats.id', '=', 'ai_llm_chat_messages.ai_llm_chat_id')
            ->where('ai_llm_chats.iam_user_id', $userId)
            ->whereNull('ai_llm_chats.deleted_at')
            ->orderByDesc('ai_llm_chat_messages.id')
            ->limit($limit)
            ->get(['ai_llm_chat_messages.ai_llm_chat_id', 'ai_llm_chat_messages.content_encrypted']);

        return $messages
            ->filter(fn ($m) => self::contains(LlmKeyHelper::decrypt($m->content_encrypted), $needle))
            ->pluck('ai_llm_chat_id')->unique()->values()->all();
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return mb_stripos($haystack, $needle) !== false;
    }
}
