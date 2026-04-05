<?php

namespace Krato\Verifactu\Validation;

use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\DTOs\TaxBreakdown;
use Krato\Verifactu\Enums\InvoiceType;
use Krato\Verifactu\Exceptions\ValidationException;

class InvoiceRecordValidator
{
    private const ALLOWED_INVOICE_TYPES = [InvoiceType::F1, InvoiceType::F2];

    private const NIF_PATTERN = '/^[A-Z0-9][0-9]{7}[A-Z0-9]$/';

    private const MAX_INVOICE_NUMBER_LENGTH = 60;

    private const MAX_SERIES_LENGTH = 20;

    /**
     * Validate an InvoiceRecord before submission. Throws on failure.
     *
     * @throws ValidationException
     */
    public function validate(InvoiceRecord $invoice): void
    {
        $violations = $this->collectViolations($invoice);

        if (! empty($violations)) {
            throw new ValidationException($violations);
        }
    }

    /**
     * @return string[]
     */
    private function collectViolations(InvoiceRecord $invoice): array
    {
        $violations = [];

        $this->validateInvoiceType($invoice, $violations);
        $this->validateIdentifier($invoice, $violations);
        $this->validateIssuer($invoice, $violations);
        $this->validateTaxBreakdowns($invoice, $violations);
        $this->validateTotalAmount($invoice, $violations);
        $this->validateIssueDate($invoice, $violations);

        return $violations;
    }

    /**
     * @param  string[]  &$violations
     */
    private function validateInvoiceType(InvoiceRecord $invoice, array &$violations): void
    {
        if (! in_array($invoice->getInvoiceType(), self::ALLOWED_INVOICE_TYPES, true)) {
            $violations[] = 'Invoice type must be F1 or F2 in v0.1. Got: '.$invoice->getInvoiceType()->value;
        }
    }

    /**
     * @param  string[]  &$violations
     */
    private function validateIdentifier(InvoiceRecord $invoice, array &$violations): void
    {
        $identifier = $invoice->getIdentifier();

        if (trim($identifier->number) === '') {
            $violations[] = 'Invoice number must not be empty.';
        } elseif (mb_strlen($identifier->number) > self::MAX_INVOICE_NUMBER_LENGTH) {
            $violations[] = 'Invoice number must not exceed '.self::MAX_INVOICE_NUMBER_LENGTH.' characters.';
        }

        if (mb_strlen($identifier->series) > self::MAX_SERIES_LENGTH) {
            $violations[] = 'Invoice series must not exceed '.self::MAX_SERIES_LENGTH.' characters.';
        }
    }

    /**
     * @param  string[]  &$violations
     */
    private function validateIssuer(InvoiceRecord $invoice, array &$violations): void
    {
        $issuer = $invoice->getIssuer();

        if (trim($issuer->nif) === '') {
            $violations[] = 'Issuer NIF must not be empty.';
        } elseif (! preg_match(self::NIF_PATTERN, strtoupper($issuer->nif))) {
            $violations[] = 'Issuer NIF format is invalid. Expected 9-character Spanish NIF (e.g. B12345678). Got: '.$issuer->nif;
        }

        if (trim($issuer->name) === '') {
            $violations[] = 'Issuer name must not be empty.';
        }
    }

    /**
     * @param  string[]  &$violations
     */
    private function validateTaxBreakdowns(InvoiceRecord $invoice, array &$violations): void
    {
        $breakdowns = $invoice->getTaxBreakdowns();

        if (empty($breakdowns)) {
            $violations[] = 'Tax breakdowns must not be empty.';

            return;
        }

        foreach ($breakdowns as $i => $breakdown) {
            if (! $breakdown instanceof TaxBreakdown) {
                $violations[] = "Tax breakdown at index {$i} must be an instance of TaxBreakdown.";

                continue;
            }

            if ($breakdown->taxBase < 0) {
                $violations[] = "Tax breakdown at index {$i}: tax base must not be negative.";
            }

            if ($breakdown->taxRate < 0 || $breakdown->taxRate > 100) {
                $violations[] = "Tax breakdown at index {$i}: tax rate must be between 0 and 100.";
            }
        }
    }

    /**
     * @param  string[]  &$violations
     */
    private function validateTotalAmount(InvoiceRecord $invoice, array &$violations): void
    {
        $total = $invoice->getTotalAmount();

        if (! is_finite($total)) {
            $violations[] = 'Total amount must be a finite number.';

            return;
        }

        if ($total < 0) {
            $violations[] = 'Total amount must not be negative.';
        }
    }

    /**
     * @param  string[]  &$violations
     */
    private function validateIssueDate(InvoiceRecord $invoice, array &$violations): void
    {
        $date = $invoice->getIssueDate();
        $identifierDate = $invoice->getIdentifier()->issueDate;

        $now = new \DateTimeImmutable('tomorrow');

        if ($date > $now) {
            $violations[] = 'Issue date must not be in the future.';
        }

        if ($date->format('Y-m-d') !== $identifierDate->format('Y-m-d')) {
            $violations[] = 'Issue date and identifier issue date must match.';
        }
    }
}
