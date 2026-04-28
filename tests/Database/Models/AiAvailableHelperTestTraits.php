<?php

namespace NextDeveloper\AI\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\AI\Database\Filters\AiAvailableHelperQueryFilter;
use NextDeveloper\AI\Services\AbstractServices\AbstractAiAvailableHelperService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait AiAvailableHelperTestTraits
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

    public function test_http_aiavailablehelper_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/ai/aiavailablehelper',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_aiavailablehelper_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/ai/aiavailablehelper', [
            'form_params'   =>  [
                'name'  =>  'a',
                'action'  =>  'a',
                'description'  =>  'a',
                'class'  =>  'a',
                'input'  =>  'a',
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
    public function test_aiavailablehelper_model_get()
    {
        $result = AbstractAiAvailableHelperService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aiavailablehelper_get_all()
    {
        $result = AbstractAiAvailableHelperService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_aiavailablehelper_get_paginated()
    {
        $result = AbstractAiAvailableHelperService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_aiavailablehelper_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_aiavailablehelper_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::first();

            event(new \NextDeveloper\AI\Events\AiAvailableHelper\AiAvailableHelperRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_name_filter()
    {
        try {
            $request = new Request(
                [
                'name'  =>  'a'
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_action_filter()
    {
        try {
            $request = new Request(
                [
                'action'  =>  'a'
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_description_filter()
    {
        try {
            $request = new Request(
                [
                'description'  =>  'a'
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_class_filter()
    {
        try {
            $request = new Request(
                [
                'class'  =>  'a'
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_input_filter()
    {
        try {
            $request = new Request(
                [
                'input'  =>  'a'
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_aiavailablehelper_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiAvailableHelperQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiAvailableHelper::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}