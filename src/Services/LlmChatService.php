<?php

namespace NextDeveloper\AI\Services;

use NextDeveloper\AI\Database\Models\LlmApiKeys;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Chat for logged-in users (panel): the same proxy pipeline as /ai/v1, but acting on behalf of the
 * IAM user and account of the session instead of an AI API key.
 */
class LlmChatService
{
    /**
     * @throws NotAllowedException when the user has none of the AI roles
     */
    public static function assertAiRole(): void
    {
        $allowed = UserHelper::hasRole('ai-user') || UserHelper::hasRole('ai-manager') || UserHelper::hasRole('ai-admin');

        throw_if(!$allowed, new NotAllowedException('You need the ai-user role to use the AI proxy.'));
    }

    /**
     * An unsaved key carrying the session user/account, so the proxy bills and logs the right account without
     * the browser holding an API key. It is never persisted: usage logs get no api key (null).
     *
     * @throws NotAllowedException when the user has no AI role
     */
    public static function keyForCurrentUser(): LlmApiKeys
    {
        self::assertAiRole();

        $key = new LlmApiKeys();
        $key->iam_user_id = UserHelper::me()->id;
        $key->iam_account_id = UserHelper::currentAccount()->id;

        return $key;
    }
}
