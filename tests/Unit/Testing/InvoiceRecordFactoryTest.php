<?php

use Krato\Verifactu\Enums\InvoiceType;
use Krato\Verifactu\Testing\InvoiceRecordFactory;

it('creates a default invoice', function () {
    $invoice = InvoiceRecordFactory::make();

    expect($invoice->getIdentifier()->series)->toBe('FA')
        ->and($invoice->getIdentifier()->number)->toBe('001')
        ->and($invoice->getIssuer()->nif)->toBe('B12345678')
        ->and($invoice->getRecipient()->nif)->toBe('A87654321')
        ->and($invoice->getInvoiceType())->toBe(InvoiceType::F1)
        ->and($invoice->getTotalAmount())->toBe(1210.00)
        ->and($invoice->getTaxBreakdowns())->toHaveCount(1);
});

it('allows customization via fluent methods', function () {
    $invoice = InvoiceRecordFactory::make()
        ->withIdentifier('FB', '999')
        ->withIssuer('A11111111', 'Custom Issuer')
        ->withTotalAmount(500.0)
        ->withInvoiceType(InvoiceType::F2)
        ->withDescription('Custom description');

    expect($invoice->getIdentifier()->series)->toBe('FB')
        ->and($invoice->getIdentifier()->number)->toBe('999')
        ->and($invoice->getIssuer()->nif)->toBe('A11111111')
        ->and($invoice->getTotalAmount())->toBe(500.0)
        ->and($invoice->getInvoiceType())->toBe(InvoiceType::F2)
        ->and($invoice->getDescription())->toBe('Custom description');
});

it('is immutable - original is not modified', function () {
    $original = InvoiceRecordFactory::make();
    $modified = $original->withIdentifier('FB', '999');

    expect($original->getIdentifier()->series)->toBe('FA')
        ->and($modified->getIdentifier()->series)->toBe('FB');
});
