<?php

use Krato\Verifactu\DTOs\HashChainLink;
use Krato\Verifactu\Testing\InvoiceRecordFactory;
use Krato\Verifactu\Xml\RecordBuilder;

it('builds valid XML for alta record', function () {
    $builder = new RecordBuilder([
        'name' => 'Test Software',
        'nif' => 'B00000000',
        'version' => '1.0.0',
        'id' => 'TEST-001',
    ]);

    $invoice = InvoiceRecordFactory::make();
    $chainLink = new HashChainLink(
        invoiceNumber: 'FA-001',
        invoiceDate: new DateTimeImmutable('2027-01-15'),
        hash: str_repeat('a', 64),
        previousHash: null,
        metadata: ['hash_timestamp' => '2027-01-15T10:00:00+01:00'],
    );

    $xml = $builder->buildAlta($invoice, $chainLink);

    expect($xml)->toBeString()
        ->and($xml)->toContain('B12345678')
        ->and($xml)->toContain('FA-001')
        ->and($xml)->toContain('F1')
        ->and($xml)->toContain('PrimerRegistro')
        ->and($xml)->toContain('1210.00');
});

it('includes recipient when present', function () {
    $builder = new RecordBuilder(['name' => 'Test', 'version' => '1.0.0']);
    $invoice = InvoiceRecordFactory::make();
    $chainLink = new HashChainLink(
        invoiceNumber: 'FA-001',
        invoiceDate: new DateTimeImmutable('2027-01-15'),
        hash: str_repeat('a', 64),
        metadata: ['hash_timestamp' => '2027-01-15T10:00:00+01:00'],
    );

    $xml = $builder->buildAlta($invoice, $chainLink);

    expect($xml)->toContain('A87654321')
        ->and($xml)->toContain('Cliente Test S.A.');
});

it('excludes recipient when null', function () {
    $builder = new RecordBuilder(['name' => 'Test', 'version' => '1.0.0']);
    $invoice = InvoiceRecordFactory::make()->withoutRecipient();
    $chainLink = new HashChainLink(
        invoiceNumber: 'FA-001',
        invoiceDate: new DateTimeImmutable('2027-01-15'),
        hash: str_repeat('a', 64),
        metadata: ['hash_timestamp' => '2027-01-15T10:00:00+01:00'],
    );

    $xml = $builder->buildAlta($invoice, $chainLink);

    expect($xml)->not->toContain('Destinatarios');
});

it('includes previous hash reference when chained', function () {
    $builder = new RecordBuilder(['name' => 'Test', 'version' => '1.0.0']);
    $invoice = InvoiceRecordFactory::make();
    $previousHash = str_repeat('b', 64);
    $chainLink = new HashChainLink(
        invoiceNumber: 'FA-001',
        invoiceDate: new DateTimeImmutable('2027-01-15'),
        hash: str_repeat('a', 64),
        previousHash: $previousHash,
        metadata: ['hash_timestamp' => '2027-01-15T10:00:00+01:00'],
    );

    $xml = $builder->buildAlta($invoice, $chainLink);

    expect($xml)->toContain('RegistroAnterior')
        ->and($xml)->toContain($previousHash)
        ->and($xml)->not->toContain('PrimerRegistro');
});

it('includes tax breakdown details', function () {
    $builder = new RecordBuilder(['name' => 'Test', 'version' => '1.0.0']);
    $invoice = InvoiceRecordFactory::make();
    $chainLink = new HashChainLink(
        invoiceNumber: 'FA-001',
        invoiceDate: new DateTimeImmutable('2027-01-15'),
        hash: str_repeat('a', 64),
        metadata: ['hash_timestamp' => '2027-01-15T10:00:00+01:00'],
    );

    $xml = $builder->buildAlta($invoice, $chainLink);

    expect($xml)->toContain('1000.00')   // BaseImponible
        ->and($xml)->toContain('21.00')  // TipoImpositivo
        ->and($xml)->toContain('210.00'); // CuotaRepercutida
});
