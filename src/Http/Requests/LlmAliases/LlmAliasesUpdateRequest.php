<?php

namespace NextDeveloper\AI\Http\Requests\LlmAliases;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmAliasesUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'alias' => $this->aliasRules(false, $this->route('ai_llm_aliases')),
        'target_provider' => 'nullable|string',
        'target_model' => ['nullable', 'string', $this->targetModelRule()],
        'is_active' => 'boolean',
        'description' => 'nullable|string|max:500',
        'fallbacks' => 'nullable|array|max:5',
        'fallbacks.*.provider' => 'required_with:fallbacks|string',
        'fallbacks.*.model' => 'required_with:fallbacks|string',
        'fallback_on_error' => 'boolean',
        'fallback_on_rate_limit' => 'boolean',
        'fallback_on_timeout' => 'boolean',
        'fallback_timeout_seconds' => 'nullable|integer|min:1|max:600',
        'temperature' => 'nullable|numeric|min:0|max:2',
        'max_tokens' => 'nullable|integer|min:1',
        'system_prompt' => 'nullable|string|max:20000',
        'monthly_spend_limit' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * @param string|null $ignoreUuid
     * @return array<int, mixed>
     */
    private function aliasRules(bool $required, ?string $ignoreUuid = null): array
    {
        $accountId = \NextDeveloper\IAM\Helpers\UserHelper::currentAccount()?->id;

        $unique = \Illuminate\Validation\Rule::unique('ai_llm_aliases', 'alias')
            ->where('iam_account_id', $accountId)
            ->whereNull('deleted_at');

        if ($ignoreUuid) {
            $unique->ignore($ignoreUuid, 'uuid');
        }

        return [$required ? 'required' : 'nullable', 'string', $unique];
    }

    /**
     * Rejects a target_model the target_provider does not offer.
     */
    private function targetModelRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $slug = $this->input('target_provider');

            if (!$slug || !is_string($value)) {
                return;
            }

            $models = \NextDeveloper\AI\Database\Models\LlmModels::withoutGlobalScopes();

            //  Same split as ModelResolver: the public prefix points at a public model id, any other slug at an upstream model of that provider.
            $prefix = \NextDeveloper\AI\Services\Llm\ModelResolver::publicPrefix();

            if ($slug === $prefix) {
                $exists = $models->where('public_id', $prefix . '/' . $value)->where('is_active', true)->exists();
            } else {
                $exists = $models->where('model_id', $value)
                    ->whereIn('ai_llm_provider_id', \NextDeveloper\AI\Database\Models\LlmProviders::withoutGlobalScopes()->where('slug', $slug)->select('id'))
                    ->exists();
            }

            if (!$exists) {
                $fail("The provider '{$slug}' does not offer the model '{$value}'.");
            }
        };
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
