<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * The account that owns the platform's own LLM rows. Rows created through the administrator path carry its id
 * instead of NULL, so they must be treated as platform rows too.
 */
class PlatformAccount
{
    private const CACHE_KEY = 'ai:platform-account-id';

    /** @var array{id: int|null}|null Fixed answer for tests. */
    private static ?array $fake = null;

    /**
     * Fixes the answer of id() (tests); pass null to go back to the real lookup.
     */
    public static function fake(?int $id, bool $enabled = true): void
    {
        self::$fake = $enabled ? ['id' => $id] : null;
    }

    /**
     * @return int|null Null when the platform account cannot be determined.
     */
    public static function id(): ?int
    {
        if (self::$fake !== null) {
            return self::$fake['id'];
        }

        try {
            return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): ?int {
                $id = UserHelper::getLeoOwnerAccount()->id ?? null;

                return $id === null ? null : (int) $id;
            });
        } catch (\Throwable $e) {
            try {
                Log::warning('[AI][PlatformAccount] Could not find the platform account: ' . $e->getMessage());
            } catch (\Throwable) {
                // No logger available (outside the application).
            }

            return null;
        }
    }

    public static function owns(?int $accountId): bool
    {
        return $accountId === null || $accountId === self::id();
    }
}
