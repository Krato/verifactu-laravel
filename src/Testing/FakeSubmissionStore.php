<?php

namespace Krato\Verifactu\Testing;

use Illuminate\Support\Collection;
use Krato\Verifactu\Contracts\SubmissionStore;
use Krato\Verifactu\DTOs\InvoiceIdentifier;
use Krato\Verifactu\DTOs\SubmissionRecord;
use Krato\Verifactu\DTOs\SubmissionResult;

class FakeSubmissionStore implements SubmissionStore
{
    /** @var SubmissionRecord[] */
    private array $submissions = [];

    public function recordSubmission(SubmissionRecord $record): void
    {
        $key = $this->key($record->nif, $record->invoiceId, $record->recordType->value);
        $this->submissions[$key] = $record;
    }

    public function recordResponse(
        string $nif,
        InvoiceIdentifier $invoiceId,
        string $recordType,
        SubmissionResult $result,
    ): void {
        $key = $this->key($nif, $invoiceId, $recordType);
        if (! isset($this->submissions[$key])) {
            return;
        }

        $existing = $this->submissions[$key];
        $this->submissions[$key] = new SubmissionRecord(
            nif: $existing->nif,
            invoiceId: $existing->invoiceId,
            recordType: $existing->recordType,
            invoiceType: $existing->invoiceType,
            xmlRequest: $existing->xmlRequest,
            hash: $existing->hash,
            status: $result->status,
            csv: $result->csv,
            xmlResponse: $result->xmlResponse,
            errors: ! empty($result->errors)
                ? array_map(fn ($e) => ['code' => $e->code, 'description' => $e->description], $result->errors)
                : null,
            attemptCount: $existing->attemptCount,
            submittedAt: $existing->submittedAt,
            respondedAt: new \DateTimeImmutable,
            tenantId: $existing->tenantId,
        );
    }

    public function getLastSubmission(string $nif, InvoiceIdentifier $invoiceId): ?SubmissionRecord
    {
        foreach ($this->submissions as $record) {
            if ($record->nif === $nif
                && $record->invoiceId->series === $invoiceId->series
                && $record->invoiceId->number === $invoiceId->number
                && $record->invoiceId->issueDate->format('Y-m-d') === $invoiceId->issueDate->format('Y-m-d')
            ) {
                return $record;
            }
        }

        return null;
    }

    public function getPendingRetries(): Collection
    {
        return collect($this->submissions)->filter(
            fn (SubmissionRecord $r) => $r->status->isRetryable()
        )->values();
    }

    public function reset(): void
    {
        $this->submissions = [];
    }

    /**
     * @return SubmissionRecord[]
     */
    public function all(): array
    {
        return array_values($this->submissions);
    }

    private function key(string $nif, InvoiceIdentifier $id, string $recordType): string
    {
        return $nif.'|'.$id->series.'|'.$id->number.'|'.$id->issueDate->format('Y-m-d').'|'.$recordType;
    }
}
