<?php

namespace NextDeveloper\AI\Http\Controllers\LlmBroadcastGroups;

use Illuminate\Http\Request;
use NextDeveloper\AI\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\AI\Http\Requests\LlmBroadcastGroups\LlmBroadcastGroupsUpdateRequest;
use NextDeveloper\AI\Database\Filters\LlmBroadcastGroupsQueryFilter;
use NextDeveloper\AI\Database\Models\LlmBroadcastGroups;
use NextDeveloper\AI\Services\LlmBroadcastGroupsService;
use NextDeveloper\AI\Http\Requests\LlmBroadcastGroups\LlmBroadcastGroupsCreateRequest;
class LlmBroadcastGroupsController extends AbstractController
{
    private $model = LlmBroadcastGroups::class;

    /**
     * This method returns the list of objects.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  LlmBroadcastGroupsQueryFilter $filter  An object that builds search query
     * @param  Request         $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(LlmBroadcastGroupsQueryFilter $filter, Request $request)
    {
        $data = LlmBroadcastGroupsService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This function returns the list of actions that can be performed on this object.
     *
     * @return void
     */
    public function getActions()
    {
        $data = LlmBroadcastGroupsService::getActions();

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * Makes the related action to the object
     *
     * @param  $objectId
     * @param  $action
     * @return array
     */
    public function doAction($objectId, $action)
    {
        $actionId = LlmBroadcastGroupsService::doAction($objectId, $action, request()->all());

        return $this->withArray(
            [
            'action_id' =>  $actionId
            ]
        );
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $aiLlmBroadcastGroupsId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = LlmBroadcastGroupsService::getByRef($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method returns the list of sub objects the related object. Sub object means an object which is preowned by
     * this object.
     *
     * It can be tags, addresses, states etc.
     *
     * @param  $ref
     * @param  $subObject
     * @return void
     */
    public function relatedObjects($ref, $subObject)
    {
        $objects = LlmBroadcastGroupsService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created LlmBroadcastGroups object on database.
     *
     * @param  LlmBroadcastGroupsCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(LlmBroadcastGroupsCreateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = LlmBroadcastGroupsService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates LlmBroadcastGroups object on database.
     *
     * @param  $aiLlmBroadcastGroupsId
     * @param  LlmBroadcastGroupsUpdateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($aiLlmBroadcastGroupsId, LlmBroadcastGroupsUpdateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = LlmBroadcastGroupsService::update($aiLlmBroadcastGroupsId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates LlmBroadcastGroups object on database.
     *
     * @param  $aiLlmBroadcastGroupsId
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($aiLlmBroadcastGroupsId)
    {
        $model = LlmBroadcastGroupsService::delete($aiLlmBroadcastGroupsId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
