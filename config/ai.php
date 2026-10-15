<?php

return [
    'scopes'    =>  [
        'global' => [
            //  Dont do this here because it makes infinite loop with user object.
            '\NextDeveloper\IAM\Database\Scopes\AuthorizationScope',
            '\NextDeveloper\Commons\Database\GlobalScopes\LimitScope',
        ],
        'iam_accounts' => [
            //'\NextDeveloper\IAM\Database\Scopes\AuthorizationScope'
        ]
    ],

    // LLM proxy settings (providers, aliases, broadcasts, api keys, usage).
    'llm' => [
        // The platform LiteLLM gateway every customer call is routed through (see ai:llm-sync).
        'litellm' => [
            'slug'      => 'litellm',
            'url'       => env('LITELLM_URL'),
            'key'       => env('LITELLM_KEY'),
            'sync_cron' => env('AI_LLM_SYNC_CRON', '0 * * * *'),
        ],
        // Bring-your-own-key: let accounts add their own upstream providers. Off until BYOK launches.
        'allow_byok'         => env('AI_LLM_ALLOW_BYOK', false),
        // Markup added on top of the upstream cost when charging the account.
        'markup_percent'     => env('AI_LLM_MARKUP_PERCENT', 5),
        // Upstream request timeout in seconds.
        'timeout'            => env('AI_LLM_TIMEOUT', 120),
        'queue'              => env('AI_LLM_QUEUE', 'default'),
        // Requests per minute per API key on the proxy endpoints.
        'throttle_per_minute' => env('AI_LLM_THROTTLE', 120),
    ],
];
