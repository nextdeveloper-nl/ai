<?php

namespace NextDeveloper\AI\Http\Requests\AvailableHelpers;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class AvailableHelpersUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
        'action' => 'nullable|string',
        'description' => 'nullable|string',
        'class' => 'nullable|string',
        'input' => 'nullable|string',
        'outputs' => 'nullable',
        'parameters' => 'nullable',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}