<?php

namespace NextDeveloper\AI\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\AI\Database\Filters\AiConversationQueryFilter;
use NextDeveloper\AI\Services\AbstractServices\AbstractAiConversationService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait AiConversationTestTraits
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

    public function test_http_aiconversation_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/ai/aiconversation',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_aiconversation_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/ai/aiconversation', [
            'form_params'   =>  [
                'role'  =>  'a',
                'message'  =>  'a',
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
    public function test_aiconversation_model_get()
    {
        $result = AbstractAiConversationService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aiconversation_get_all()
    {
        $result = AbstractAiConversationService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aiconversation_get_paginated()
    {
        $result = AbstractAiConversationService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_aiconversation_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiconversation_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiConversation::first();

            event(new \NextDeveloper\AI\Events\AiConversation\AiConversationRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_role_filter()
    {
        try {
            $request = new Request(
                [
                'role'  =>  'a'
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_message_filter()
    {
        try {
            $request = new Request(
                [
                'message'  =>  'a'
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiconversation_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiConversationQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiConversation::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}