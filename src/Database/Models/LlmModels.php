<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Database\Traits\HasStates;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\AI\Database\Observers\LlmModelsObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * LlmModels model.
 *
 * @package  NextDeveloper\AI\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property integer $ai_llm_provider_id
 * @property string $model_id
 * @property string $display_name
 * @property $input_price_per_1m
 * @property $output_price_per_1m
 * @property $cache_read_price_per_1m
 * @property $cache_write_price_per_1m
 * @property string $public_id
 * @property integer $context_window
 * @property array $capabilities
 * @property boolean $is_active
 * @property boolean $is_public
 * @property integer $iam_user_id
 * @property integer $iam_account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class LlmModels extends Model
{
    use Filterable, UuidId, CleanCache, HasStates, RunAsAdministrator;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'ai_llm_models';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'ai_llm_provider_id',
            'model_id',
            'display_name',
            'input_price_per_1m',
            'output_price_per_1m',
            'cache_read_price_per_1m',
            'cache_write_price_per_1m',
            'public_id',
            'context_window',
            'capabilities',
            'is_active',
            'is_public',
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
    'ai_llm_provider_id' => 'integer',
    'model_id' => 'string',
    'display_name' => 'string',
    'context_window' => 'integer',
    'capabilities' => 'array',
    'is_active' => 'boolean',
    'is_public' => 'boolean',
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

        static::observe(LlmModelsObserver::class);
    }

    public static function registerScopes()
    {
        $globalScopes = config('ai.scopes.global');
        $modelScopes = config('ai.scopes.ai_llm_models');

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

    public function aiLlmProvider() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\AI\Database\Models\LlmProviders::class, 'ai_llm_provider_id');
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Short alias of aiLlmProvider(), used by the proxy services.
     */
    public function provider() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\AI\Database\Models\LlmProviders::class, 'ai_llm_provider_id');
    }
}
