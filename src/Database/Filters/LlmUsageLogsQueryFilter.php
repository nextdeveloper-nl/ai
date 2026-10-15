<?php

namespace NextDeveloper\AI\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class LlmUsageLogsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function requestId($value)
    {
        return $this->builder->where('request_id', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of requestId
    public function request_id($value)
    {
        return $this->requestId($value);
    }

    public function broadcastId($value)
    {
        return $this->builder->where('broadcast_id', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of broadcastId
    public function broadcast_id($value)
    {
        return $this->broadcastId($value);
    }

    public function aiLlmApiKeyId($value)
    {
        $related = \NextDeveloper\AI\Database\Models\LlmApiKeys::where('uuid', $value)->first();

        if($related) {
            return $this->builder->where('ai_llm_api_key_id', '=', $related->id);
        }
    }

    //  This is an alias function of aiLlmApiKeyId
    public function ai_llm_api_key_id($value)
    {
        return $this->aiLlmApiKeyId($value);
    }

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

    public function provider($value)
    {
        return $this->builder->where('provider', 'ilike', '%' . $value . '%');
    }

    public function model($value)
    {
        return $this->builder->where('model', 'ilike', '%' . $value . '%');
    }

    public function requestedModel($value)
    {
        return $this->builder->where('requested_model', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of requestedModel
    public function requested_model($value)
    {
        return $this->requestedModel($value);
    }

    public function inputTokens($value)
    {
        return $this->builder->where('input_tokens', '=', $value);
    }

    //  This is an alias function of inputTokens
    public function input_tokens($value)
    {
        return $this->inputTokens($value);
    }

    public function outputTokens($value)
    {
        return $this->builder->where('output_tokens', '=', $value);
    }

    //  This is an alias function of outputTokens
    public function output_tokens($value)
    {
        return $this->outputTokens($value);
    }

    public function upstreamCost($value)
    {
        return $this->builder->where('upstream_cost', '=', $value);
    }

    //  This is an alias function of upstreamCost
    public function upstream_cost($value)
    {
        return $this->upstreamCost($value);
    }

    public function cost($value)
    {
        return $this->builder->where('cost', '=', $value);
    }

    public function billed($value)
    {
        return $this->builder->where('billed', '=', $value);
    }

    public function accountingCreditTransactionId($value)
    {
        return $this->builder->where('accounting_credit_transaction_id', '=', $value);
    }

    //  This is an alias function of accountingCreditTransactionId
    public function accounting_credit_transaction_id($value)
    {
        return $this->accountingCreditTransactionId($value);
    }

    public function status($value)
    {
        return $this->builder->where('status', 'ilike', '%' . $value . '%');
    }

    public function statusCode($value)
    {
        return $this->builder->where('status_code', '=', $value);
    }

    //  This is an alias function of statusCode
    public function status_code($value)
    {
        return $this->statusCode($value);
    }

    public function latencyMs($value)
    {
        return $this->builder->where('latency_ms', '=', $value);
    }

    //  This is an alias function of latencyMs
    public function latency_ms($value)
    {
        return $this->latencyMs($value);
    }

    public function ttftMs($value)
    {
        return $this->builder->where('ttft_ms', '=', $value);
    }

    //  This is an alias function of ttftMs
    public function ttft_ms($value)
    {
        return $this->ttftMs($value);
    }

    public function stream($value)
    {
        return $this->builder->where('stream', '=', $value);
    }

    public function errorMessage($value)
    {
        return $this->builder->where('error_message', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of errorMessage
    public function error_message($value)
    {
        return $this->errorMessage($value);
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

    public function alias($value)
    {
        return $this->builder->where('alias', 'ilike', '%' . $value . '%');
    }

    public function servedByFallback($value)
    {
        return $this->builder->where('served_by_fallback', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    public function served_by_fallback($value)
    {
        return $this->servedByFallback($value);
    }
}
