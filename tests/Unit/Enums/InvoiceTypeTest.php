<?php

use Krato\Verifactu\Enums\InvoiceType;

it('has correct values', function () {
    expect(InvoiceType::F1->value)->toBe('F1')
        ->and(InvoiceType::F2->value)->toBe('F2');
});

it('can be created from value', function () {
    expect(InvoiceType::from('F1'))->toBe(InvoiceType::F1)
        ->and(InvoiceType::from('F2'))->toBe(InvoiceType::F2);
});
