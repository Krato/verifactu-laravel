<?php

namespace Krato\Verifactu;

use Krato\Verifactu\Contracts\CertificateResolver;
use Krato\Verifactu\Contracts\HashChainStore;
use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\Contracts\SubmissionStore;
use Krato\Verifactu\DTOs\CertificateCredentials;
use Krato\Verifactu\DTOs\SubmissionRecord;
use Krato\Verifactu\DTOs\SubmissionResult;
use Krato\Verifactu\Enums\RecordType;
use Krato\Verifactu\Enums\SubmissionStatus;
use Krato\Verifactu\Exceptions\SubmissionException;
use Krato\Verifactu\Hash\ChainManager;
use Krato\Verifactu\Hash\HashGenerator;
use Krato\Verifactu\Jobs\SubmitInvoiceRecord;
use Krato\Verifactu\Testing\FakeTransport;
use Krato\Verifactu\Transport\CertificateAuth;
use Krato\Verifactu\Transport\Endpoints;
use Krato\Verifactu\Transport\SoapClient;
use Krato\Verifactu\Xml\RecordBuilder;
use Krato\Verifactu\Xml\ResponseParser;
use Krato\Verifactu\Xml\SubmissionEnvelope;
use Krato\Verifactu\Xml\XsdValidator;

class VerifactuManager
{
    private ?string $tenantNif = null;

    private ?FakeTransport $fakeTransport = null;

    public function __construct(
        private readonly HashGenerator $hashGenerator,
        private readonly HashChainStore $hashChainStore,
        private readonly SubmissionStore $submissionStore,
        private readonly RecordBuilder $recordBuilder,
        private readonly SubmissionEnvelope $submissionEnvelope,
        private readonly XsdValidator $xsdValidator,
        private readonly ResponseParser $responseParser,
        private readonly Endpoints $endpoints,
        private readonly CertificateAuth $certificateAuth,
        private readonly ?CertificateResolver $certificateResolver,
        private readonly array $config,
    ) {}

    /**
     * Submit an invoice record synchronously to AEAT.
     */
    public function submit(InvoiceRecord $invoice): SubmissionResult
    {
        $chainManager = new ChainManager($this->hashGenerator, $this->hashChainStore);

        // 1. Generate hash chain
        $chainLink = $chainManager->chain($invoice);

        // 2. Build XML record
        $recordXml = $this->recordBuilder->buildAlta($invoice, $chainLink);

        // 3. Validate XML
        $this->xsdValidator->validate($recordXml);

        // 4. Build SOAP envelope
        $issuer = $invoice->getIssuer();
        $soapXml = $this->submissionEnvelope->wrap($recordXml, $issuer->nif, $issuer->name);

        // 5. Record submission BEFORE sending
        $identifier = $invoice->getIdentifier();
        $submissionRecord = new SubmissionRecord(
            nif: $issuer->nif,
            invoiceId: $identifier,
            recordType: RecordType::Alta,
            invoiceType: $invoice->getInvoiceType(),
            xmlRequest: $soapXml,
            hash: $chainLink->hash,
            status: SubmissionStatus::Submitted,
            submittedAt: new \DateTimeImmutable,
            tenantId: $this->tenantNif,
        );
        $this->submissionStore->recordSubmission($submissionRecord);

        // 6. Send to AEAT (or fake)
        try {
            if ($this->fakeTransport !== null) {
                $responseXml = $this->fakeTransport->send($soapXml);
            } else {
                $credentials = $this->resolveCertificate($issuer->nif);
                $soapClient = new SoapClient($this->endpoints, $this->certificateAuth);
                $responseXml = $soapClient->send($soapXml, $credentials);
            }
        } catch (\Throwable $e) {
            $failResult = new SubmissionResult(
                status: SubmissionStatus::Failed,
                errors: [new \Krato\Verifactu\DTOs\SubmissionError('TRANSPORT_ERROR', $e->getMessage())],
            );
            $this->submissionStore->recordResponse($issuer->nif, $identifier, RecordType::Alta->value, $failResult);

            throw new SubmissionException('Failed to submit to AEAT: '.$e->getMessage(), $failResult, $e);
        }

        // 7. Parse response
        $result = $this->responseParser->parse($responseXml);

        // 8. Record response
        $this->submissionStore->recordResponse($issuer->nif, $identifier, RecordType::Alta->value, $result);

        return $result;
    }

    /**
     * Dispatch an invoice record for async processing via queue.
     */
    public function dispatch(InvoiceRecord $invoice): void
    {
        $job = new SubmitInvoiceRecord($invoice, $this->tenantNif);

        $queueConfig = $this->config['queue'] ?? [];
        $connection = $queueConfig['connection'] ?? 'default';
        $queue = $queueConfig['queue'] ?? 'verifactu';

        if ($connection !== 'default') {
            $job->onConnection($connection);
        }

        $job->onQueue($queue);

        dispatch($job);
    }

    /**
     * Set the tenant NIF for multi-tenant usage.
     */
    public function forTenant(string $nif): static
    {
        $clone = clone $this;
        $clone->tenantNif = $nif;

        return $clone;
    }

    /**
     * Enable fake transport for testing.
     */
    public function fake(?FakeTransport $transport = null): FakeTransport
    {
        $this->fakeTransport = $transport ?? new FakeTransport;

        return $this->fakeTransport;
    }

    /**
     * Assert that an invoice was submitted (only works in fake mode).
     */
    public function assertSubmitted(string $invoiceNumber): void
    {
        if ($this->fakeTransport === null) {
            throw new \LogicException('Cannot assert submissions without calling fake() first.');
        }

        $this->fakeTransport->assertSubmitted($invoiceNumber);
    }

    /**
     * Assert the number of submissions (only works in fake mode).
     */
    public function assertSubmittedCount(int $count): void
    {
        if ($this->fakeTransport === null) {
            throw new \LogicException('Cannot assert submissions without calling fake() first.');
        }

        $this->fakeTransport->assertSubmittedCount($count);
    }

    private function resolveCertificate(string $nif): CertificateCredentials
    {
        if ($this->certificateResolver !== null) {
            return $this->certificateResolver->resolve($nif);
        }

        $certPath = $this->config['certificate']['path'] ?? null;
        $certPassword = $this->config['certificate']['password'] ?? null;

        if ($certPath === null || $certPassword === null) {
            throw new SubmissionException('No certificate configured. Set VERIFACTU_CERT_PATH and VERIFACTU_CERT_PASSWORD or implement CertificateResolver.');
        }

        return new CertificateCredentials($certPath, $certPassword);
    }
}
