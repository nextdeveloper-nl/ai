<?php

namespace NextDeveloper\AI\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use NextDeveloper\AI\Services\Llm\AnthropicMessagesAdapter;
use NextDeveloper\AI\Services\LlmApiKeysService;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Authenticates proxy calls with an AI API key (Authorization: Bearer sk-pc-... or x-api-key: sk-pc-...).
 *
 * Fails closed: anything other than a valid, active, unexpired key gets a 401 in OpenAI error format.
 * On success it sets the IAM user/account of the key as current context and stores the key model on the
 * request as "ai_llm_api_key". These routes must be listed in the host app's anonymous_uris, otherwise
 * the platform tries to resolve the bearer token as an IAM token first.
 */
class AuthenticateLlmApiKey
{
    public function handle(Request $request, Closure $next)
    {
        // OpenAI clients send "Authorization: Bearer", Anthropic clients send "x-api-key".
        $token = $request->bearerToken() ?: $request->header('x-api-key');

        $key = $token && str_starts_with($token, \NextDeveloper\AI\Helpers\LlmKeyHelper::KEY_PREFIX)
            ? LlmApiKeysService::findActiveByPlainKey($token)
            : null;

        if (!$key && $request->is('ai/v1/messages')) {
            return response()->json(AnthropicMessagesAdapter::errorBody('Invalid or missing API key.', 401), 401);
        }

        if (!$key) {
            return response()->json([
                'error' => ['message' => 'Invalid or missing API key.', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key'],
            ], 401);
        }

        UserHelper::setUserById($key->iam_user_id);
        UserHelper::setCurrentAccountById($key->iam_account_id);

        // Cheap, non-model write: avoids firing observers/events on every proxied call.
        DB::table($key->getTable())->where('id', $key->id)->update(['last_used_at' => now()]);

        $request->attributes->set('ai_llm_api_key', $key);

        return $next($request);
    }
}
