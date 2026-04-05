<?php

namespace Krato\Verifactu\Exceptions;

class DuplicateSubmissionException extends VerifactuException
{
    public function __construct(string $invoiceIdentity, string $recordType, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Invoice '{$invoiceIdentity}' with record type '{$recordType}' has already been accepted. Duplicate submission prevented.",
            0,
            $previous,
        );
    }
}
