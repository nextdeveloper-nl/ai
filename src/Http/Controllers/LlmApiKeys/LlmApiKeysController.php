<?php

namespace NextDeveloper\AI\Http\Controllers\LlmApiKeys;

use Illuminate\Http\Request;
use NextDeveloper\AI\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\AI\Http\Requests\LlmApiKeys\LlmApiKeysUpdateRequest;
use NextDeveloper\AI\Database\Filters\LlmApiKeysQueryFilter;
use NextDeveloper\AI\Database\Models\LlmApiKeys;
use NextDeveloper\AI\Services\LlmApiKeysService;
use NextDeveloper\AI\Http\Requests\LlmApiKeys\LlmApiKeysCreateRequest;
class LlmApiKeysController extends AbstractController
{
    private $model = LlmApiKeys::class;

    /**
     * This method returns the list of objects.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  LlmApiKeysQueryFilter $filter  An object that builds search query
     * @param  Request         $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(LlmApiKeysQueryFilter $filter, Request $request)
    {
        $data = LlmApiKeysService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This function returns the list of actions that can be performed on this object.
     *
     * @return void
     */
    public function getActions()
    {
        $data = LlmApiKeysService::getActions();

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
        $actionId = LlmApiKeysService::doAction($objectId, $action, request()->all());

        return $this->withArray(
            [
            'action_id' =>  $actionId
            ]
        );
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $aiLlmApiKeysId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = LlmApiKeysService::getByRef($ref);

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
        $objects = LlmApiKeysService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created LlmApiKeys object on database.
     *
     * @param  LlmApiKeysCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(LlmApiKeysCreateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = LlmApiKeysService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates LlmApiKeys object on database.
     *
     * @param  $aiLlmApiKeysId
     * @param  LlmApiKeysUpdateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($aiLlmApiKeysId, LlmApiKeysUpdateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = LlmApiKeysService::update($aiLlmApiKeysId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates LlmApiKeys object on database.
     *
     * @param  $aiLlmApiKeysId
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($aiLlmApiKeysId)
    {
        $model = LlmApiKeysService::delete($aiLlmApiKeysId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Revokes the API key. The key stays in the table so usage logs keep their reference.
     *
     * @param  $ref
     * @return mixed|null
     */
    public function revoke($ref)
    {
        $model = LlmApiKeysService::revoke($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * Revokes the API key and returns a new one (the response is the only time the plaintext key is visible).
     *
     * @param  $ref
     * @return mixed|null
     */
    public function regenerate($ref)
    {
        $model = LlmApiKeysService::regenerate($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

}
