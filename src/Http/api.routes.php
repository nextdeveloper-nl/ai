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

// EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE


});


