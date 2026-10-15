<?php

namespace NextDeveloper\AI\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class LlmApiKeysQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function name($value)
    {
        return $this->builder->where('name', 'ilike', '%' . $value . '%');
    }

    public function keyPrefix($value)
    {
        return $this->builder->where('key_prefix', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of keyPrefix
    public function key_prefix($value)
    {
        return $this->keyPrefix($value);
    }

    public function status($value)
    {
        return $this->builder->where('status', 'ilike', '%' . $value . '%');
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
