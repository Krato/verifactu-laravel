<?php

namespace Krato\Verifactu\Contracts;

use Krato\Verifactu\DTOs\InvoiceIdentifier;
use Krato\Verifactu\DTOs\Issuer;
use Krato\Verifactu\DTOs\Recipient;
use Krato\Verifactu\Enums\InvoiceType;

interface InvoiceRecord
{
    public function getIdentifier(): InvoiceIdentifier;

    public function getIssuer(): Issuer;

    public function getRecipient(): ?Recipient;

    public function getInvoiceType(): InvoiceType;

    public function getDescription(): string;

    /** @return \Krato\Verifactu\DTOs\TaxBreakdown[] */
    public function getTaxBreakdowns(): array;

    public function getTotalAmount(): float;

    public function getIssueDate(): \DateTimeInterface;
}
