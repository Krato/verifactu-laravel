<?php

namespace Krato\Verifactu\Testing;

use PHPUnit\Framework\Assert;

class FakeTransport
{
    /** @var array<int, array{xml: string, timestamp: \DateTimeImmutable}> */
    private array $submissions = [];

    private ?string $fixedResponse = null;

    /**
     * Set a fixed XML response to return for all submissions.
     */
    public function respondWith(string $xmlResponse): static
    {
        $this->fixedResponse = $xmlResponse;

        return $this;
    }

    /**
     * Simulate sending a SOAP request. Records the submission and returns a response.
     */
    public function send(string $soapXml): string
    {
        $this->submissions[] = [
            'xml' => $soapXml,
            'timestamp' => new \DateTimeImmutable,
        ];

        if ($this->fixedResponse !== null) {
            return $this->fixedResponse;
        }

        return $this->defaultAcceptedResponse();
    }

    /**
     * Assert that a submission was made containing the given invoice number.
     */
    public function assertSubmitted(string $invoiceNumber): void
    {
        $found = false;
        foreach ($this->submissions as $submission) {
            if (str_contains($submission['xml'], $invoiceNumber)) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "No submission found containing invoice number: {$invoiceNumber}");
    }

    /**
     * Assert the total number of submissions.
     */
    public function assertSubmittedCount(int $count): void
    {
        Assert::assertCount($count, $this->submissions, "Expected {$count} submissions, got ".count($this->submissions));
    }

    /**
     * Assert no submissions were made.
     */
    public function assertNothingSubmitted(): void
    {
        Assert::assertCount(0, $this->submissions, 'Expected no submissions, got '.count($this->submissions));
    }

    /**
     * Get all recorded submissions.
     *
     * @return array<int, array{xml: string, timestamp: \DateTimeImmutable}>
     */
    public function submissions(): array
    {
        return $this->submissions;
    }

    /**
     * Reset recorded submissions.
     */
    public function reset(): void
    {
        $this->submissions = [];
    }

    private function defaultAcceptedResponse(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>Correcto</EstadoEnvio>
            <CSV>FAKE-CSV-001</CSV>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }
}
