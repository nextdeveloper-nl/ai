<?php

namespace NextDeveloper\AI\Services;

use NextDeveloper\AI\Database\Models\LlmAliasHistory;
use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Database\Models\LlmUsageLogs;
use NextDeveloper\AI\Services\AbstractServices\AbstractLlmAliasesService;
use NextDeveloper\AI\Services\Llm\LlmSpendLimitService;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * This class is responsible from managing the data for LlmAliases
 *
 * Class LlmAliasesService.
 *
 * @package NextDeveloper\AI\Database\Models
 */
class LlmAliasesService extends AbstractLlmAliasesService
{
    /**
     * Fields whose changes are written to the alias history.
     */
    private const TRACKED = [
        'alias', 'target_provider', 'target_model', 'is_active', 'description', 'fallbacks',
        'fallback_on_error', 'fallback_on_rate_limit', 'fallback_on_timeout', 'fallback_timeout_seconds',
        'temperature', 'max_tokens', 'system_prompt', 'monthly_spend_limit',
    ];

    public static function create(array $data)
    {
        $model = parent::create($data);

        self::recordHistory($model, 'created', self::diff([], $model->only(self::TRACKED)));

        return $model;
    }

    public static function update($id, array $data)
    {
        $before = LlmAliases::where('uuid', $id)->first()?->only(self::TRACKED) ?? [];
        $model = parent::update($id, $data);
        $changes = self::diff($before, $model->only(self::TRACKED));

        if ($changes) {
            self::recordHistory($model, 'updated', $changes);
        }

        return $model;
    }

    public static function delete($id)
    {
        $model = LlmAliases::where('uuid', $id)->first();
        $result = parent::delete($id);

        if ($model) {
            self::recordHistory($model, 'deleted', []);
        }

        return $result;
    }

    /**
     * Change history of one alias, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function history(string $ref): array
    {
        $alias = self::getByRef($ref) ?? throw new NotAllowedException('Alias not found or not allowed.');

        return LlmAliasHistory::where('ai_llm_alias_id', $alias->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (LlmAliasHistory $row) {
                $user = $row->iam_user_id
                    ? \NextDeveloper\IAM\Database\Models\Users::withoutGlobalScopes()->find($row->iam_user_id)
                    : null;

                return [
                    'id'         => $row->uuid,
                    'action'     => $row->action,
                    'alias'      => $row->alias,
                    'changes'    => $row->changes ?? [],
                    'iam_user_id' => $user?->uuid,
                    'user_name'  => $user?->name,
                    'created_at' => $row->created_at,
                ];
            })
            ->all();
    }

    /**
     * Usage of one alias: last 24 hours and this month.
     *
     * @return array<string, mixed>
     */
    public static function stats(string $ref): array
    {
        $alias = self::getByRef($ref) ?? throw new NotAllowedException('Alias not found or not allowed.');

        $logs = fn () => LlmUsageLogs::withoutGlobalScopes()
            ->where('iam_account_id', $alias->iam_account_id)
            ->where(fn ($q) => $q->where('alias', $alias->alias)->orWhere(fn ($q2) => $q2->whereNull('alias')->where('requested_model', $alias->alias)));

        $day = $logs()->where('created_at', '>=', now()->subDay());

        return [
            'calls_24h'                => (clone $day)->count(),
            'failed_24h'               => (clone $day)->where('status', 'failed')->count(),
            'served_by_fallback_24h'   => (clone $day)->where('served_by_fallback', true)->count(),
            'api_keys_used_24h'        => (clone $day)->whereNotNull('ai_llm_api_key_id')->distinct()->count('ai_llm_api_key_id'),
            'spent_this_month'         => round(LlmSpendLimitService::spentThisMonth($alias->iam_account_id, $alias->alias), 8),
            'monthly_spend_limit'      => $alias->monthly_spend_limit !== null ? (float) $alias->monthly_spend_limit : null,
            'last_used_at'             => $logs()->max('created_at'),
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (self::TRACKED as $field) {
            $from = $before[$field] ?? null;
            $to = $after[$field] ?? null;

            if ($from != $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $changes
     */
    private static function recordHistory(LlmAliases $alias, string $action, array $changes): void
    {
        LlmAliasHistory::create([
            'ai_llm_alias_id' => $alias->id,
            'alias'           => $alias->alias,
            'action'          => $action,
            'changes'         => $changes ?: null,
            'iam_account_id'  => $alias->iam_account_id,
            'iam_user_id'     => UserHelper::me()?->id,
            'created_at'      => now(),
        ]);
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
