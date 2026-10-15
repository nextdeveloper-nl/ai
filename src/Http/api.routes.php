<?php

Route::prefix('ai')->group(function () {
    Route::prefix('available-helpers')->group(
        function () {
            Route::get('/', 'AvailableHelpers\AvailableHelpersController@index');
            Route::get('/actions', 'AvailableHelpers\AvailableHelpersController@getActions');

            Route::get('{ai_available_helpers}/tags ', 'AvailableHelpers\AvailableHelpersController@tags');
            Route::post('{ai_available_helpers}/tags ', 'AvailableHelpers\AvailableHelpersController@saveTags');
            Route::get('{ai_available_helpers}/addresses ', 'AvailableHelpers\AvailableHelpersController@addresses');
            Route::post('{ai_available_helpers}/addresses ', 'AvailableHelpers\AvailableHelpersController@saveAddresses');

            Route::get('/{ai_available_helpers}/{subObjects}', 'AvailableHelpers\AvailableHelpersController@relatedObjects');
            Route::get('/{ai_available_helpers}', 'AvailableHelpers\AvailableHelpersController@show');

            Route::post('/', 'AvailableHelpers\AvailableHelpersController@store');
            Route::post('/{ai_available_helpers}/do/{action}', 'AvailableHelpers\AvailableHelpersController@doAction');

            Route::patch('/{ai_available_helpers}', 'AvailableHelpers\AvailableHelpersController@update');
            Route::delete('/{ai_available_helpers}', 'AvailableHelpers\AvailableHelpersController@destroy');
        }
    );

    Route::prefix('conversations')->group(
        function () {
            Route::get('/', 'Conversations\ConversationsController@index');
            Route::get('/actions', 'Conversations\ConversationsController@getActions');

            Route::get('{ai_conversations}/tags ', 'Conversations\ConversationsController@tags');
            Route::post('{ai_conversations}/tags ', 'Conversations\ConversationsController@saveTags');
            Route::get('{ai_conversations}/addresses ', 'Conversations\ConversationsController@addresses');
            Route::post('{ai_conversations}/addresses ', 'Conversations\ConversationsController@saveAddresses');

            Route::get('/{ai_conversations}/{subObjects}', 'Conversations\ConversationsController@relatedObjects');
            Route::get('/{ai_conversations}', 'Conversations\ConversationsController@show');

            Route::post('/', 'Conversations\ConversationsController@store');
            Route::post('/{ai_conversations}/do/{action}', 'Conversations\ConversationsController@doAction');

            Route::patch('/{ai_conversations}', 'Conversations\ConversationsController@update');
            Route::delete('/{ai_conversations}', 'Conversations\ConversationsController@destroy');
        }
    );

    Route::prefix('sessions')->group(
        function () {
            Route::get('/', 'Sessions\SessionsController@index');
            Route::get('/actions', 'Sessions\SessionsController@getActions');

            Route::get('{ai_sessions}/tags ', 'Sessions\SessionsController@tags');
            Route::post('{ai_sessions}/tags ', 'Sessions\SessionsController@saveTags');
            Route::get('{ai_sessions}/addresses ', 'Sessions\SessionsController@addresses');
            Route::post('{ai_sessions}/addresses ', 'Sessions\SessionsController@saveAddresses');

            Route::get('/{ai_sessions}/{subObjects}', 'Sessions\SessionsController@relatedObjects');
            Route::get('/{ai_sessions}', 'Sessions\SessionsController@show');

            Route::post('/', 'Sessions\SessionsController@store');
            Route::post('/{ai_sessions}/do/{action}', 'Sessions\SessionsController@doAction');

            Route::patch('/{ai_sessions}', 'Sessions\SessionsController@update');
            Route::delete('/{ai_sessions}', 'Sessions\SessionsController@destroy');
        }
    );

    Route::prefix('runs')->group(
        function () {
            Route::get('/', 'Runs\RunsController@index');
            Route::get('/actions', 'Runs\RunsController@getActions');

            Route::get('{ai_runs}/tags ', 'Runs\RunsController@tags');
            Route::post('{ai_runs}/tags ', 'Runs\RunsController@saveTags');
            Route::get('{ai_runs}/addresses ', 'Runs\RunsController@addresses');
            Route::post('{ai_runs}/addresses ', 'Runs\RunsController@saveAddresses');

            Route::get('/{ai_runs}/{subObjects}', 'Runs\RunsController@relatedObjects');
            Route::get('/{ai_runs}', 'Runs\RunsController@show');

            Route::post('/', 'Runs\RunsController@store');
            Route::post('/{ai_runs}/do/{action}', 'Runs\RunsController@doAction');

            Route::patch('/{ai_runs}', 'Runs\RunsController@update');
            Route::delete('/{ai_runs}', 'Runs\RunsController@destroy');
        }
    );

    Route::prefix('agents')->group(
        function () {
            Route::get('/', 'Agents\AgentsController@index');
            Route::get('/actions', 'Agents\AgentsController@getActions');

            Route::get('{ai_agents}/tags ', 'Agents\AgentsController@tags');
            Route::post('{ai_agents}/tags ', 'Agents\AgentsController@saveTags');
            Route::get('{ai_agents}/addresses ', 'Agents\AgentsController@addresses');
            Route::post('{ai_agents}/addresses ', 'Agents\AgentsController@saveAddresses');

            Route::get('/{ai_agents}/{subObjects}', 'Agents\AgentsController@relatedObjects');
            Route::get('/{ai_agents}', 'Agents\AgentsController@show');

            Route::post('/', 'Agents\AgentsController@store');
            Route::post('/{ai_agents}/do/{action}', 'Agents\AgentsController@doAction');

            Route::patch('/{ai_agents}', 'Agents\AgentsController@update');
            Route::delete('/{ai_agents}', 'Agents\AgentsController@destroy');
        }
    );

    // LLM proxy: providers, models, aliases, broadcast groups, api keys, usage logs
    Route::prefix('llm/providers')->group(
        function () {
            Route::get('/', 'LlmProviders\LlmProvidersController@index');
            Route::get('/actions', 'LlmProviders\LlmProvidersController@getActions');
            Route::post('/{ai_llm_providers}/sync-models', 'LlmProviders\LlmProvidersController@syncModels');

            Route::get('/{ai_llm_providers}/{subObjects}', 'LlmProviders\LlmProvidersController@relatedObjects');
            Route::get('/{ai_llm_providers}', 'LlmProviders\LlmProvidersController@show');

            Route::post('/', 'LlmProviders\LlmProvidersController@store');
            Route::patch('/{ai_llm_providers}', 'LlmProviders\LlmProvidersController@update');
            Route::delete('/{ai_llm_providers}', 'LlmProviders\LlmProvidersController@destroy');

            Route::post('/{ai_llm_providers}/do/{action}', 'LlmProviders\LlmProvidersController@doAction');
        }
    );

    Route::prefix('llm/models')->group(
        function () {
            Route::get('/', 'LlmModels\LlmModelsController@index');
            Route::get('/actions', 'LlmModels\LlmModelsController@getActions');

            Route::get('/{ai_llm_models}/{subObjects}', 'LlmModels\LlmModelsController@relatedObjects');
            Route::get('/{ai_llm_models}', 'LlmModels\LlmModelsController@show');

            Route::post('/', 'LlmModels\LlmModelsController@store');
            Route::patch('/{ai_llm_models}', 'LlmModels\LlmModelsController@update');
            Route::delete('/{ai_llm_models}', 'LlmModels\LlmModelsController@destroy');

            Route::post('/{ai_llm_models}/do/{action}', 'LlmModels\LlmModelsController@doAction');
        }
    );

    Route::prefix('llm/aliases')->group(
        function () {
            Route::get('/', 'LlmAliases\LlmAliasesController@index');
            Route::get('/actions', 'LlmAliases\LlmAliasesController@getActions');
            Route::get('/{ai_llm_aliases}/stats', 'LlmAliases\LlmAliasesController@stats');
            Route::get('/{ai_llm_aliases}/history', 'LlmAliases\LlmAliasesController@history');

            Route::get('/{ai_llm_aliases}/{subObjects}', 'LlmAliases\LlmAliasesController@relatedObjects');
            Route::get('/{ai_llm_aliases}', 'LlmAliases\LlmAliasesController@show');

            Route::post('/', 'LlmAliases\LlmAliasesController@store');
            Route::patch('/{ai_llm_aliases}', 'LlmAliases\LlmAliasesController@update');
            Route::delete('/{ai_llm_aliases}', 'LlmAliases\LlmAliasesController@destroy');

            Route::post('/{ai_llm_aliases}/do/{action}', 'LlmAliases\LlmAliasesController@doAction');
        }
    );

    Route::prefix('llm/broadcast-groups')->group(
        function () {
            Route::get('/', 'LlmBroadcastGroups\LlmBroadcastGroupsController@index');
            Route::get('/actions', 'LlmBroadcastGroups\LlmBroadcastGroupsController@getActions');

            Route::get('/{ai_llm_broadcast_groups}/{subObjects}', 'LlmBroadcastGroups\LlmBroadcastGroupsController@relatedObjects');
            Route::get('/{ai_llm_broadcast_groups}', 'LlmBroadcastGroups\LlmBroadcastGroupsController@show');

            Route::post('/', 'LlmBroadcastGroups\LlmBroadcastGroupsController@store');
            Route::patch('/{ai_llm_broadcast_groups}', 'LlmBroadcastGroups\LlmBroadcastGroupsController@update');
            Route::delete('/{ai_llm_broadcast_groups}', 'LlmBroadcastGroups\LlmBroadcastGroupsController@destroy');

            Route::post('/{ai_llm_broadcast_groups}/do/{action}', 'LlmBroadcastGroups\LlmBroadcastGroupsController@doAction');
        }
    );

    Route::prefix('llm/api-keys')->group(
        function () {
            Route::get('/', 'LlmApiKeys\LlmApiKeysController@index');
            Route::get('/actions', 'LlmApiKeys\LlmApiKeysController@getActions');
            Route::post('/{ai_llm_api_keys}/revoke', 'LlmApiKeys\LlmApiKeysController@revoke');
            Route::post('/{ai_llm_api_keys}/regenerate', 'LlmApiKeys\LlmApiKeysController@regenerate');

            Route::get('/{ai_llm_api_keys}/{subObjects}', 'LlmApiKeys\LlmApiKeysController@relatedObjects');
            Route::get('/{ai_llm_api_keys}', 'LlmApiKeys\LlmApiKeysController@show');

            Route::post('/', 'LlmApiKeys\LlmApiKeysController@store');
            Route::patch('/{ai_llm_api_keys}', 'LlmApiKeys\LlmApiKeysController@update');
            Route::delete('/{ai_llm_api_keys}', 'LlmApiKeys\LlmApiKeysController@destroy');

            Route::post('/{ai_llm_api_keys}/do/{action}', 'LlmApiKeys\LlmApiKeysController@doAction');
        }
    );

    Route::prefix('llm/usage-logs')->group(
        function () {
            Route::get('/', 'LlmUsageLogs\LlmUsageLogsController@index');
            Route::get('/actions', 'LlmUsageLogs\LlmUsageLogsController@getActions');
            Route::get('/summary', 'LlmUsageLogs\LlmUsageLogsController@summary');

            Route::get('/{ai_llm_usage_logs}/{subObjects}', 'LlmUsageLogs\LlmUsageLogsController@relatedObjects');
            Route::get('/{ai_llm_usage_logs}', 'LlmUsageLogs\LlmUsageLogsController@show');

            Route::post('/{ai_llm_usage_logs}/do/{action}', 'LlmUsageLogs\LlmUsageLogsController@doAction');
        }
    );

    // Monthly spend limit of the current account.
    Route::get('llm/limits', 'LlmLimits\\LlmLimitsController@show');
    Route::put('llm/limits', 'LlmLimits\\LlmLimitsController@update');

    // Chat for logged-in users (IAM token): same pipeline as /ai/v1/chat/completions, billed to the session account.
    Route::post('llm/chat', 'Chat\\ChatController@chat');

    // OpenAI-compatible proxy endpoints. Authenticated with an AI API key, not an IAM token.
    // These URIs must be listed in anonymous_uris of the host application (/ai/v1/*).
    Route::prefix('v1')
        ->middleware([
            \NextDeveloper\AI\Http\Middlewares\AuthenticateLlmApiKey::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':' . config('ai.llm.throttle_per_minute', 120) . ',1',
        ])
        ->group(function () {
            Route::post('/chat/completions', 'Proxy\\ProxyController@chat');
            Route::post('/messages', 'Proxy\\ProxyController@messages');
            Route::get('/models', 'Proxy\\ProxyController@models');
            Route::post('/broadcast/{group}', 'Proxy\\ProxyController@broadcast');
        });

// EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE


});


