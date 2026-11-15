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

    // Roles the AI endpoints make sure a logged-in user has, adding the missing ones before the request is authorized.
    'roles' => [
        // Everybody who uses the AI endpoints.
        'default' => ['ai-user'],
        // The owner of the account, on top of the default ones.
        'owner'   => ['ai-manager'],
    ],

    // LLM proxy settings (providers, aliases, broadcasts, api keys, usage).
    'llm' => [
        // Cron of ai:llm-sync, which refreshes the models and prices of every active platform provider.
        'sync_cron'          => env('AI_LLM_SYNC_CRON', '0 * * * *'),
        // Cron of ai:llm-speed-test, which measures the tokens per second of every active platform model.
        'speed_test_cron'    => env('AI_LLM_SPEED_TEST_CRON', '30 3 * * *'),
        // Tokens per second (median of the last days) from which a model gets speed 2, 3, 4 and 5; below the first is 1.
        'speed_thresholds'   => [20, 50, 100, 200],
        // Prefix of the model names customers use ("plusclouds/gemma-4-31b-it"); providers never show up in them.
        'public_prefix'      => env('AI_LLM_PUBLIC_PREFIX', 'plusclouds'),
        // Let callers address a model as "provider/model" too. Off: that would reveal the provider.
        'allow_provider_model_ids' => env('AI_LLM_ALLOW_PROVIDER_MODEL_IDS', false),
        // List the platform aliases (helper routing names) in GET /ai/v1/models. Off: customers see their own aliases and the public models.
        'list_platform_aliases' => env('AI_LLM_LIST_PLATFORM_ALIASES', false),
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
