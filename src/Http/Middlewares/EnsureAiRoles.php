<?php

namespace NextDeveloper\AI\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NextDeveloper\AI\Services\AiRolesService;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * On the AI routes of logged-in users: adds the roles of ai.roles.default (and ai.roles.owner for the account
 * owner) that the user is missing, before the request is authorized. Never blocks a request: if anything goes
 * wrong the request carries on and the normal authorization decides.
 */
class EnsureAiRoles
{
    public function handle(Request $request, Closure $next)
    {
        // The proxy (/ai/v1/*) authenticates with an API key, not as a logged-in user.
        if (!$request->is('ai/v1/*')) {
            try {
                $user = UserHelper::me();
                $account = $user ? UserHelper::currentAccount($user) : null;

                if ($user && $account) {
                    AiRolesService::ensure($user, $account);
                }
            } catch (\Throwable $e) {
                Log::warning('[AI][Roles] Could not check the default roles: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
