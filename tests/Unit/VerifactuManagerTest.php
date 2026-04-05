<?php

use Krato\Verifactu\Enums\SubmissionStatus;
use Krato\Verifactu\Exceptions\DuplicateSubmissionException;
use Krato\Verifactu\Exceptions\SubmissionException;
use Krato\Verifactu\Exceptions\ValidationException;
use Krato\Verifactu\Hash\HashGenerator;
use Krato\Verifactu\Testing\FakeHashChainStore;
use Krato\Verifactu\Testing\FakeSubmissionStore;
use Krato\Verifactu\Testing\InvoiceRecordFactory;
use Krato\Verifactu\Transport\CertificateAuth;
use Krato\Verifactu\Transport\Endpoints;
use Krato\Verifactu\VerifactuManager;
use Krato\Verifactu\Xml\RecordBuilder;
use Krato\Verifactu\Xml\ResponseParser;
use Krato\Verifactu\Xml\SubmissionEnvelope;
use Krato\Verifactu\Xml\XsdValidator;

function pastInvoice(): InvoiceRecordFactory
{
    return InvoiceRecordFactory::make()
        ->withIssueDate(new \DateTimeImmutable('2025-01-15'));
}

function createManager(
    ?FakeHashChainStore $hashStore = null,
    ?FakeSubmissionStore $submissionStore = null,
): VerifactuManager {
    $hashStore ??= new FakeHashChainStore;
    $submissionStore ??= new FakeSubmissionStore;

    return new VerifactuManager(
        hashGenerator: new HashGenerator,
        hashChainStore: $hashStore,
        submissionStore: $submissionStore,
        recordBuilder: new RecordBuilder([
            'name' => 'Test Software',
            'nif' => 'B00000000',
            'version' => '1.0.0',
            'id' => 'TEST-001',
        ]),
        submissionEnvelope: new SubmissionEnvelope,
        xsdValidator: new XsdValidator,
        responseParser: new ResponseParser,
        endpoints: new Endpoints('testing'),
        certificateAuth: new CertificateAuth,
        certificateResolver: null,
        config: [
            'environment' => 'testing',
            'sif' => [
                'name' => 'Test Software',
                'nif' => 'B00000000',
                'version' => '1.0.0',
            ],
        ],
    );
}

// --- Validation failures ---

it('rejects submission with invalid issuer NIF', function () {
    $manager = createManager();
    $manager->fake();

    $invoice = pastInvoice()->withIssuer('BADNIF', 'Test');

    $manager->submit($invoice);
})->throws(ValidationException::class, 'Issuer NIF format is invalid');

it('rejects submission with empty tax breakdowns', function () {
    $manager = createManager();
    $manager->fake();

    $invoice = pastInvoice()->withTaxBreakdowns([]);

    $manager->submit($invoice);
})->throws(ValidationException::class, 'Tax breakdowns must not be empty');

it('rejects submission with empty invoice number', function () {
    $manager = createManager();
    $manager->fake();

    $invoice = pastInvoice()->withIdentifier('FA', '', new \DateTimeImmutable('2025-01-15'));

    $manager->submit($invoice);
})->throws(ValidationException::class, 'Invoice number must not be empty');

// --- Accepted response => hash chain appended ---

it('appends hash on accepted response', function () {
    $hashStore = new FakeHashChainStore;
    $submissionStore = new FakeSubmissionStore;
    $manager = createManager($hashStore, $submissionStore);
    $manager->fake();

    $invoice = pastInvoice()
        ->withIdentifier('FA', '001', new \DateTimeImmutable('2025-01-15'))
        ->withIssuer('B12345678', 'Test S.L.');

    $result = $manager->submit($invoice);

    expect($result->isAccepted())->toBeTrue()
        ->and($result->csv)->toBe('FAKE-CSV-001');

    // Hash chain should have the entry (appended after acceptance)
    $chain = $hashStore->getChain('B12345678', 'FA');
    expect($chain)->toHaveCount(1)
        ->and($chain[0]->hash)->toHaveLength(64);
});

it('records submission with accepted status after successful send', function () {
    $submissionStore = new FakeSubmissionStore;
    $manager = createManager(submissionStore: $submissionStore);
    $manager->fake();

    $invoice = pastInvoice();
    $result = $manager->submit($invoice);

    $submissions = $submissionStore->all();
    expect($submissions)->toHaveCount(1)
        ->and($submissions[0]->status)->toBe(SubmissionStatus::Accepted);
});

// --- Rejected response => hash NOT appended, result returned ---

it('does not append hash on rejected response', function () {
    $hashStore = new FakeHashChainStore;
    $submissionStore = new FakeSubmissionStore;
    $manager = createManager($hashStore, $submissionStore);

    $fake = $manager->fake();
    $fake->respondWith(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>Incorrecto</EstadoEnvio>
            <RespuestaLinea>
                <EstadoRegistro>Incorrecto</EstadoRegistro>
                <CodigoErrorRegistro>4100</CodigoErrorRegistro>
                <DescripcionErrorRegistro>NIF no válido</DescripcionErrorRegistro>
            </RespuestaLinea>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML);

    $invoice = pastInvoice()
        ->withIdentifier('FA', '001', new \DateTimeImmutable('2025-01-15'))
        ->withIssuer('B12345678', 'Test S.L.');
    $result = $manager->submit($invoice);

    expect($result->isRejected())->toBeTrue()
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->code)->toBe('4100');

    // Hash chain must be empty — rejected submissions are NOT persisted
    $chain = $hashStore->getChain('B12345678', 'FA');
    expect($chain)->toBeEmpty();

    $submissions = $submissionStore->all();
    expect($submissions[0]->status)->toBe(SubmissionStatus::Rejected);
});

