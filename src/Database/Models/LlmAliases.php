<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Database\Traits\HasStates;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\AI\Database\Observers\LlmAliasesObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * LlmAliases model.
 *
 * @package  NextDeveloper\AI\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $alias
 * @property string $target_provider
 * @property string $target_model
 * @property boolean $is_active
 * @property integer $iam_user_id
 * @property integer $iam_account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class LlmAliases extends Model
{
    use Filterable, UuidId, CleanCache, HasStates, RunAsAdministrator;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'ai_llm_aliases';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'alias',
            'target_provider',
            'target_model',
            'is_active',
            'description',
            'fallbacks',
            'fallback_on_error',
            'fallback_on_rate_limit',
            'fallback_on_timeout',
            'fallback_timeout_seconds',
            'temperature',
            'max_tokens',
            'system_prompt',
            'monthly_spend_limit',
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
    'alias' => 'string',
    'target_provider' => 'string',
    'target_model' => 'string',
    'is_active' => 'boolean',
    'fallbacks' => 'array',
    'fallback_on_error' => 'boolean',
    'fallback_on_rate_limit' => 'boolean',
    'fallback_on_timeout' => 'boolean',
    'fallback_timeout_seconds' => 'integer',
    'max_tokens' => 'integer',
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

        static::observe(LlmAliasesObserver::class);
    }

    public static function registerScopes()
    {
        $globalScopes = config('ai.scopes.global');
        $modelScopes = config('ai.scopes.ai_llm_aliases');

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
