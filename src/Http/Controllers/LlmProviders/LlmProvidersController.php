<?php

namespace NextDeveloper\AI\Http\Controllers\LlmProviders;

use Illuminate\Http\Request;
use NextDeveloper\AI\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\AI\Http\Requests\LlmProviders\LlmProvidersUpdateRequest;
use NextDeveloper\AI\Database\Filters\LlmProvidersQueryFilter;
use NextDeveloper\AI\Database\Models\LlmProviders;
use NextDeveloper\AI\Services\LlmProvidersService;
use NextDeveloper\AI\Http\Requests\LlmProviders\LlmProvidersCreateRequest;
class LlmProvidersController extends AbstractController
{
    private $model = LlmProviders::class;

    /**
     * This method returns the list of objects.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  LlmProvidersQueryFilter $filter  An object that builds search query
     * @param  Request         $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(LlmProvidersQueryFilter $filter, Request $request)
    {
        $data = LlmProvidersService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This function returns the list of actions that can be performed on this object.
     *
     * @return void
     */
    public function getActions()
    {
        $data = LlmProvidersService::getActions();

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
        $actionId = LlmProvidersService::doAction($objectId, $action, request()->all());

        return $this->withArray(
            [
            'action_id' =>  $actionId
            ]
        );
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $aiLlmProvidersId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = LlmProvidersService::getByRef($ref);

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
        $objects = LlmProvidersService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created LlmProviders object on database.
     *
     * @param  LlmProvidersCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(LlmProvidersCreateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = LlmProvidersService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates LlmProviders object on database.
     *
     * @param  $aiLlmProvidersId
     * @param  LlmProvidersUpdateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($aiLlmProvidersId, LlmProvidersUpdateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = LlmProvidersService::update($aiLlmProvidersId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates LlmProviders object on database.
     *
     * @param  $aiLlmProvidersId
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($aiLlmProvidersId)
    {
        $model = LlmProvidersService::delete($aiLlmProvidersId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Pulls the model list from the upstream provider and stores the models we do not know yet.
     *
     * @param  $ref
     * @return array
     */
    public function syncModels($ref)
    {
        $created = LlmProvidersService::syncModels($ref);

        return $this->withArray(['created' => $created]);
    }

}
