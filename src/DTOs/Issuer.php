<?php

namespace Krato\Verifactu\DTOs;

final readonly class Issuer
{
    public function __construct(
        public string $nif,
        public string $name,
    ) {}
}
