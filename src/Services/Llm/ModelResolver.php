<?php

namespace NextDeveloper\AI\Services\Llm;

use NextDeveloper\AI\Database\Models\LlmAliases;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;

/**
 * Turns whatever the caller sent as "model" into a concrete LlmTarget.
 *
 * Resolution order: active alias of the account -> "provider-slug/model-id".
 * Runs on behalf of an API key (no logged-in user), so it explicitly bypasses the
 * AuthorizationScope and scopes by the key's account itself.
 */
class ModelResolver
{
    public static function resolve(string $requested, int $accountId): LlmTarget
    {
        $providerSlug = null;
        $modelId = null;

        $alias = LlmAliases::withoutGlobalScopes()
            ->where('iam_account_id', $accountId)
            ->where('alias', $requested)
            ->where('is_active', true)
            ->first();

        if ($alias) {
            $providerSlug = $alias->target_provider;
            $modelId = $alias->target_model;
        } elseif (str_contains($requested, '/')) {
            [$providerSlug, $modelId] = explode('/', $requested, 2);
        }

        if (!$providerSlug || !$modelId) {
            throw new LlmProxyException("The model '{$requested}' does not exist or is not an alias of this account.", 404, 'invalid_request_error', 'model_not_found');
        }

        $provider = self::findProvider($providerSlug, $accountId);

        if (!$provider) {
            throw new LlmProxyException("Provider '{$providerSlug}' is not available.", 404, 'invalid_request_error', 'provider_not_found');
        }

        $pricing = LlmModels::withoutGlobalScopes()
            ->where('ai_llm_provider_id', $provider->id)
            ->where('model_id', $modelId)
            ->first();

        if ($pricing && !$pricing->is_active) {
            throw new LlmProxyException("The model '{$requested}' is disabled.", 404, 'invalid_request_error', 'model_disabled');
        }

        // Platform calls are billed with the cost the upstream reports per call, so an unpriced model is still served;
        // the charge job refuses to bill when neither a reported nor a synced price exists.

        return new LlmTarget($provider, $modelId, $pricing, $requested, $alias?->alias, $alias);
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
