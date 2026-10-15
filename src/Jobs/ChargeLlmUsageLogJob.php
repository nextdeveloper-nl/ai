<?php

namespace NextDeveloper\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Accounting\Helpers\AccountingHelper;
use NextDeveloper\Accounting\Helpers\CreditHelper;
use NextDeveloper\AI\Database\Models\LlmUsageLogs;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Debits the account for one proxied LLM call (log.cost, already containing the markup).
 *
 * Billed after the fact (the call already happened), so it is not gated on available credit.
 * Throws when the log carries no cost so the queue retries instead of silently skipping
 * the charge. Idempotent: a log that is already billed is skipped.
 */
class ChargeLlmUsageLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(private LlmUsageLogs $usageLog)
    {
        $this->onQueue(config('ai.llm.queue', 'default'));
    }

    public function handle(): void
    {
        // Re-read inside the job: the log may have been billed by a previous attempt.
        $log = LlmUsageLogs::withoutGlobalScopes()->find($this->usageLog->id);

        if (!$log || $log->billed) {
            return;
        }

        if ($log->cost === null) {
            throw new \RuntimeException('Usage log id=' . $log->id . ' has no cost; refusing to bill at a guessed price.');
        }

        if ((float) $log->cost <= 0) {
            $this->markBilled($log, null);

            return;
        }

        $account = AccountingHelper::getAccountFromIamAccountId($log->iam_account_id);

        if (!$account) {
            Log::warning('[AI][ChargeLlmUsageLogJob] No accounting account for iam_account_id=' . $log->iam_account_id . ' (log id=' . $log->id . ')');

            return;
        }

        // runAsAdmin: queue worker has no authenticated user and the credit observers deny writes without one.
        UserHelper::runAsAdmin(function () use ($log, $account) {
            DB::transaction(function () use ($log, $account) {
                $transaction = CreditHelper::debit($account, (float) $log->cost, 'LLM usage - ' . $log->uuid, $log);
                $this->markBilled($log, $transaction->id);
            });
        });
    }

    private function markBilled(LlmUsageLogs $log, ?int $transactionId): void
    {
        // Direct query update: the log was written by the proxy under admin context, this only flips billing state.
        DB::table($log->getTable())->where('id', $log->id)->update([
            'billed'                           => true,
            'accounting_credit_transaction_id' => $transactionId,
            'updated_at'                       => now(),
        ]);
    }
}
