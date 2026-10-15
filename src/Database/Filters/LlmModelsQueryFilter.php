<?php

namespace NextDeveloper\AI\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class LlmModelsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function aiLlmProviderId($value)
    {
        $related = \NextDeveloper\AI\Database\Models\LlmProviders::where('uuid', $value)->first();

        if($related) {
            return $this->builder->where('ai_llm_provider_id', '=', $related->id);
        }
    }

    //  This is an alias function of aiLlmProviderId
    public function ai_llm_provider_id($value)
    {
        return $this->aiLlmProviderId($value);
    }

    public function modelId($value)
    {
        return $this->builder->where('model_id', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of modelId
    public function model_id($value)
    {
        return $this->modelId($value);
    }

    public function displayName($value)
    {
        return $this->builder->where('display_name', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of displayName
    public function display_name($value)
    {
        return $this->displayName($value);
    }

    public function inputPricePer1M($value)
    {
        return $this->builder->where('input_price_per_1m', '=', $value);
    }

    //  This is an alias function of inputPricePer1M
    public function input_price_per_1m($value)
    {
        return $this->inputPricePer1M($value);
    }

    public function outputPricePer1M($value)
    {
        return $this->builder->where('output_price_per_1m', '=', $value);
    }

    //  This is an alias function of outputPricePer1M
    public function output_price_per_1m($value)
    {
        return $this->outputPricePer1M($value);
    }

    public function contextWindow($value)
    {
        return $this->builder->where('context_window', '=', $value);
    }

    //  This is an alias function of contextWindow
    public function context_window($value)
    {
        return $this->contextWindow($value);
    }

    public function isActive($value)
    {
        return $this->builder->where('is_active', '=', $value);
    }

    //  This is an alias function of isActive
    public function is_active($value)
    {
        return $this->isActive($value);
    }

    public function createdAtStart($date)
    {
        return $this->builder->where('created_at', '>=', $date);
    }

    //  This is an alias function of createdAtStart
    public function created_at_start($value)
    {
        return $this->createdAtStart($value);
    }

    public function createdAtEnd($date)
    {
        return $this->builder->where('created_at', '<=', $date);
    }

    //  This is an alias function of createdAtEnd
    public function created_at_end($value)
    {
        return $this->createdAtEnd($value);
    }

    public function updatedAtStart($date)
    {
        return $this->builder->where('updated_at', '>=', $date);
    }

    //  This is an alias function of updatedAtStart
    public function updated_at_start($value)
    {
        return $this->updatedAtStart($value);
    }

    public function updatedAtEnd($date)
    {
        return $this->builder->where('updated_at', '<=', $date);
    }

    //  This is an alias function of updatedAtEnd
    public function updated_at_end($value)
    {
        return $this->updatedAtEnd($value);
    }

    public function iamUserId($value)
    {
        $related = \NextDeveloper\IAM\Database\Models\Users::where('uuid', $value)->first();

        if($related) {
            return $this->builder->where('iam_user_id', '=', $related->id);
        }
    }

    public function iamAccountId($value)
    {
        $related = \NextDeveloper\IAM\Database\Models\Accounts::where('uuid', $value)->first();

        if($related) {
            return $this->builder->where('iam_account_id', '=', $related->id);
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
