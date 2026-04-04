<?php

namespace Krato\Verifactu\DTOs;

final readonly class InvoiceIdentifier
{
    public function __construct(
        public string $series,
        public string $number,
        public \DateTimeInterface $issueDate,
    ) {}

    public function fullNumber(): string
    {
        return $this->series !== '' ? "{$this->series}-{$this->number}" : $this->number;
    }
}
