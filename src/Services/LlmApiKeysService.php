<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use NextDeveloper\AI\Database\Filters\LlmApiKeysQueryFilter;
use NextDeveloper\AI\Database\Models\LlmApiKeys;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\AbstractServices\AbstractLlmApiKeysService;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
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
     * A plain list returns every key of the account. LimitScope would cut it to 20 rows and hide the newest keys.
     * Callers that ask for paginate or rowCount keep the standard behaviour.
     */
    public static function get(?LlmApiKeysQueryFilter $filter = null, array $params = []): Collection|LengthAwarePaginator
    {
        if (array_key_exists('paginate', $params) || array_key_exists('rowCount', $params)) {
            return parent::get($filter, $params);
        }

        $filter ??= new LlmApiKeysQueryFilter(new Request());

        if (array_key_exists('orderBy', $params)) {
            $filter->orderBy($params['orderBy']);
        }

        return LlmApiKeys::withoutGlobalScope(LimitScope::class)->filter($filter)->get();
    }

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

        if ($old->status !== 'active') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Only an active API key can be regenerated.',
            ]);
        }

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
