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

    public const LEVEL = 50;

    public const DESCRIPTION = 'AI User';

    public const DB_PREFIX = 'ai';

    /**
     * Limits query results to records belonging to the authenticated user's account.
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->where($model->getTable().'.iam_account_id', UserHelper::currentAccount()?->id);
    }

    public function checkPrivileges(Users $users = null)
    {
        //return UserHelper::hasRole(self::NAME, $users);
    }

    public function getModule()
    {
        return 'ai';
    }

    public function allowedOperations(): array
    {
        return [
            'ai_agents:read',
            'ai_agents:create',
            'ai_available_helpers:read',
            'ai_conversations:read',
            'ai_conversations:create',
            'ai_sessions:read',
            'ai_sessions:create',
            'ai_sessions:update',
            'ai_sessions:delete',
            'ai_runs:read',
            'ai_runs:create',
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

    public function canBeApplied($column)
    {
        if (self::DB_PREFIX === '*') {
            return true;
        }

        if (Str::startsWith($column, self::DB_PREFIX)) {
            return true;
        }

        return false;
    }

    public function getDbPrefix()
    {
        return self::DB_PREFIX;
    }

    public function checkRules(Users $users): bool
    {
        return UserHelper::hasRole(self::NAME, $users);
    }
}
