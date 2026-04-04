<?php

namespace Krato\Verifactu\DTOs;

final readonly class CertificateCredentials
{
    public function __construct(
        public string $path,
        public string $password,
    ) {}
}
