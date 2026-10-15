<?php

namespace NextDeveloper\AI\Http\Controllers\LlmLimits;

use NextDeveloper\AI\Http\Controllers\AbstractController;
use NextDeveloper\AI\Http\Requests\LlmLimits\LlmLimitsUpdateRequest;
use NextDeveloper\AI\Services\Llm\LlmSpendLimitService;
use NextDeveloper\AI\Services\LlmChatService;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Monthly spend limit of the current account (GET to read, PUT to change; changing needs ai-manager).
 */
class LlmLimitsController extends AbstractController
{
    public function show()
    {
        LlmChatService::assertAiRole();

        return $this->withArray(LlmSpendLimitService::status(UserHelper::currentAccount()->id));
    }

    public function update(LlmLimitsUpdateRequest $request)
    {
        $limit = $request->validated()['monthly_spend_limit'] ?? null;

        return $this->withArray(LlmSpendLimitService::setAccountLimit($limit === null ? null : (float) $limit));
    }
}
