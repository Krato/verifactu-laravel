<?php

namespace Krato\Verifactu\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Krato\Verifactu\Contracts\SubmissionStore;
use Krato\Verifactu\DTOs\InvoiceIdentifier;
use Krato\Verifactu\DTOs\SubmissionRecord;
use Krato\Verifactu\DTOs\SubmissionResult;
use Krato\Verifactu\Enums\InvoiceType;
use Krato\Verifactu\Enums\RecordType;
use Krato\Verifactu\Enums\SubmissionStatus;

class DatabaseSubmissionStore implements SubmissionStore
{
    public function recordSubmission(SubmissionRecord $record): void
    {
        DB::table('verifactu_submissions')->updateOrInsert(
            [
                'nif' => $record->nif,
                'series' => $record->invoiceId->series,
                'invoice_number' => $record->invoiceId->number,
                'invoice_date' => $record->invoiceId->issueDate->format('Y-m-d'),
                'record_type' => $record->recordType->value,
            ],
            [
                'invoice_type' => $record->invoiceType->value,
                'xml_request' => $record->xmlRequest,
                'hash' => $record->hash,
                'status' => $record->status->value,
                'attempt_count' => $record->attemptCount,
                'submitted_at' => $record->submittedAt?->format('Y-m-d H:i:s'),
                'tenant_id' => $record->tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function recordResponse(
        string $nif,
        InvoiceIdentifier $invoiceId,
        string $recordType,
        SubmissionResult $result,
    ): void {
        DB::table('verifactu_submissions')
            ->where('nif', $nif)
            ->where('series', $invoiceId->series)
            ->where('invoice_number', $invoiceId->number)
            ->where('invoice_date', $invoiceId->issueDate->format('Y-m-d'))
            ->where('record_type', $recordType)
            ->update([
                'status' => $result->status->value,
                'csv' => $result->csv,
                'xml_response' => $result->xmlResponse,
                'errors' => ! empty($result->errors) ? json_encode(
                    array_map(fn ($e) => ['code' => $e->code, 'description' => $e->description], $result->errors)
                ) : null,
                'responded_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function getLastSubmission(string $nif, InvoiceIdentifier $invoiceId): ?SubmissionRecord
    {
        $row = DB::table('verifactu_submissions')
            ->where('nif', $nif)
            ->where('series', $invoiceId->series)
            ->where('invoice_number', $invoiceId->number)
            ->where('invoice_date', $invoiceId->issueDate->format('Y-m-d'))
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return new SubmissionRecord(
            nif: $row->nif,
            invoiceId: new InvoiceIdentifier(
                series: $row->series,
                number: $row->invoice_number,
                issueDate: new \DateTimeImmutable($row->invoice_date),
            ),
            recordType: RecordType::from($row->record_type),
            invoiceType: InvoiceType::from($row->invoice_type),
            xmlRequest: $row->xml_request,
            hash: $row->hash,
            status: SubmissionStatus::from($row->status),
            csv: $row->csv,
            xmlResponse: $row->xml_response,
            errors: $row->errors !== null ? json_decode($row->errors, true) : null,
            attemptCount: $row->attempt_count,
            submittedAt: $row->submitted_at !== null ? new \DateTimeImmutable($row->submitted_at) : null,
            respondedAt: $row->responded_at !== null ? new \DateTimeImmutable($row->responded_at) : null,
            tenantId: $row->tenant_id,
        );
    }

    public function getPendingRetries(): Collection
    {
        return DB::table('verifactu_submissions')
            ->where('status', SubmissionStatus::TransportError->value)
            ->where('attempt_count', '<', config('verifactu.retry.max_attempts', 3))
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->get()
            ->map(fn ($row) => new SubmissionRecord(
                nif: $row->nif,
                invoiceId: new InvoiceIdentifier(
                    series: $row->series,
                    number: $row->invoice_number,
                    issueDate: new \DateTimeImmutable($row->invoice_date),
                ),
                recordType: RecordType::from($row->record_type),
                invoiceType: InvoiceType::from($row->invoice_type),
                xmlRequest: $row->xml_request,
                hash: $row->hash,
                status: SubmissionStatus::from($row->status),
                csv: $row->csv,
                xmlResponse: $row->xml_response,
                errors: $row->errors !== null ? json_decode($row->errors, true) : null,
                attemptCount: $row->attempt_count,
                submittedAt: $row->submitted_at !== null ? new \DateTimeImmutable($row->submitted_at) : null,
                respondedAt: $row->responded_at !== null ? new \DateTimeImmutable($row->responded_at) : null,
                nextRetryAt: $row->next_retry_at !== null ? new \DateTimeImmutable($row->next_retry_at) : null,
                tenantId: $row->tenant_id,
            ));
    }
}
