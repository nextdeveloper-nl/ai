<?php

namespace NextDeveloper\AI\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\AI\Database\Filters\AiSessionQueryFilter;
use NextDeveloper\AI\Services\AbstractServices\AbstractAiSessionService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait AiSessionTestTraits
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

    public function test_http_aisession_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/ai/aisession',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_aisession_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/ai/aisession', [
            'form_params'   =>  [
                'title'  =>  'a',
                'description'  =>  'a',
                'ai_engine_name'  =>  'a',
                'thread'  =>  'a',
                'run'  =>  'a',
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
    public function test_aisession_model_get()
    {
        $result = AbstractAiSessionService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aisession_get_all()
    {
        $result = AbstractAiSessionService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aisession_get_paginated()
    {
        $result = AbstractAiSessionService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_aisession_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiSession\AiSessionRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aisession_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiSession::first();

            event(new \NextDeveloper\AI\Events\AiSession\AiSessionRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_title_filter()
    {
        try {
            $request = new Request(
                [
                'title'  =>  'a'
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_description_filter()
    {
        try {
            $request = new Request(
                [
                'description'  =>  'a'
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_ai_engine_name_filter()
    {
        try {
            $request = new Request(
                [
                'ai_engine_name'  =>  'a'
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_thread_filter()
    {
        try {
            $request = new Request(
                [
                'thread'  =>  'a'
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_run_filter()
    {
        try {
            $request = new Request(
                [
                'run'  =>  'a'
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aisession_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiSessionQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiSession::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}