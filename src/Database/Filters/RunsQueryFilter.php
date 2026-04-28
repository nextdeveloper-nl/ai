<?php

namespace NextDeveloper\AI\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
            

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class RunsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function model($value)
    {
        return $this->builder->where('model', 'ilike', '%' . $value . '%');
    }

        
    public function output($value)
    {
        return $this->builder->where('output', 'ilike', '%' . $value . '%');
    }

        
    public function status($value)
    {
        return $this->builder->where('status', 'ilike', '%' . $value . '%');
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
    
    public function statusCode($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('status_code', $operator, $value);
    }

        //  This is an alias function of statusCode
    public function status_code($value)
    {
        return $this->statusCode($value);
    }
    
    public function inputTokens($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('input_tokens', $operator, $value);
    }

        //  This is an alias function of inputTokens
    public function input_tokens($value)
    {
        return $this->inputTokens($value);
    }
    
    public function outputTokens($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('output_tokens', $operator, $value);
    }

        //  This is an alias function of outputTokens
    public function output_tokens($value)
    {
        return $this->outputTokens($value);
    }
    
    public function durationMs($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('duration_ms', $operator, $value);
    }

        //  This is an alias function of durationMs
    public function duration_ms($value)
    {
        return $this->durationMs($value);
    }
    
    public function createdAtStart($date)
    {
        return $this->builder->where('created_at', '>=', $date);
    }

    public function createdAtEnd($date)
    {
        return $this->builder->where('created_at', '<=', $date);
    }

    //  This is an alias function of createdAt
    public function created_at_start($value)
    {
        return $this->createdAtStart($value);
    }

    //  This is an alias function of createdAt
    public function created_at_end($value)
    {
        return $this->createdAtEnd($value);
    }

    public function updatedAtStart($date)
    {
        return $this->builder->where('updated_at', '>=', $date);
    }

    public function updatedAtEnd($date)
    {
        return $this->builder->where('updated_at', '<=', $date);
    }

    //  This is an alias function of updatedAt
    public function updated_at_start($value)
    {
        return $this->updatedAtStart($value);
    }

    //  This is an alias function of updatedAt
    public function updated_at_end($value)
    {
        return $this->updatedAtEnd($value);
    }

    public function deletedAtStart($date)
    {
        return $this->builder->where('deleted_at', '>=', $date);
    }

    public function deletedAtEnd($date)
    {
        return $this->builder->where('deleted_at', '<=', $date);
    }

    //  This is an alias function of deletedAt
    public function deleted_at_start($value)
    {
        return $this->deletedAtStart($value);
    }

    //  This is an alias function of deletedAt
    public function deleted_at_end($value)
    {
        return $this->deletedAtEnd($value);
    }

    public function aiAgentId($value)
    {
            $aiAgent = \NextDeveloper\AI\Database\Models\Agents::where('uuid', $value)->first();

        if($aiAgent) {
            return $this->builder->where('ai_agent_id', '=', $aiAgent->id);
        }
    }

        //  This is an alias function of aiAgent
    public function ai_agent_id($value)
    {
        return $this->aiAgent($value);
    }
    
    public function iamUserId($value)
    {
            $iamUser = \NextDeveloper\IAM\Database\Models\Users::where('uuid', $value)->first();

        if($iamUser) {
            return $this->builder->where('iam_user_id', '=', $iamUser->id);
        }
    }

    
    public function iamAccountId($value)
    {
            $iamAccount = \NextDeveloper\IAM\Database\Models\Accounts::where('uuid', $value)->first();

        if($iamAccount) {
            return $this->builder->where('iam_account_id', '=', $iamAccount->id);
        }
    }

    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
