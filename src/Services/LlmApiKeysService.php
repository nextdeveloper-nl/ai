<?php

namespace NextDeveloper\AI\Services;

use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\AbstractServices\AbstractLlmApiKeysService;
use NextDeveloper\Commons\Exceptions\NotAllowedException;

/**
 * This class is responsible from managing the data for LlmApiKeys
 *
 * Class LlmApiKeysService.
 *
 * @package NextDeveloper\AI\Database\Models
 */
class LlmApiKeysService extends AbstractLlmApiKeysService
{
    /**
     * Creates an API key. Only the sha256 hash is stored; the plaintext is attached to the returned
     * model as the transient "plain_key" attribute and is shown to the caller exactly once.
     */
    public static function create(array $data)
    {
        $generated = LlmKeyHelper::generateApiKey();

        $data['key_prefix'] = $generated['prefix'];
        $data['key_hash'] = $generated['hash'];
        $data['status'] = 'active';

        $model = parent::create($data);
        $model->plain_key = $generated['key'];

        return $model;
    }

    /**
     * Revokes a key. Soft state change (not a delete) so usage logs keep pointing at it.
     */
    public static function revoke(string $ref)
    {
        $model = self::getByRef($ref);

        throw_if(!$model, new NotAllowedException('API key not found or not allowed.'));

        return parent::update($model->uuid, ['status' => 'revoked', 'revoked_at' => now()]);
    }

    /**
     * Revokes the key and issues a new one with the same name and expiry. Returns the new key (with plain_key).
     */
    public static function regenerate(string $ref)
    {
        $old = self::getByRef($ref);

        throw_if(!$old, new NotAllowedException('API key not found or not allowed.'));

        self::revoke($ref);

        return self::create([
            'name'       => $old->name,
            'expires_at' => $old->expires_at,
        ]);
    }

    /**
     * Finds an active, unexpired key by its plaintext value. Used by the auth middleware, which runs
     * before any user exists, so the AuthorizationScope is bypassed here on purpose.
     */
    public static function findActiveByPlainKey(string $plain)
    {
        $key = \NextDeveloper\AI\Database\Models\LlmApiKeys::withoutGlobalScopes()
            ->where('key_hash', LlmKeyHelper::hash($plain))
            ->where('status', 'active')
            ->first();

        if (!$key || ($key->expires_at && $key->expires_at->isPast())) {
            return null;
        }

        return $key;
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
