<?php

namespace NextDeveloper\AI\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NextDeveloper\IAM\Authorization\Roles\AbstractRole;
use NextDeveloper\IAM\Authorization\Roles\IAuthorizationRole;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;

class AIAdminRole extends AbstractRole implements IAuthorizationRole
{
    public const NAME = 'ai-admin';

    public const LEVEL = 100;

    public const DESCRIPTION = 'AI Admin';

    public const DB_PREFIX = 'ai';

    /**
     * Applies basic member role sql for Eloquent
     *
     * @param Builder $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // Returns everything about ai
    }

    public function checkPrivileges(Users $users = null)
    {
        //return UserHelper::hasRole(self::NAME, $users);
    }

    public function getModule()
    {
        return 'ai';
    }

    public function allowedOperations() :array
    {
        $operations = [
            'ai_agents:read',
            'ai_agents:create',
            'ai_agents:update',
            'ai_agents:delete',
            'ai_runs:read',
            'ai_runs:create',
            'ai_runs:update',
            'ai_runs:delete'
        ];

        return $operations;
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

    public function canBeApplied($column)
    {
        if(self::DB_PREFIX === '*') {
            return true;
        }

        if(Str::startsWith($column, self::DB_PREFIX)) {
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
        // TODO: Implement checkRules() method.
    }
}
