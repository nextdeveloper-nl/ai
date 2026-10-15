<?php

namespace NextDeveloper\AI\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Database\Traits\HasStates;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\AI\Database\Observers\LlmUsageLogsObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * LlmUsageLogs model.
 *
 * @package  NextDeveloper\AI\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $request_id
 * @property string $broadcast_id
 * @property integer $ai_llm_api_key_id
 * @property integer $ai_llm_provider_id
 * @property string $provider
 * @property string $model
 * @property string $requested_model
 * @property integer $input_tokens
 * @property integer $output_tokens
 * @property $upstream_cost
 * @property $cost
 * @property boolean $billed
 * @property integer $accounting_credit_transaction_id
 * @property string $status
 * @property integer $status_code
 * @property integer $latency_ms
 * @property integer $ttft_ms
 * @property boolean $stream
 * @property string $error_message
 * @property integer $iam_user_id
 * @property integer $iam_account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class LlmUsageLogs extends Model
{
    use Filterable, UuidId, CleanCache, HasStates, RunAsAdministrator;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'ai_llm_usage_logs';

    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'request_id',
            'broadcast_id',
            'ai_llm_api_key_id',
            'ai_llm_provider_id',
            'provider',
            'model',
            'requested_model',
            'input_tokens',
            'output_tokens',
            'upstream_cost',
            'cost',
            'billed',
            'accounting_credit_transaction_id',
            'status',
            'status_code',
            'latency_ms',
            'ttft_ms',
            'stream',
            'error_message',
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
    'request_id' => 'string',
    'broadcast_id' => 'string',
    'ai_llm_api_key_id' => 'integer',
    'ai_llm_provider_id' => 'integer',
    'provider' => 'string',
    'model' => 'string',
    'requested_model' => 'string',
    'input_tokens' => 'integer',
    'output_tokens' => 'integer',
    'billed' => 'boolean',
    'accounting_credit_transaction_id' => 'integer',
    'status' => 'string',
    'status_code' => 'integer',
    'latency_ms' => 'integer',
    'ttft_ms' => 'integer',
    'stream' => 'boolean',
    'error_message' => 'string',
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

        static::observe(LlmUsageLogsObserver::class);
    }

    public static function registerScopes()
    {
        $globalScopes = config('ai.scopes.global');
        $modelScopes = config('ai.scopes.ai_llm_usage_logs');

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

    public function aiLlmApiKey() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\AI\Database\Models\LlmApiKeys::class, 'ai_llm_api_key_id');
    }

    public function aiLlmProvider() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\AI\Database\Models\LlmProviders::class, 'ai_llm_provider_id');
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
