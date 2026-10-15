<?php

namespace NextDeveloper\AI\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class LlmBroadcastGroupsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function name($value)
    {
        return $this->builder->where('name', 'ilike', '%' . $value . '%');
    }

    public function mode($value)
    {
        return $this->builder->where('mode', 'ilike', '%' . $value . '%');
    }

    public function timeoutMs($value)
    {
        return $this->builder->where('timeout_ms', '=', $value);
    }

    //  This is an alias function of timeoutMs
    public function timeout_ms($value)
    {
        return $this->timeoutMs($value);
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
