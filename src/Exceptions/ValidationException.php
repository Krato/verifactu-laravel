<?php

namespace Krato\Verifactu\Exceptions;

class ValidationException extends VerifactuException
{
    /** @var string[] */
    public readonly array $violations;

    /**
     * @param  string[]  $violations
     */
    public function __construct(array $violations, ?\Throwable $previous = null)
    {
        $this->violations = $violations;

        $message = 'Invoice record validation failed: '.implode('; ', $violations);

        parent::__construct($message, 0, $previous);
    }
}
