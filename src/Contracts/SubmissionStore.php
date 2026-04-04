<?php

namespace Krato\Verifactu\Contracts;

use Illuminate\Support\Collection;
use Krato\Verifactu\DTOs\InvoiceIdentifier;
use Krato\Verifactu\DTOs\SubmissionRecord;
use Krato\Verifactu\DTOs\SubmissionResult;

interface SubmissionStore
{
    public function recordSubmission(SubmissionRecord $record): void;

    public function recordResponse(
        string $nif,
        InvoiceIdentifier $invoiceId,
        string $recordType,
        SubmissionResult $result
    ): void;

    public function getLastSubmission(string $nif, InvoiceIdentifier $invoiceId): ?SubmissionRecord;

    /** @return Collection<int, SubmissionRecord> */
    public function getPendingRetries(): Collection;
}
