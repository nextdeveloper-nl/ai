<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NextDeveloper\AI\Database\Models\LlmChatMessages;
use NextDeveloper\AI\Database\Models\LlmChats;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\Llm\LlmProxyException;
use NextDeveloper\Accounting\Helpers\AccountingHelper;
use NextDeveloper\Accounting\Helpers\CreditHelper;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Server-side chat history of the logged-in user. Every query is scoped to the session user, so a user can never
 * reach the chats of anybody else, not even in the same account. Message text and the system prompt are encrypted.
 * Whether a chat is kept is decided when it is created (see create()); the storage fee is metered by the billing
 * service from LlmChatStorage, not charged here.
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

    /**
     * The account's default for new chats: ai_accounts.configuration -> llm_chat.keep_history, off when unset.
     */
    public static function accountDefault(int $accountId): bool
    {
        return self::keepFromConfiguration(DB::table('ai_accounts')->where('iam_account_id', $accountId)->whereNull('deleted_at')->value('configuration'));
    }

    public static function keepFromConfiguration(mixed $configuration): bool
    {
        $configuration = is_string($configuration) ? json_decode($configuration, true) : $configuration;

        return (bool) ($configuration['llm_chat']['keep_history'] ?? false);
    }

    /**
     * @return array{keep_history: bool, stored_bytes: int}
     */
    public static function settings(): array
    {
        $owner = self::owner();

        return ['keep_history' => self::accountDefault($owner['account']), 'stored_bytes' => LlmChatStorage::storedBytes($owner['account'])];
    }

    /**
     * Sets the account default for new chats. A chat can still say otherwise when it is created.
     *
     * @return array{keep_history: bool, stored_bytes: int}
     *
     * @throws NotAllowedException
     */
    public static function updateSettings(bool $keepHistory): array
    {
        $owner = self::owner();

        throw_if(
            !UserHelper::hasRole('ai-manager') && !UserHelper::hasRole('ai-admin'),
            new NotAllowedException('You need the ai-manager role to change the chat history default of the account.')
        );

        DB::transaction(function () use ($owner, $keepHistory) {
            // Lock the row: the assistant settings of the application live in the same column.
            $row = DB::table('ai_accounts')->where('iam_account_id', $owner['account'])->whereNull('deleted_at')->lockForUpdate()->first();
            $configuration = $row ? (json_decode((string) $row->configuration, true) ?: []) : [];
            $configuration['llm_chat']['keep_history'] = $keepHistory;

            if ($row) {
                DB::table('ai_accounts')->where('id', $row->id)->update(['configuration' => json_encode($configuration), 'updated_at' => now()]);
            } else {
                DB::table('ai_accounts')->insert([
                    'uuid' => (string) Str::uuid(), 'iam_account_id' => $owner['account'], 'configuration' => json_encode($configuration),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return self::settings();
    }

    /**
     * Storing needs credit, because the account pays for the storage. Reading, renaming and deleting always work,
     * so nobody is locked out of their own data.
     *
     * @throws LlmProxyException
     */
    public static function assertCanStore(): void
    {
        $account = AccountingHelper::getAccountFromIamAccountId(self::owner()['account']);

        if (!$account || CreditHelper::getCredit($account) <= 0) {
            throw new LlmProxyException(
                'Storing chats is paid by the hour and your account has no credit. Add credit to keep new chats; the ones you already have stay readable.',
                402, 'insufficient_quota', 'chat_storage_no_credit'
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
        $owner = self::owner();

        // Kept or not is decided here, when the chat starts: the request says so, else the account default does.
        $keep = array_key_exists('keep', $data) && $data['keep'] !== null ? (bool) $data['keep'] : self::accountDefault($owner['account']);

        if (!$keep) {
            return ['saved' => false];
        }

        self::assertCanStore();

        $chat = LlmChats::create([
            'iam_user_id'      => $owner['user'],
            'iam_account_id'   => $owner['account'],
            'title'            => isset($data['title']) ? Str::limit((string) $data['title'], 200, '') : null,
            'model'            => $data['model'] ?? null,
            'system_encrypted' => filled($data['system'] ?? null) ? LlmKeyHelper::encrypt((string) $data['system']) : null,
            'temperature'      => $data['temperature'] ?? null,
            'max_tokens'       => $data['max_tokens'] ?? null,
        ]);

        return ['saved' => true] + self::present($chat) + ['message_count' => 0, 'last_message' => null, 'messages' => []];
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
