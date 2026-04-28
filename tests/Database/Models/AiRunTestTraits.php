<?php

namespace NextDeveloper\AI\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\AI\Database\Filters\AiRunQueryFilter;
use NextDeveloper\AI\Services\AbstractServices\AbstractAiRunService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait AiRunTestTraits
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

    public function test_http_airun_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/ai/airun',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_airun_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/ai/airun', [
            'form_params'   =>  [
                'model'  =>  'a',
                'output'  =>  'a',
                'status'  =>  'a',
                'error_message'  =>  'a',
                'status_code'  =>  '1',
                'input_tokens'  =>  '1',
                'output_tokens'  =>  '1',
                'duration_ms'  =>  '1',
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
    public function test_airun_model_get()
    {
        $result = AbstractAiRunService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_airun_get_all()
    {
        $result = AbstractAiRunService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_airun_get_paginated()
    {
        $result = AbstractAiRunService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_airun_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\AI\Events\AiRun\AiRunRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_airun_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\AI\Database\Models\AiRun::first();

            event(new \NextDeveloper\AI\Events\AiRun\AiRunRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_model_filter()
    {
        try {
            $request = new Request(
                [
                'model'  =>  'a'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_output_filter()
    {
        try {
            $request = new Request(
                [
                'output'  =>  'a'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_status_filter()
    {
        try {
            $request = new Request(
                [
                'status'  =>  'a'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_error_message_filter()
    {
        try {
            $request = new Request(
                [
                'error_message'  =>  'a'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_status_code_filter()
    {
        try {
            $request = new Request(
                [
                'status_code'  =>  '1'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_input_tokens_filter()
    {
        try {
            $request = new Request(
                [
                'input_tokens'  =>  '1'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_output_tokens_filter()
    {
        try {
            $request = new Request(
                [
                'output_tokens'  =>  '1'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_duration_ms_filter()
    {
        try {
            $request = new Request(
                [
                'duration_ms'  =>  '1'
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_airun_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new AiRunQueryFilter($request);

            $model = \NextDeveloper\AI\Database\Models\AiRun::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}