// --- Invalid response => exception thrown, hash NOT appended ---

it('throws SubmissionException on malformed response and does not append hash', function () {
    $hashStore = new FakeHashChainStore;
    $submissionStore = new FakeSubmissionStore;
    $manager = createManager($hashStore, $submissionStore);

    $fake = $manager->fake();
    $fake->respondWith('not-xml-response');

    $invoice = pastInvoice()
        ->withIdentifier('FA', '001', new \DateTimeImmutable('2025-01-15'))
        ->withIssuer('B12345678', 'Test S.L.');

    try {
        $manager->submit($invoice);
        test()->fail('Expected SubmissionException');
    } catch (SubmissionException $e) {
        // The exception should carry the parsed result
        expect($e->result)->not->toBeNull()
            ->and($e->result->status)->toBe(SubmissionStatus::TransportError)
            ->and($e->result->errors[0]->code)->toBe('PARSE_ERROR');
    }

    // Hash chain must be empty
    $chain = $hashStore->getChain('B12345678', 'FA');
    expect($chain)->toBeEmpty();

    // Submission status should be recorded as TransportError
    $submissions = $submissionStore->all();
    expect($submissions[0]->status)->toBe(SubmissionStatus::TransportError);
});

it('throws SubmissionException on SOAP fault and does not append hash', function () {
    $hashStore = new FakeHashChainStore;
    $submissionStore = new FakeSubmissionStore;
    $manager = createManager($hashStore, $submissionStore);

    $fake = $manager->fake();
    $fake->respondWith(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <soapenv:Fault>
            <faultcode>Server</faultcode>
            <faultstring>Internal Server Error</faultstring>
        </soapenv:Fault>
    </soapenv:Body>
</soapenv:Envelope>
XML);

    $invoice = pastInvoice()
        ->withIdentifier('FA', '001', new \DateTimeImmutable('2025-01-15'))
        ->withIssuer('B12345678', 'Test S.L.');

    try {
        $manager->submit($invoice);
        test()->fail('Expected SubmissionException');
    } catch (SubmissionException $e) {
        expect($e->result)->not->toBeNull()
            ->and($e->result->status)->toBe(SubmissionStatus::TransportError)
            ->and($e->result->errors[0]->code)->toBe('SOAP_FAULT');
    }

    $chain = $hashStore->getChain('B12345678', 'FA');
    expect($chain)->toBeEmpty();
});

// --- Duplicate submission prevention ---

it('prevents duplicate submission of already accepted invoice', function () {
    $submissionStore = new FakeSubmissionStore;
    $manager = createManager(submissionStore: $submissionStore);
    $manager->fake();

    $invoice = pastInvoice()
        ->withIdentifier('FA', '001', new \DateTimeImmutable('2025-01-15'))
        ->withIssuer('B12345678', 'Test S.L.');

    // First submission succeeds
    $result = $manager->submit($invoice);
    expect($result->isAccepted())->toBeTrue();

    // Second submission should throw
    $manager->submit($invoice);
})->throws(DuplicateSubmissionException::class, 'already been accepted');

it('allows resubmission after invalid response', function () {
    $submissionStore = new FakeSubmissionStore;
    $hashStore = new FakeHashChainStore;
    $manager = createManager($hashStore, $submissionStore);

    $fake = $manager->fake();

    $invoice = pastInvoice()
        ->withIdentifier('FA', '002', new \DateTimeImmutable('2025-01-15'))
        ->withIssuer('B12345678', 'Test S.L.');

    // First attempt: malformed response → SubmissionException
    $fake->respondWith('not-xml');
    try {
        $manager->submit($invoice);
        test()->fail('Expected SubmissionException');
    } catch (SubmissionException $e) {
        expect($e->result->isTransportError())->toBeTrue();
    }

    // Hash should NOT be in chain after transport error
    expect($hashStore->getChain('B12345678', 'FA'))->toBeEmpty();

    // Second attempt: success
    $fake->respondWith(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>Correcto</EstadoEnvio>
            <CSV>CSV-RETRY-001</CSV>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML);

    $result2 = $manager->submit($invoice);
    expect($result2->isAccepted())->toBeTrue()
        ->and($result2->csv)->toBe('CSV-RETRY-001');

    // Now the hash should be in the chain
    expect($hashStore->getChain('B12345678', 'FA'))->toHaveCount(1);
});

it('allows resubmission after rejection', function () {
    $submissionStore = new FakeSubmissionStore;
    $hashStore = new FakeHashChainStore;
    $manager = createManager($hashStore, $submissionStore);

    $fake = $manager->fake();

    $invoice = pastInvoice()
        ->withIdentifier('FA', '003', new \DateTimeImmutable('2025-01-15'))
        ->withIssuer('B12345678', 'Test S.L.');

    // First attempt: rejected
    $fake->respondWith(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>Incorrecto</EstadoEnvio>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML);

    $result1 = $manager->submit($invoice);
    expect($result1->isRejected())->toBeTrue();

    // Hash should NOT be in chain after rejection
    expect($hashStore->getChain('B12345678', 'FA'))->toBeEmpty();

    // Second attempt: should be allowed (rejections are not duplicates)
    $fake->respondWith(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>Correcto</EstadoEnvio>
            <CSV>CSV-FIXED-001</CSV>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML);

    $result2 = $manager->submit($invoice);
    expect($result2->isAccepted())->toBeTrue();

    // Now the hash should be in the chain
    expect($hashStore->getChain('B12345678', 'FA'))->toHaveCount(1);
});
