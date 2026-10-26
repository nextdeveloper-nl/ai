<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Support\Facades\DB;

/**
 * How much encrypted chat text accounts keep. The storage fee is not charged by the AI package: the billing service
 * meters these bytes hourly into accounting_usage_events.
 */
class LlmChatStorage
{
    public static function storedBytes(int $accountId): int
    {
        return (int) self::query()->where('c.iam_account_id', $accountId)->sum('m.content_bytes');
    }

    /**
     * Stored bytes of every account that keeps chats, for the billing service's hourly emitter.
     *
     * @return array<int, int> iam_account_id => bytes
     */
    public static function bytesByAccount(): array
    {
        return self::query()
            ->groupBy('c.iam_account_id')
            ->selectRaw('c.iam_account_id, sum(m.content_bytes) as bytes')
            ->pluck('bytes', 'iam_account_id')
            ->map(fn ($bytes) => (int) $bytes)
            ->all();
    }

    private static function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table('ai_llm_chat_messages as m')
            ->join('ai_llm_chats as c', 'c.id', '=', 'm.ai_llm_chat_id')
            ->whereNull('c.deleted_at');
    }
}
