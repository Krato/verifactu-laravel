<?php

namespace Krato\Verifactu\DTOs;

final readonly class HashChainLink
{
    public function __construct(
        public string $invoiceNumber,
        public \DateTimeInterface $invoiceDate,
        public string $hash,
        public ?string $previousHash = null,
        public ?array $metadata = null,
    ) {}
}
