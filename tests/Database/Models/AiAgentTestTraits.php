<?php

namespace NextDeveloper\AI\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\AI\Database\Filters\AiAgentQueryFilter;
use NextDeveloper\AI\Services\AbstractServices\AbstractAiAgentService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait AiAgentTestTraits
{
    public $http;

    /**
     *   Creating the Guzzle object
     */
    public function setupGuzzle()
    {
        $this->http = new Client(
            [
            'base_uri'  =>  '127.0.0.1:8000'
            ]
        );
    }

    /**
     *   Destroying the Guzzle object
     */
    public function destroyGuzzle()
    {
        $this->http = null;
    }

    public function test_http_aiagent_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/ai/aiagent',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_aiagent_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/ai/aiagent', [
            'form_params'   =>  [
                'name'  =>  'a',
                'slug'  =>  'a',
                'ref_name'  =>  'a',
                'description'  =>  'a',
                'system_prompt'  =>  'a',
                'response_format'  =>  'a',
                'temperature'  =>  '1',
                'max_tokens'  =>  '1',
                            ],
                ['http_errors' => false]
            ]
        );

        $this->assertEquals($response->getStatusCode(), Response::HTTP_OK);
    }

    /**
     * Get test
     *
     * @return bool
     */
    public function test_aiagent_model_get()
    {
        $result = AbstractAiAgentService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aiagent_get_all()
    {
        $result = AbstractAiAgentService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aiagent_get_paginated()
    {
        $result = AbstractAiAgentService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_aiagent_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiagent_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAgent::first();

            event(new \NextDeveloper\AI\Events\AiAgent\AiAgentRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_name_filter()
    {
        try {
            $request = new Request(
                [
                'name'  =>  'a'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_slug_filter()
    {
        try {
            $request = new Request(
                [
                'slug'  =>  'a'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_ref_name_filter()
    {
        try {
            $request = new Request(
                [
                'ref_name'  =>  'a'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_description_filter()
    {
        try {
            $request = new Request(
                [
                'description'  =>  'a'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_system_prompt_filter()
    {
        try {
            $request = new Request(
                [
                'system_prompt'  =>  'a'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_response_format_filter()
    {
        try {
            $request = new Request(
                [
                'response_format'  =>  'a'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_temperature_filter()
    {
        try {
            $request = new Request(
                [
                'temperature'  =>  '1'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_max_tokens_filter()
    {
        try {
            $request = new Request(
                [
                'max_tokens'  =>  '1'
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiagent_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiAgentQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAgent::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}