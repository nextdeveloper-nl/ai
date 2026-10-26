<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NextDeveloper\IAM\Database\Models\Accounts;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Services\RolesService;

/**
 * Makes sure a user of the AI endpoints has the roles the configuration says every user (ai.roles.default) and the
 * owner of the account (ai.roles.owner) need, and adds the missing ones. Users who register elsewhere (a role list
 * of another application) or before a role existed would otherwise be refused with "not allowed".
 */
class AiRolesService
{
    private const RECHECK_MINUTES = 60;

    /**
     * Adds the missing default roles, and the owner roles when $user owns $account. The check itself is remembered
     * for an hour per user and account, so most requests cost no query.
     *
     * @return array<int, string> names of the roles that were added
     */
    public static function ensure(Users $user, Accounts $account): array
    {
        $key = "ai:roles-ensured:{$user->id}:{$account->id}";

        if (Cache::has($key)) {
            return [];
        }

        $wanted = self::wanted(self::list('default'), self::list('owner'), (int) $account->iam_user_id === (int) $user->id);
        $added = [];

        foreach ($wanted as $name) {
            $role = RolesService::getRoleByName($name);

            if (!$role) {
                Log::error("[AI][Roles] The role '{$name}' from ai.roles does not exist; run leo:generate-roles.");

                continue;
            }

            if (!self::has($user, $account, (int) $role->id)) {
                RolesService::assignUserToRole($user, $role, $account);
                $added[] = $name;
            }
        }

        Cache::put($key, true, now()->addMinutes(self::RECHECK_MINUTES));

        return $added;
    }

    /**
     * The roles to check: the default ones for everybody, the owner ones on top for the account owner.
     *
     * @param array<int, string> $default
     * @param array<int, string> $owner
     * @return array<int, string>
     */
    public static function wanted(array $default, array $owner, bool $isOwner): array
    {
        return array_values(array_unique(array_filter($isOwner ? array_merge($default, $owner) : $default, 'is_string')));
    }

    /**
     * @return array<int, string>
     */
    private static function list(string $which): array
    {
        return (array) config("ai.roles.{$which}", []);
    }

    private static function has(Users $user, Accounts $account, int $roleId): bool
    {
        return \NextDeveloper\IAM\Database\Models\RoleUsers::withoutGlobalScopes()
            ->where('iam_user_id', $user->id)
            ->where('iam_account_id', $account->id)
            ->where('iam_role_id', $roleId)
            ->where('is_active', true)
            ->exists();
    }
}
