<?php

namespace NextDeveloper\AI\Http\Requests\Chat;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ChatRequest extends AbstractFormRequest
{
    /**
     * Only the fields the chat needs are validated; everything else in the body is passed through to the upstream.
     *
     * @return array<string, string>
     */
    public function rules()
    {
        return [
            'model'         => 'required|string',
            'messages'      => 'required|array|min:1',
            'messages.*.role' => 'required|string',
            'stream'        => 'boolean',
            'temperature'   => 'nullable|numeric|min:0|max:2',
            'max_tokens'    => 'nullable|integer|min:1',
        ];
    }
}
