<?php

use Krato\Verifactu\DTOs\InvoiceIdentifier;

it('generates full number with series', function () {
    $id = new InvoiceIdentifier('FA', '001', new DateTimeImmutable('2027-01-15'));

    expect($id->fullNumber())->toBe('FA-001');
});

it('generates full number without series', function () {
    $id = new InvoiceIdentifier('', '001', new DateTimeImmutable('2027-01-15'));

    expect($id->fullNumber())->toBe('001');
});
