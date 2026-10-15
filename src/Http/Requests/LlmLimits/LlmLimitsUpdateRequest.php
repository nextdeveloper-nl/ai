<?php

namespace NextDeveloper\AI\Http\Requests\LlmLimits;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class LlmLimitsUpdateRequest extends AbstractFormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules()
    {
        return [
            'monthly_spend_limit' => 'present|nullable|numeric|min:0',
        ];
    }
}
