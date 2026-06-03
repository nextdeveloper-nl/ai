<?php

namespace NextDeveloper\AI\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NextDeveloper\IAM\Authorization\Roles\AbstractRole;
use NextDeveloper\IAM\Authorization\Roles\IAuthorizationRole;
use NextDeveloper\IAM\Database\Models\Users;

class AIAdminRole extends AbstractRole implements IAuthorizationRole
{
    public const NAME = 'ai-admin';

    public const LEVEL = 10;

    public const DESCRIPTION = 'AI admin with unrestricted access to all AI objects across all accounts.';

    public const DB_PREFIX = 'ai';

    public function apply(Builder $builder, Model $model): void
    {
        // Admins see all AI records — no scope applied.
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
            'ai_accounts:delete',

            'ai_agents:read',
            'ai_agents:create',
            'ai_agents:update',
            'ai_agents:delete',

            'ai_agent_tool_assignments:read',
            'ai_agent_tool_assignments:create',
            'ai_agent_tool_assignments:update',
            'ai_agent_tool_assignments:delete',

            'ai_agent_versions:read',
            'ai_agent_versions:create',
            'ai_agent_versions:update',
            'ai_agent_versions:delete',

            'ai_available_helpers:read',
            'ai_available_helpers:create',
            'ai_available_helpers:update',
            'ai_available_helpers:delete',

            'ai_conversations:read',
            'ai_conversations:create',
            'ai_conversations:update',
            'ai_conversations:delete',

            'ai_sessions:read',
            'ai_sessions:create',
            'ai_sessions:update',
            'ai_sessions:delete',

            'ai_runs:read',
            'ai_runs:create',
            'ai_runs:update',
            'ai_runs:delete',
        ];
    }

    public function checkCreatePolicy(Users $user, Model $model): bool
    {
        return true;
    }

    public function checkUpdatePolicy(Model $model, Users $user): bool
    {
        return true;
    }

    public function checkDeletePolicy(Model $model, Users $user): bool
    {
        return true;
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

    public function canBeApplied(string $column): bool
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
        return true;
    }
}
