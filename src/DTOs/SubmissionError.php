<?php

namespace Krato\Verifactu\DTOs;

final readonly class SubmissionError
{
    public function __construct(
        public string $code,
        public string $description,
    ) {}
}
