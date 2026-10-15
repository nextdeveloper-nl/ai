<?php

namespace NextDeveloper\AI\Http\Requests\LlmAliases;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmAliasesCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
        'alias' => 'required|string',
        'target_provider' => 'required|string',
        'target_model' => 'required|string',
        'is_active' => 'boolean',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
