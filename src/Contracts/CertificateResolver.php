<?php

namespace Krato\Verifactu\Contracts;

use Krato\Verifactu\DTOs\CertificateCredentials;

interface CertificateResolver
{
    public function resolve(string $nif): CertificateCredentials;
}
