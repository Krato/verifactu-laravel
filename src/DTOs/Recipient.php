<?php

namespace Krato\Verifactu\DTOs;

final readonly class Recipient
{
    public function __construct(
        public string $nif,
        public string $name,
        public ?string $countryCode = 'ES',
        public ?string $idType = null,
    ) {}
}
