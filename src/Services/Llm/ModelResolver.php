<?php

namespace NextDeveloper\AI\Services\Llm;

use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;

/**
 * Turns whatever the caller sent as "model" into a concrete LlmTarget.
 *
 * Resolution order: active alias of the account -> public model id (the cheapest provider offer first).
 * Runs on behalf of an API key (no logged-in user), so it explicitly bypasses the
 * AuthorizationScope and scopes by the key's account itself.
 */
class ModelResolver
{
    public static function resolve(string $requested, int $accountId): LlmTarget
    {
        // An alias of the account wins over a platform alias (iam_account_id NULL) of the same name.
        $alias = LlmAliases::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('iam_account_id', $accountId)->orWhereNull('iam_account_id'))
            ->where('alias', $requested)
            ->where('is_active', true)
            ->orderByRaw('iam_account_id IS NULL')
            ->first();

        if ($alias) {
            // An alias that points at the public prefix routes to a public model: the cheapest offer, with fallbacks.
            if ($alias->target_provider === self::publicPrefix()) {
                $offers = self::offers(self::publicPrefix() . '/' . $alias->target_model, $accountId, $requested, $alias);

                if (!$offers) {
                    throw new LlmProxyException("The model '{$requested}' is not available.", 404, 'invalid_request_error', 'model_not_found');
                }

                return $offers[0]->withAlternates(array_slice($offers, 1));
            }

            return self::resolveProviderModel($alias->target_provider, $alias->target_model, $accountId, $requested, $alias);
        }

        $offers = self::offers($requested, $accountId);

        if ($offers) {
            return $offers[0]->withAlternates(array_slice($offers, 1));
        }

        // "provider/model" ids reveal the provider, so customers only get them when the platform allows it.
        if (config('ai.llm.allow_provider_model_ids', false) && str_contains($requested, '/')) {
            [$providerSlug, $modelId] = explode('/', $requested, 2);

            return self::resolveProviderModel($providerSlug, $modelId, $accountId, $requested);
        }

        throw new LlmProxyException("The model '{$requested}' does not exist or is not an alias of this account.", 404, 'invalid_request_error', 'model_not_found');
    }

    /**
     * Resolves an upstream model of one provider. Used for alias targets, alias fallbacks and broadcast targets,
     * which are set up by admins and customers, not typed into a request.
     */
    public static function resolveProviderModel(?string $providerSlug, ?string $modelId, int $accountId, ?string $requested = null, ?LlmAliases $alias = null): LlmTarget
    {
        $requested ??= $providerSlug . '/' . $modelId;

        if (!$providerSlug || !$modelId) {
            throw new LlmProxyException("The model '{$requested}' does not exist or is not an alias of this account.", 404, 'invalid_request_error', 'model_not_found');
        }

        $provider = self::findProvider($providerSlug, $accountId);

        if (!$provider) {
            throw new LlmProxyException("The model '{$requested}' is not available.", 404, 'invalid_request_error', 'provider_not_found');
        }

        $pricing = LlmModels::withoutGlobalScopes()
            ->where('ai_llm_provider_id', $provider->id)
            ->where('model_id', $modelId)
            ->first();

        if ($pricing && !$pricing->is_active) {
            throw new LlmProxyException("The model '{$requested}' is disabled.", 404, 'invalid_request_error', 'model_disabled');
        }

        // Platform calls are billed from the upstream cost or the synced prices; the charge job refuses to bill
        // a call when neither exists, so an unpriced model is still served.

        return new LlmTarget($provider, $modelId, $pricing, $requested, $alias?->alias, $alias);
    }

    /**
     * Every active offer of a public model id from providers the account may use, cheapest first. Price is the
     * blended input/output price (input counts three times: calls are mostly input); unpriced offers go last.
     *
     * @return array<int, LlmTarget>
     */
    public static function offers(string $publicId, int $accountId, ?string $requested = null, ?LlmAliases $alias = null): array
    {
        $models = LlmModels::withoutGlobalScopes()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->get();

        $providers = self::usableProviders($accountId)->keyBy('id');
        $offers = [];

        foreach ($models as $model) {
            if ($provider = $providers->get($model->ai_llm_provider_id)) {
                $offers[] = new LlmTarget($provider, $model->model_id, $model, $requested ?? $publicId, $alias?->alias, $alias, $publicId);
            }
        }

        return self::cheapestFirst($offers);
    }

    public static function publicPrefix(): string
    {
        return trim((string) config('ai.llm.public_prefix', 'plusclouds'), '/');
    }

    /**
     * @param array<int, LlmTarget> $offers
     * @return array<int, LlmTarget>
     */
    public static function cheapestFirst(array $offers): array
    {
        $blended = fn (LlmTarget $t): float => $t->pricing && $t->pricing->input_price_per_1m !== null && $t->pricing->output_price_per_1m !== null
            ? (3 * (float) $t->pricing->input_price_per_1m + (float) $t->pricing->output_price_per_1m) / 4
            : PHP_FLOAT_MAX;

        // Equal prices keep their order, so the answer does not flip between calls.
        $indexed = array_map(null, array_keys($offers), $offers);
        usort($indexed, fn ($a, $b) => [$blended($a[1]), $a[0]] <=> [$blended($b[1]), $b[0]]);

        return array_column($indexed, 1);
    }

    /**
     * Active providers the account may use: platform providers, plus its own while BYOK is on.
     *
     * @return \Illuminate\Support\Collection<int, LlmProviders>
     */
    public static function usableProviders(int $accountId)
    {
        return LlmProviders::withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($q) use ($accountId) {
                $q->whereNull('iam_account_id');

                if (config('ai.llm.allow_byok', false)) {
                    $q->orWhere('iam_account_id', $accountId);
                }
            })
            ->get();
    }

    /**
     * An account's own provider wins over a platform provider with the same slug.
     */
    private static function findProvider(string $slug, int $accountId): ?LlmProviders
    {
        return LlmProviders::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where(function ($q) use ($accountId) {
                // Account-owned (BYOK) providers are only served while ai.llm.allow_byok is on.
                if (config('ai.llm.allow_byok', false)) {
                    $q->where('iam_account_id', $accountId)->orWhereNull('iam_account_id');
                } else {
                    $q->whereNull('iam_account_id');
                }
            })
            ->orderByRaw('iam_account_id IS NULL')
            ->first();
    }
}
