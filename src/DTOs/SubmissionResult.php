<?php

namespace Krato\Verifactu\DTOs;

use Krato\Verifactu\Enums\SubmissionStatus;

final readonly class SubmissionResult
{
    /**
     * @param  SubmissionError[]  $errors
     */
    public function __construct(
        public SubmissionStatus $status,
        public ?string $csv = null,
        public ?string $xmlResponse = null,
        public array $errors = [],
    ) {}

    public function isAccepted(): bool
    {
        return in_array($this->status, [
            SubmissionStatus::Accepted,
            SubmissionStatus::AcceptedWithErrors,
        ]);
    }

    public function isRejected(): bool
    {
        return $this->status === SubmissionStatus::Rejected;
    }
}
