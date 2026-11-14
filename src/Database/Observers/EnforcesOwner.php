<?php

namespace NextDeveloper\AI\Database\Observers;

use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Makes sure a record belongs to the account and user who work with it, whatever the caller put in the data:
 * a new record gets the current account and user, an existing record keeps its owner. Only ai-admin may write
 * records for other accounts, and calls without a user (console commands) are left alone.
 */
trait EnforcesOwner
{
    protected function enforceOwner(Model $model): void
    {
        if (!UserHelper::me() || $this->isPlatformAdmin()) {
            return;
        }

        if ($model->exists) {
            $model->iam_account_id = $model->getOriginal('iam_account_id');
            $model->iam_user_id = $model->getOriginal('iam_user_id');

            return;
        }

        $account = UserHelper::currentAccount();

        throw_if(!$account, new NotAllowedException('You are not allowed to save this record'));

        $model->iam_account_id = $account->id;
        $model->iam_user_id = UserHelper::me()->id;
    }

    protected function isPlatformAdmin(): bool
    {
        return UserHelper::hasRole('ai-admin');
    }
}
