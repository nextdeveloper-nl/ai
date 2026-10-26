<?php

namespace NextDeveloper\AI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use NextDeveloper\AI\Services\LlmChatStorageBilling;

/**
 * Hourly: charges the chat storage fee for the hour that just ended and deletes the chats of accounts that stayed
 * unpaid past the grace period.
 */
class ChargeChatStorageCommand extends Command
{
    protected $signature = 'ai:llm-charge-chat-storage {--hour= : Hour to charge, as a date-time (default: the hour that just ended)}';

    protected $description = 'Charge the hourly chat storage fee and purge chats of accounts that did not pay';

    public function handle(): int
    {
        $hour = $this->option('hour') ? Carbon::parse($this->option('hour')) : now()->subHour();

        $result = LlmChatStorageBilling::chargeHour($hour);
        $purged = LlmChatStorageBilling::purgeUnpaid();

        $this->info(sprintf('%s: %d charged, %d could not pay, %d skipped; %d account(s) purged.', $hour->copy()->startOfHour()->toDateTimeString(), $result['charged'], $result['unpaid'], $result['skipped'], $purged));

        return self::SUCCESS;
    }
}
