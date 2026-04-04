<?php

namespace Krato\Verifactu\Transport;

class Endpoints
{
    private const PRODUCTION_BASE = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/SuministroFacturacion';

    private const TESTING_BASE = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/SuministroFacturacion';

    public function __construct(
        private readonly string $environment = 'testing',
    ) {}

    public function submission(): string
    {
        return $this->isProduction()
            ? self::PRODUCTION_BASE
            : self::TESTING_BASE;
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function isTesting(): bool
    {
        return ! $this->isProduction();
    }
}
