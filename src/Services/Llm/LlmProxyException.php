<?php

namespace NextDeveloper\AI\Services\Llm;

use RuntimeException;

/**
 * Error raised while proxying a request. Rendered to the caller in OpenAI error format.
 */
class LlmProxyException extends RuntimeException
{
    public function __construct(string $message, private int $status = 400, private string $type = 'invalid_request_error', private ?string $errorCode = null)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function toArray(): array
    {
        return ['error' => ['message' => $this->getMessage(), 'type' => $this->type, 'code' => $this->errorCode]];
    }
}
