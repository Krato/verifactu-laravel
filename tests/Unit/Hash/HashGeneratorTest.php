<?php

use Krato\Verifactu\Hash\HashGenerator;
use Krato\Verifactu\Testing\InvoiceRecordFactory;

it('generates a sha256 hash', function () {
    $generator = new HashGenerator;
    $invoice = InvoiceRecordFactory::make();
    $timestamp = new DateTimeImmutable('2027-01-15T10:00:00+01:00');

    $hash = $generator->generate($invoice, null, $timestamp);

    expect($hash)->toBeString()
        ->and(strlen($hash))->toBe(64);
});

it('generates different hashes for different invoices', function () {
    $generator = new HashGenerator;
    $timestamp = new DateTimeImmutable('2027-01-15T10:00:00+01:00');

    $invoice1 = InvoiceRecordFactory::make()->withIdentifier('FA', '001');
    $invoice2 = InvoiceRecordFactory::make()->withIdentifier('FA', '002');

    $hash1 = $generator->generate($invoice1, null, $timestamp);
    $hash2 = $generator->generate($invoice2, null, $timestamp);

    expect($hash1)->not->toBe($hash2);
});

it('generates different hash with previous hash', function () {
    $generator = new HashGenerator;
    $invoice = InvoiceRecordFactory::make();
    $timestamp = new DateTimeImmutable('2027-01-15T10:00:00+01:00');

    $hash1 = $generator->generate($invoice, null, $timestamp);
    $hash2 = $generator->generate($invoice, 'abc123', $timestamp);

    expect($hash1)->not->toBe($hash2);
});

it('generates deterministic hashes', function () {
    $generator = new HashGenerator;
    $invoice = InvoiceRecordFactory::make();
    $timestamp = new DateTimeImmutable('2027-01-15T10:00:00+01:00');

    $hash1 = $generator->generate($invoice, null, $timestamp);
    $hash2 = $generator->generate($invoice, null, $timestamp);

    expect($hash1)->toBe($hash2);
});
