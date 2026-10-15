<?php

namespace NextDeveloper\AI\Helpers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Key helper for the LLM proxy.
 *
 * - Caller API keys are hashed (sha256) and shown once; they can never be recovered.
 * - Upstream provider keys must be replayed to the provider, so they are encrypted
 *   with the Laravel application key (Crypt facade).
 */
class LlmKeyHelper
{
    public const KEY_PREFIX = 'sk-pc-';

    /**
     * Generates a new caller API key.
     *
     * @return array{key: string, prefix: string, hash: string}
     */
    public static function generateApiKey(): array
    {
        $key = self::KEY_PREFIX . Str::random(40);

        return [
            'key'    => $key,
            'prefix' => substr($key, 0, 12),
            'hash'   => self::hash($key),
        ];
    }

    public static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Encrypts an upstream provider secret with the application key (APP_KEY).
     */
    public static function encrypt(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    /**
     * @throws RuntimeException when the payload is invalid or the key changed
     */
    public static function decrypt(string $encrypted): string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException $e) {
            throw new RuntimeException('LlmKeyHelper: decryption failed (bad key or tampered data)', 0, $e);
        }
    }
}
