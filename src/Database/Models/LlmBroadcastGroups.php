<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Database\Traits\HasStates;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\AI\Database\Observers\LlmBroadcastGroupsObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * LlmBroadcastGroups model.
 *
 * @package  NextDeveloper\AI\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $name
 * @property string $mode
 * @property integer $timeout_ms
 * @property array $targets
 * @property boolean $is_active
 * @property integer $iam_user_id
 * @property integer $iam_account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class LlmBroadcastGroups extends Model
{
    use Filterable, UuidId, CleanCache, HasStates, RunAsAdministrator;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'ai_llm_broadcast_groups';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'name',
            'mode',
            'timeout_ms',
            'targets',
            'is_active',
            'iam_user_id',
            'iam_account_id',
    ];

    /**
     Here we have the fulltext fields. We can use these for fulltext search if enabled.
     */
    protected $fullTextFields = [

    ];

    /**
     @var array
     */
    protected $appends = [

    ];

    /**
     We are casting fields to objects so that we can work on them better
     *
     @var array
     */
    protected $casts = [
    'id' => 'integer',
    'name' => 'string',
    'mode' => 'string',
    'timeout_ms' => 'integer',
    'targets' => 'array',
    'is_active' => 'boolean',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    ];

    /**
     We are casting data fields.
     *
     @var array
     */
    protected $dates = [
    'created_at',
    'updated_at',
    'deleted_at',
    ];

    /**
     @var array
     */
    protected $with = [

    ];

    /**
     @var int
     */
    protected $perPage = 20;

    /**
     @return void
     */
    public static function boot()
    {
        parent::boot();

        self::registerScopes();
    }

    /**
     * Registers the observer once the model has finished booting (Laravel 12+ rejects doing it in boot()).
     *
     * @return void
     */
    protected static function booted()
    {
        parent::booted();

        static::observe(LlmBroadcastGroupsObserver::class);
    }

    public static function registerScopes()
    {
        $globalScopes = config('ai.scopes.global');
        $modelScopes = config('ai.scopes.ai_llm_broadcast_groups');

        if(!$modelScopes) { $modelScopes = [];
        }
        if (!$globalScopes) { $globalScopes = [];
        }

        $scopes = array_merge(
            $globalScopes,
            $modelScopes
        );

        if($scopes) {
            foreach ($scopes as $scope) {
                static::addGlobalScope(app($scope));
            }
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
