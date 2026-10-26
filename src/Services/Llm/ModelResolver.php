<?php

namespace NextDeveloper\AI\Services\Llm;

use Illuminate\Support\Facades\Log;
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
                    throw self::unavailable($requested, self::publicPrefix() . '/' . $alias->target_model, $accountId, true);
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

        // A public id that exists but cannot be served says why; anything else is simply unknown.
        if (self::visibleModels($requested, $accountId)->exists()) {
            throw self::unavailable($requested, $requested, $accountId, false);
        }

        throw new LlmProxyException(
            "The model '{$requested}' does not exist. List the models and aliases you can use with GET /ai/v1/models.",
            404, 'invalid_request_error', 'model_not_found'
        );
    }

    /**
     * Resolves an upstream model of one provider. Used for alias targets, alias fallbacks and broadcast targets,
     * which are set up by admins and customers, not typed into a request.
     */
    public static function resolveProviderModel(?string $providerSlug, ?string $modelId, int $accountId, ?string $requested = null, ?LlmAliases $alias = null): LlmTarget
    {
        $requested ??= $providerSlug . '/' . $modelId;

        if (!$providerSlug || !$modelId) {
            Log::warning('[AI][ModelResolver] Alias target is incomplete.', ['requested' => $requested, 'alias' => $alias?->alias, 'target_provider' => $providerSlug, 'target_model' => $modelId]);

            throw new LlmProxyException(
                "The alias '{$requested}' is not set up completely: it has no model to run. Contact support.",
                404, 'invalid_request_error', 'alias_misconfigured'
            );
        }

        $provider = self::findProvider($providerSlug, $accountId);

        if (!$provider) {
            Log::warning('[AI][ModelResolver] No usable provider for the target.', ['requested' => $requested, 'provider' => $providerSlug, 'model' => $modelId, 'account' => $accountId]);

            throw new LlmProxyException(
                $alias ? "The alias '{$requested}' exists, but the model it points to is not available right now. Try again later." : "The model '{$requested}' is not available right now. Try again later.",
                404, 'invalid_request_error', $alias ? 'alias_target_unavailable' : 'model_unavailable'
            );
        }

        $pricing = LlmModels::withoutGlobalScopes()
            ->where('ai_llm_provider_id', $provider->id)
            ->where('model_id', $modelId)
            ->first();

        if ($pricing && !$pricing->is_active) {
            throw new LlmProxyException(
                $alias ? "The alias '{$requested}' exists, but the model it points to is disabled." : "The model '{$requested}' is disabled and cannot be used.",
                404, 'invalid_request_error', 'model_disabled'
            );
        }

        // Platform calls are billed from the upstream cost or the synced prices; the charge job refuses to bill
        // a call when neither exists, so an unpriced model is still served.

        return new LlmTarget($provider, $modelId, $pricing, $requested, $alias?->alias, $alias);
    }

    /**
     * Every active offer of a public model id, cheapest first: from providers the account may use, plus models marked is_public. Price is the
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
        $publicProviders = LlmProviders::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('id', $models->where('is_public', true)->pluck('ai_llm_provider_id'))
            ->get()
            ->keyBy('id');
        $offers = [];

        foreach ($models as $model) {
            $provider = $providers->get($model->ai_llm_provider_id) ?? ($model->is_public ? $publicProviders->get($model->ai_llm_provider_id) : null);

            if ($provider) {
                $offers[] = new LlmTarget($provider, $model->model_id, $model, $requested ?? $publicId, $alias?->alias, $alias, $publicId);
            }
        }

        return self::cheapestFirst($offers);
    }

    /**
     * The non-deleted models with a public id that the account may know about: public models, those of platform
     * providers and of its own providers. A model of another account's provider must look exactly like an id that does not exist.
     *
     * @return \Illuminate\Database\Eloquent\Builder<LlmModels>
     */
    private static function visibleModels(string $publicId, int $accountId)
    {
        return LlmModels::withoutGlobalScopes()
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('is_public', true)->orWhereIn('ai_llm_provider_id', LlmProviders::withoutGlobalScopes()
                ->where(fn ($q) => $q->whereNull('iam_account_id')->orWhere('iam_account_id', $accountId))
                ->select('id')));
    }

    /**
     * Why a public model cannot be served right now. The customer is told what is wrong with the model or alias they
     * used (never which provider is behind it); the exact cause, with provider names, goes to the log.
     */
    private static function unavailable(string $requested, string $publicId, int $accountId, bool $viaAlias): LlmProxyException
    {
        $models = self::visibleModels($publicId, $accountId)->get();
        $providers = LlmProviders::withoutGlobalScopes()->whereIn('id', $models->pluck('ai_llm_provider_id'))->get()->keyBy('id');
        $usable = self::usableProviders($accountId)->pluck('id')->all();

        $reason = self::classify($models->all(), $providers->all(), $usable);

        Log::warning('[AI][ModelResolver] Public model cannot be served.', [
            'requested' => $requested, 'public_id' => $publicId, 'account' => $accountId, 'reason' => $reason['code'], 'detail' => $reason['detail'],
        ]);

        $subject = $viaAlias ? "The alias '{$requested}' exists, but the model it points to" : "The model '{$requested}'";

        return match ($reason['code']) {
            'no_model' => new LlmProxyException(
                $viaAlias ? "The alias '{$requested}' exists, but the model it points to does not exist (any more). Contact support." : "The model '{$requested}' does not exist. List the models and aliases you can use with GET /ai/v1/models.",
                404, 'invalid_request_error', $viaAlias ? 'alias_target_unavailable' : 'model_not_found'
            ),
            'model_disabled' => new LlmProxyException("{$subject} is disabled and cannot be used.", 404, 'invalid_request_error', 'model_disabled'),
            default => new LlmProxyException("{$subject} is not available right now. Try again later; if it keeps happening, contact support.", 404, 'invalid_request_error', $viaAlias ? 'alias_target_unavailable' : 'model_unavailable'),
        };
    }

    /**
     * Sorts out why none of the models of a public id can be served.
     *
     * @param array<int, LlmModels> $models        every non-deleted model row with the public id
     * @param array<int, LlmProviders> $providers  their providers by id
     * @param array<int, int> $usableProviderIds   ids of the providers the account may use
     * @return array{code: string, detail: string}  code: no_model | model_disabled | provider_unavailable
     */
    public static function classify(array $models, array $providers, array $usableProviderIds): array
    {
        if (!$models) {
            return ['code' => 'no_model', 'detail' => 'No model row carries this public_id (run ai:llm-sync, or check the alias target).'];
        }

        $active = array_filter($models, fn ($m) => $m->is_active);

        if (!$active) {
            return ['code' => 'model_disabled', 'detail' => 'Every model row with this public_id is inactive (the sync deactivates models the provider stops listing).'];
        }

        $why = array_map(function ($m) use ($providers, $usableProviderIds) {
            $provider = $providers[$m->ai_llm_provider_id] ?? null;

            return match (true) {
                !$provider                       => "{$m->model_id}: provider row is missing",
                $provider->deleted_at !== null   => "{$m->model_id}: provider '{$provider->slug}' is deleted",
                !$provider->is_active            => "{$m->model_id}: provider '{$provider->slug}' is inactive",
                !$m->is_public && !in_array($provider->id, $usableProviderIds, true) => "{$m->model_id}: provider '{$provider->slug}' belongs to another account",
                default                          => "{$m->model_id}: usable",
            };
        }, $active);

        return ['code' => 'provider_unavailable', 'detail' => implode('; ', $why)];
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
