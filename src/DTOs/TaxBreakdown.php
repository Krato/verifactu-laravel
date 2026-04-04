<?php

namespace Krato\Verifactu\DTOs;

use Krato\Verifactu\Enums\TaxRegime;
use Krato\Verifactu\Enums\TaxType;

final readonly class TaxBreakdown
{
    public function __construct(
        public TaxType $taxType,
        public TaxRegime $taxRegime,
        public float $taxBase,
        public float $taxRate,
        public float $taxAmount,
        public ?float $surchargeRate = null,
        public ?float $surchargeAmount = null,
    ) {}
}
