<?php

namespace NextDeveloper\AI\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Account scoping shared by the roles that work inside one account (ai-user and ai-manager).
 * Only ai-admin sees across accounts.
 */
trait ScopesAiRecordsToAccount
{
    /**
     * Tables whose records belong to the user who made them: other users of the same account do not see them
     * (only for roles that restrict to own records).
     */
    private const OWN_RECORDS_TABLES = ['ai_sessions', 'ai_runs'];

    /**
     * @param bool $ownRecordsOnly Also restrict sessions and runs to the current user.
     */
    protected function applyAccountScope(Builder $builder, Model $model, bool $ownRecordsOnly): void
    {
        $table = $model->getTable();
        $accountId = UserHelper::currentAccount()?->id;

        // Public helpers are available to everyone, whether they belong to an account or not
        // (platform helpers have no account). Private ones only to their own account.
        if ($table === 'ai_available_helpers') {
            $builder->where(function (Builder $query) use ($table, $accountId): void {
                $query->where($table.'.is_public', true)
                    ->orWhere($table.'.iam_account_id', $accountId);
            });

            return;
        }

        // Platform LLM providers and their models have no owning account and are visible to everybody.
        // Writes on them are blocked by the update/delete policies (account must match).
        if (in_array($table, ['ai_llm_providers', 'ai_llm_models'], true)) {
            $builder->where(function (Builder $query) use ($table, $accountId): void {
                $query->whereNull($table.'.iam_account_id')
                    ->orWhere($table.'.iam_account_id', $accountId);

                // Models the platform published (is_public) stay visible even when a platform account owns them.
                if ($table === 'ai_llm_models') {
                    $query->orWhere($table.'.is_public', true);
                }

                // Providers owned by the platform account are platform providers too (the transformer hides who they are).
                if ($table === 'ai_llm_providers' && ($platformAccountId = \NextDeveloper\AI\Services\PlatformAccount::id()) !== null) {
                    $query->orWhere($table.'.iam_account_id', $platformAccountId);
                }
            });

            return;
        }

        // A conversation has no owner columns of its own: it belongs to a session of the account
        // (and of the user, for roles that restrict to own records).
        if ($table === 'ai_conversations') {
            $builder->whereIn($table.'.ai_session_id', function ($query) use ($accountId, $ownRecordsOnly): void {
                $query->select('id')->from('ai_sessions')
                    ->where('iam_account_id', $accountId);

                if ($ownRecordsOnly) {
                    $query->where('iam_user_id', UserHelper::me()?->id);
                }
            });

            return;
        }

        $builder->where($table.'.iam_account_id', $accountId);

        if ($ownRecordsOnly && in_array($table, self::OWN_RECORDS_TABLES, true)) {
            $builder->where($table.'.iam_user_id', UserHelper::me()?->id);
        }
    }

    /**
     * A record can be changed only from its own account. Platform rows (no account) are never writable
     * through these roles; they belong to ai-admin.
     */
    protected function ownsAccountRecord(Model $model, Users $user, bool $ownRecordsOnly): bool
    {
        $accountId = UserHelper::currentAccount()?->id;

        if ($accountId === null || $model->iam_account_id === null) {
            return false;
        }

        if ((int) $model->iam_account_id !== (int) $accountId) {
            return false;
        }

        return !$ownRecordsOnly
            || !in_array($model->getTable(), self::OWN_RECORDS_TABLES, true)
            || (int) $model->iam_user_id === (int) $user->id;
    }
}
