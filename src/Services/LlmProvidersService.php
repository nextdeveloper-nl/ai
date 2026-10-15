<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Support\Facades\DB;
use NextDeveloper\AI\Database\Models\LlmModels;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Helpers\LlmKeyHelper;
use NextDeveloper\AI\Services\AbstractServices\AbstractLlmProvidersService;
use NextDeveloper\AI\Services\Llm\LlmTarget;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * This class is responsible from managing the data for LlmProviders
 *
 * Class LlmProvidersService.
 *
 * @package NextDeveloper\AI\Database\Models
 */
class LlmProvidersService extends AbstractLlmProvidersService
{
    /**
     * Creates a provider. The plaintext "api_key" is encrypted before it is stored.
     * "is_platform" creates a platform-wide provider (no owning account); only ai-admin may do that.
     */
    public static function create(array $data)
    {
        $data = self::encryptKey($data);
        $isPlatform = !empty($data['is_platform']);
        unset($data['is_platform']);

        if (!$isPlatform) {
            // BYOK is switched off for now: only platform providers can be created.
            throw_if(
                !config('ai.llm.allow_byok', false),
                new NotAllowedException('Bring-your-own-key providers are not available yet.')
            );

            return parent::create($data);
        }

        throw_if(
            !UserHelper::hasRole('ai-admin'),
            new NotAllowedException('Only ai-admin can create platform providers.')
        );

        // Platform providers have no owning account, which the generic create() would overwrite with the current one.
        $data['iam_user_id'] = UserHelper::me()->id;
        $data['iam_account_id'] = null;

        return LlmProviders::create($data)->fresh();
    }

    public static function update($id, array $data)
    {
        unset($data['is_platform']);

        return parent::update($id, self::encryptKey($data));
    }

    /**
     * Pulls the model list from the upstream provider and syncs it into ai_llm_models.
     *
     * @return int number of newly created models
     */
    public static function syncModels(string $ref): int
    {
        $provider = self::getByRef($ref);

        if (!$provider) {
            throw new NotAllowedException('Provider not found or not allowed.');
        }

        // Syncing rewrites models and prices of every customer, so it is an admin job for platform providers.
        throw_if(
            $provider->iam_account_id === null && !UserHelper::hasRole('ai-admin'),
            new NotAllowedException('Only ai-admin can sync platform providers.')
        );

        return self::syncProviderModels($provider)['created'];
    }

    /**
     * Upserts the upstream models of a provider. Prices, context window and the upstream vendor come from the
     * upstream on every run (LiteLLM prices change); models that vanished upstream are deactivated, never deleted,
     * so old usage logs keep their reference. Runs without role checks: callers (command, admin action) are trusted.
     *
     * @return array{created: int, updated: int, deactivated: int}
     */
    public static function syncProviderModels(LlmProviders $provider): array
    {
        $target = new LlmTarget($provider, '', null, '');
        $key = $provider->api_key_encrypted ? LlmKeyHelper::decrypt($provider->api_key_encrypted) : '';
        $upstream = $target->driver()->fetchModels($provider->base_url, $key, (int) config('ai.llm.timeout', 120));

        $counts = ['created' => 0, 'updated' => 0, 'deactivated' => 0];
        $seen = [];

        foreach ($upstream as $model) {
            $seen[] = $model['model_id'];

            $row = LlmModels::withoutGlobalScopes()
                ->where('ai_llm_provider_id', $provider->id)
                ->where('model_id', $model['model_id'])
                ->first();

            $capabilities = array_filter(['upstream_provider' => $model['upstream_provider'] ?? null]);

            if (!$row) {
                UserHelper::runAsAdmin(fn () => LlmModels::create([
                    'ai_llm_provider_id'  => $provider->id,
                    'model_id'            => $model['model_id'],
                    'display_name'        => $model['display_name'],
                    'input_price_per_1m'  => $model['input_price_per_1m'] ?? null,
                    'output_price_per_1m' => $model['output_price_per_1m'] ?? null,
                    'context_window'      => $model['context_window'] ?? null,
                    'capabilities'        => $capabilities ?: null,
                    'is_active'           => true,
                    'iam_account_id'      => $provider->iam_account_id,
                    'iam_user_id'         => $provider->iam_user_id,
                ]));
                $counts['created']++;

                continue;
            }

            // Direct update: this only mirrors upstream state, no observer/permission round trip needed.
            // A missing upstream price keeps the previous one rather than wiping it.
            $changes = array_filter([
                'display_name'        => $model['display_name'],
                'input_price_per_1m'  => $model['input_price_per_1m'] ?? null,
                'output_price_per_1m' => $model['output_price_per_1m'] ?? null,
                'context_window'      => $model['context_window'] ?? null,
            ], fn ($v) => $v !== null);

            if ($capabilities) {
                $changes['capabilities'] = json_encode($capabilities);
            }

            if (!$row->is_active) {
                $changes['is_active'] = true;
            }

            DB::table($row->getTable())->where('id', $row->id)->update($changes + ['updated_at' => now()]);
            $counts['updated']++;
        }

        $counts['deactivated'] = DB::table((new LlmModels())->getTable())
            ->where('ai_llm_provider_id', $provider->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotIn('model_id', $seen)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return $counts;
    }

    private static function encryptKey(array $data): array
    {
        if (!empty($data['api_key'])) {
            $data['api_key_encrypted'] = LlmKeyHelper::encrypt($data['api_key']);
        }

        unset($data['api_key']);

        return $data;
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
