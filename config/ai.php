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
        // Cron of ai:llm-sync, which refreshes the models and prices of every active platform provider.
        'sync_cron'          => env('AI_LLM_SYNC_CRON', '0 * * * *'),
        // Prefix of the model names customers use ("plusclouds/gemma-4-31b-it"); providers never show up in them.
        'public_prefix'      => env('AI_LLM_PUBLIC_PREFIX', 'plusclouds'),
        // Let callers address a model as "provider/model" too. Off: that would reveal the provider.
        'allow_provider_model_ids' => env('AI_LLM_ALLOW_PROVIDER_MODEL_IDS', false),
        // Chat history: how many of a user's newest messages a content search decrypts and scans.
        'chat_search_scan_limit' => env('AI_LLM_CHAT_SEARCH_SCAN_LIMIT', 2000),
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
