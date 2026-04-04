<?php

namespace Krato\Verifactu\Exceptions;

use Krato\Verifactu\DTOs\SubmissionResult;

class SubmissionException extends VerifactuException
{
    public function __construct(
        string $message,
        public readonly ?SubmissionResult $result = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
