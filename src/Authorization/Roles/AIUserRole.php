<?php

namespace NextDeveloper\AI\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NextDeveloper\IAM\Authorization\Roles\AbstractRole;
use NextDeveloper\IAM\Authorization\Roles\IAuthorizationRole;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;

class AIUserRole extends AbstractRole implements IAuthorizationRole
{
    public const NAME = 'ai-user';

    public const LEVEL = 150;

    public const DESCRIPTION = 'AI user who can run helpers, manage their own sessions and view their own runs.';

    public const DB_PREFIX = 'ai';

    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();
        $accountId = UserHelper::currentAccount()?->id;

        // Public helpers are available to everyone, whether they belong to an account or not
        // (platform helpers have no account). Private ones only to their own account.
        if ($table === 'ai_available_helpers') {
            $builder->where(function (Builder $query) use ($table, $accountId): void {
                $query->where($table.'.is_public', true)
                    ->orWhere($table.'.iam_account_id', $accountId);
            });

            return;
        }

        // Platform LLM providers and their models have no owning account and are visible to everybody.
        // Writes on them are still blocked by checkUpdatePolicy()/checkDeletePolicy() (account must match).
        if (in_array($table, ['ai_llm_providers', 'ai_llm_models'], true)) {
            $builder->where(function (Builder $query) use ($table, $accountId): void {
                $query->whereNull($table.'.iam_account_id')
                    ->orWhere($table.'.iam_account_id', $accountId);
            });

            return;
        }

        $builder->where($table.'.iam_account_id', $accountId);
    }

    public function getModule(): string
    {
        return 'ai';
    }

    public function allowedOperations(): array
    {
        return [
            'ai_accounts:read',
            'ai_accounts:create',
            'ai_accounts:update',

            'ai_agents:read',

            'ai_agent_tool_assignments:read',

            'ai_agent_versions:read',

            'ai_available_helpers:read',

            'ai_conversations:read',
            'ai_conversations:create',

            'ai_sessions:read',
            'ai_sessions:create',
            'ai_sessions:update',
            'ai_sessions:delete',

            'ai_runs:read',
            'ai_runs:create',

            'ai_llm_providers:read',
            'ai_llm_providers:create',
            'ai_llm_providers:update',
            'ai_llm_providers:delete',

            'ai_llm_models:read',
            'ai_llm_models:create',
            'ai_llm_models:update',
            'ai_llm_models:delete',

            'ai_llm_aliases:read',
            'ai_llm_aliases:create',
            'ai_llm_aliases:update',
            'ai_llm_aliases:delete',

            'ai_llm_broadcast_groups:read',
            'ai_llm_broadcast_groups:create',
            'ai_llm_broadcast_groups:update',
            'ai_llm_broadcast_groups:delete',

            'ai_llm_api_keys:read',
            'ai_llm_api_keys:create',
            'ai_llm_api_keys:update',
            'ai_llm_api_keys:delete',

            'ai_llm_usage_logs:read',

            'ai_llm_chats:read',
            'ai_llm_chats:create',
            'ai_llm_chats:update',
            'ai_llm_chats:delete',

            'ai_llm_chat_messages:read',
            'ai_llm_chat_messages:create',
            'ai_llm_chat_messages:update',
            'ai_llm_chat_messages:delete',
        ];
    }

    public function checkUpdatePolicy(Model $model, Users $user): bool
    {
        return (int) $model->iam_account_id === (int) UserHelper::currentAccount()?->id;
    }

    public function checkDeletePolicy(Model $model, Users $user): bool
    {
        return (int) $model->iam_account_id === (int) UserHelper::currentAccount()?->id;
    }

    public function getLevel(): int
    {
        return self::LEVEL;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDescription(): string
    {
        return self::DESCRIPTION;
    }

    public function canBeApplied($column): bool
    {
        if (self::DB_PREFIX === '*') {
            return true;
        }

        return Str::startsWith($column, self::DB_PREFIX);
    }

    public function getDbPrefix(): string
    {
        return self::DB_PREFIX;
    }

    public function checkRules(?Users $users = null): bool
    {
        return UserHelper::hasRole(self::NAME, $users);
    }
}
