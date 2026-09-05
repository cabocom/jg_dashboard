<?php

namespace App\Exceptions;

use Exception;

class AgentHttpException extends Exception
{
    protected ?int $statusCode;
    protected ?array $responseBody;

    public function __construct(
        string $message = '',
        ?int $statusCode = null,
        ?array $responseBody = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}

class AgentConnectionException extends AgentHttpException
{
}

class AgentTimeoutException extends AgentHttpException
{
}
