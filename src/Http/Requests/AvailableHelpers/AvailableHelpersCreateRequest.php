<?php

namespace NextDeveloper\AI\Http\Requests\AvailableHelpers;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class AvailableHelpersCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
        'action' => 'required|string',
        'description' => 'required|string',
        'class' => 'required|string',
        'input' => 'nullable|string',
        'outputs' => 'nullable',
        'parameters' => 'required',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}