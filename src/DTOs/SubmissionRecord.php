<?php

namespace Krato\Verifactu\DTOs;

use Krato\Verifactu\Enums\InvoiceType;
use Krato\Verifactu\Enums\RecordType;
use Krato\Verifactu\Enums\SubmissionStatus;

final readonly class SubmissionRecord
{
    public function __construct(
        public string $nif,
        public InvoiceIdentifier $invoiceId,
        public RecordType $recordType,
        public InvoiceType $invoiceType,
        public string $xmlRequest,
        public string $hash,
        public SubmissionStatus $status = SubmissionStatus::Pending,
        public ?string $csv = null,
        public ?string $xmlResponse = null,
        public ?array $errors = null,
        public int $attemptCount = 1,
        public ?\DateTimeInterface $submittedAt = null,
        public ?\DateTimeInterface $respondedAt = null,
        public ?\DateTimeInterface $nextRetryAt = null,
        public ?string $tenantId = null,
    ) {}
}
