<?php

namespace NextDeveloper\AI\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Accounting\Helpers\AccountingHelper;
use NextDeveloper\Accounting\Helpers\CreditHelper;
use NextDeveloper\AI\Database\Models\LlmChatStorageCharges;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * The hourly fee for the encrypted chat bytes an account keeps: stored MB x ai.llm.chat_storage_price_per_mb_hour.
 * An account that cannot pay is marked unpaid (its chats turn read-only) and its chats are deleted after
 * ai.llm.chat_unpaid_grace_days.
 */
class LlmChatStorageBilling
{
    private const BYTES_PER_MB = '1048576';

    public static function storedBytes(int $accountId): int
    {
        return (int) DB::table('ai_llm_chat_messages as m')
            ->join('ai_llm_chats as c', 'c.id', '=', 'm.ai_llm_chat_id')
            ->where('c.iam_account_id', $accountId)
            ->whereNull('c.deleted_at')
            ->sum('m.content_bytes');
    }

    public static function isUnpaid(int $accountId): bool
    {
        return DB::table('ai_accounts')->where('iam_account_id', $accountId)->whereNull('deleted_at')->value('llm_chat_unpaid_since') !== null;
    }

    /**
     * Fee in USD for keeping $bytes for one hour, 8 decimals, rounded down so a customer is never charged more than the price list.
     */
    public static function amountFor(int $bytes, string $pricePerMbHour): string
    {
        return bcdiv(bcmul((string) max(0, $bytes), $pricePerMbHour, 12), self::BYTES_PER_MB, 8);
    }

    /**
     * Charges every account with stored chats for the hour. Safe to run twice for the same hour.
     *
     * @return array{charged: int, unpaid: int, skipped: int}
     */
    public static function chargeHour(Carbon $hour): array
    {
        $result = ['charged' => 0, 'unpaid' => 0, 'skipped' => 0];
        $price = (string) config('ai.llm.chat_storage_price_per_mb_hour', '0');

        // No price set: storing is free and nothing is recorded.
        if (!is_numeric($price) || bccomp($price, '0', 6) <= 0) {
            return $result;
        }

        $hour = $hour->copy()->startOfHour();
        $accounts = DB::table('ai_llm_chats')->whereNull('deleted_at')->distinct()->pluck('iam_account_id');

        foreach ($accounts as $accountId) {
            if (LlmChatStorageCharges::where('iam_account_id', $accountId)->where('hour_start', $hour)->exists()) {
                $result['skipped']++;

                continue;
            }

            $bytes = self::storedBytes((int) $accountId);
            $amount = self::amountFor($bytes, $price);

            if ($bytes === 0 || bccomp($amount, '0', 8) <= 0) {
                $result['skipped']++;

                continue;
            }

            $charge = LlmChatStorageCharges::create([
                'iam_account_id'    => $accountId,
                'hour_start'        => $hour,
                'stored_bytes'      => $bytes,
                'price_per_mb_hour' => $price,
                'amount'            => $amount,
            ]);

            self::collect((int) $accountId, $charge, $hour) ? $result['charged']++ : $result['unpaid']++;
        }

        return $result;
    }

    /**
     * Deletes the chats of accounts that stayed unpaid longer than the grace period.
     *
     * @return int number of accounts purged
     */
    public static function purgeUnpaid(): int
    {
        $days = (int) config('ai.llm.chat_unpaid_grace_days', 30);
        $accounts = DB::table('ai_accounts')
            ->whereNull('deleted_at')
            ->whereNotNull('llm_chat_unpaid_since')
            ->where('llm_chat_unpaid_since', '<', now()->subDays($days))
            ->pluck('iam_account_id');

        foreach ($accounts as $accountId) {
            $chatIds = DB::table('ai_llm_chats')->where('iam_account_id', $accountId)->whereNull('deleted_at')->pluck('id');

            DB::transaction(function () use ($accountId, $chatIds) {
                DB::table('ai_llm_chat_messages')->whereIn('ai_llm_chat_id', $chatIds)->delete();
                DB::table('ai_llm_chats')->whereIn('id', $chatIds)->update(['deleted_at' => now()]);
                DB::table('ai_accounts')->where('iam_account_id', $accountId)->update(['llm_chat_unpaid_since' => null]);
            });

            Log::warning('[AI][ChatStorage] Deleted the chats of an account that did not pay the storage fee.', ['account' => $accountId, 'chats' => $chatIds->count(), 'grace_days' => $days]);
        }

        return $accounts->count();
    }

    /**
     * Debits the fee, or marks the account unpaid when it cannot cover it.
     */
    private static function collect(int $accountId, LlmChatStorageCharges $charge, Carbon $hour): bool
    {
        $account = AccountingHelper::getAccountFromIamAccountId($accountId);

        if (!$account || CreditHelper::getCredit($account) < (float) $charge->amount) {
            DB::table('ai_accounts')->where('iam_account_id', $accountId)->whereNull('llm_chat_unpaid_since')->update(['llm_chat_unpaid_since' => now()]);

            return false;
        }

        // runAsAdmin: the scheduler has no logged-in user and the credit observers refuse writes without one.
        UserHelper::runAsAdmin(function () use ($account, $charge, $hour, $accountId) {
            DB::transaction(function () use ($account, $charge, $hour, $accountId) {
                $transaction = CreditHelper::debit($account, (float) $charge->amount, 'LLM chat storage - ' . $hour->toDateTimeString(), $charge);

                $charge->forceFill(['billed' => true, 'accounting_credit_transaction_id' => $transaction->id])->save();
                DB::table('ai_accounts')->where('iam_account_id', $accountId)->update(['llm_chat_unpaid_since' => null]);
            });
        });

        return true;
    }
}
