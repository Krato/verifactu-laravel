<?php

namespace Krato\Verifactu\Testing;

use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\DTOs\InvoiceIdentifier;
use Krato\Verifactu\DTOs\Issuer;
use Krato\Verifactu\DTOs\Recipient;
use Krato\Verifactu\DTOs\TaxBreakdown;
use Krato\Verifactu\Enums\InvoiceType;
use Krato\Verifactu\Enums\TaxRegime;
use Krato\Verifactu\Enums\TaxType;

class InvoiceRecordFactory implements InvoiceRecord
{
    private InvoiceIdentifier $identifier;

    private Issuer $issuer;

    private ?Recipient $recipient;

    private InvoiceType $invoiceType;

    private string $description;

    /** @var TaxBreakdown[] */
    private array $taxBreakdowns;

    private float $totalAmount;

    private \DateTimeInterface $issueDate;

    public function __construct()
    {
        $this->issueDate = new \DateTimeImmutable('2027-01-15');
        $this->identifier = new InvoiceIdentifier('FA', '001', $this->issueDate);
        $this->issuer = new Issuer('B12345678', 'Empresa Test S.L.');
        $this->recipient = new Recipient('A87654321', 'Cliente Test S.A.');
        $this->invoiceType = InvoiceType::F1;
        $this->description = 'Servicios de consultoría';
        $this->totalAmount = 1210.00;
        $this->taxBreakdowns = [
            new TaxBreakdown(
                taxType: TaxType::IVA,
                taxRegime: TaxRegime::General,
                taxBase: 1000.00,
                taxRate: 21.00,
                taxAmount: 210.00,
            ),
        ];
    }

    public static function make(): static
    {
        return new static;
    }

    public function withIdentifier(string $series, string $number, ?\DateTimeInterface $date = null): static
    {
        $clone = clone $this;
        $clone->identifier = new InvoiceIdentifier($series, $number, $date ?? $clone->issueDate);

        return $clone;
    }

    public function withIssuer(string $nif, string $name): static
    {
        $clone = clone $this;
        $clone->issuer = new Issuer($nif, $name);

        return $clone;
    }

    public function withRecipient(?string $nif = null, ?string $name = null): static
    {
        $clone = clone $this;
        $clone->recipient = $nif !== null ? new Recipient($nif, $name ?? 'Test Recipient') : null;

        return $clone;
    }

    public function withoutRecipient(): static
    {
        $clone = clone $this;
        $clone->recipient = null;

        return $clone;
    }

    public function withInvoiceType(InvoiceType $type): static
    {
        $clone = clone $this;
        $clone->invoiceType = $type;

        return $clone;
    }

    public function withDescription(string $description): static
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function withTotalAmount(float $amount): static
    {
        $clone = clone $this;
        $clone->totalAmount = $amount;

        return $clone;
    }

    /**
     * @param  TaxBreakdown[]  $breakdowns
     */
    public function withTaxBreakdowns(array $breakdowns): static
    {
        $clone = clone $this;
        $clone->taxBreakdowns = $breakdowns;

        return $clone;
    }

    public function withIssueDate(\DateTimeInterface $date): static
    {
        $clone = clone $this;
        $clone->issueDate = $date;
        $clone->identifier = new InvoiceIdentifier(
            $clone->identifier->series,
            $clone->identifier->number,
            $date,
        );

        return $clone;
    }

    // InvoiceRecord interface implementation

    public function getIdentifier(): InvoiceIdentifier
    {
        return $this->identifier;
    }

    public function getIssuer(): Issuer
    {
        return $this->issuer;
    }

    public function getRecipient(): ?Recipient
    {
        return $this->recipient;
    }

    public function getInvoiceType(): InvoiceType
    {
        return $this->invoiceType;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTaxBreakdowns(): array
    {
        return $this->taxBreakdowns;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getIssueDate(): \DateTimeInterface
    {
        return $this->issueDate;
    }
}
