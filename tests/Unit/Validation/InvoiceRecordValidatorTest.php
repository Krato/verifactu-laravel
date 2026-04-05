<?php

use Krato\Verifactu\DTOs\TaxBreakdown;
use Krato\Verifactu\Enums\InvoiceType;
use Krato\Verifactu\Enums\TaxRegime;
use Krato\Verifactu\Enums\TaxType;
use Krato\Verifactu\Exceptions\ValidationException;
use Krato\Verifactu\Testing\InvoiceRecordFactory;
use Krato\Verifactu\Validation\InvoiceRecordValidator;

function validInvoice(): InvoiceRecordFactory
{
    return InvoiceRecordFactory::make()
        ->withIssueDate(new DateTimeImmutable('2025-01-15'));
}

it('passes validation for a valid invoice', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice();

    $validator->validate($invoice);

    expect(true)->toBeTrue();
});

it('passes validation for F2 invoices', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withInvoiceType(InvoiceType::F2);

    $validator->validate($invoice);

    expect(true)->toBeTrue();
});

it('fails when invoice number is empty', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withIdentifier('FA', '', new DateTimeImmutable('2025-01-15'));

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Invoice number must not be empty');

it('fails when invoice number exceeds max length', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withIdentifier('FA', str_repeat('X', 61), new DateTimeImmutable('2025-01-15'));

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Invoice number must not exceed 60 characters');

it('fails when series exceeds max length', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withIdentifier(str_repeat('X', 21), '001', new DateTimeImmutable('2025-01-15'));

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Invoice series must not exceed 20 characters');

it('fails when issuer NIF is empty', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withIssuer('', 'Test Company');

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Issuer NIF must not be empty');

it('fails when issuer NIF format is invalid', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withIssuer('INVALID', 'Test Company');

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Issuer NIF format is invalid');

it('fails when issuer name is empty', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withIssuer('B12345678', '');

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Issuer name must not be empty');

it('fails when recipient NIF is empty', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withRecipient('', 'Some Client');

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Recipient NIF must not be empty');

it('fails when recipient name is empty', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withRecipient('A12345678', '');

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Recipient name must not be empty');

it('passes when recipient is null', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withoutRecipient();

    $validator->validate($invoice);

    expect(true)->toBeTrue();
});

it('fails when tax breakdowns are empty', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withTaxBreakdowns([]);

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Tax breakdowns must not be empty');

it('fails when tax base is negative', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()
        ->withTaxBreakdowns([
            new TaxBreakdown(TaxType::IVA, TaxRegime::General, -100.00, 21.00, -21.00),
        ])
        ->withTotalAmount(-121.00);

    $validator->validate($invoice);
})->throws(ValidationException::class, 'tax base must not be negative');

it('fails when tax rate is out of range', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()
        ->withTaxBreakdowns([
            new TaxBreakdown(TaxType::IVA, TaxRegime::General, 100.00, 150.00, 150.00),
        ])
        ->withTotalAmount(250.00);

    $validator->validate($invoice);
})->throws(ValidationException::class, 'tax rate must be between 0 and 100');

it('fails when total amount is negative', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withTotalAmount(-100.00);

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Total amount must not be negative');

it('fails when total does not match tax breakdown sum', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()
        ->withTaxBreakdowns([
            new TaxBreakdown(TaxType::IVA, TaxRegime::General, 1000.00, 21.00, 210.00),
        ])
        ->withTotalAmount(9999.00);

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Total amount (9999.00) does not match the sum of tax breakdowns (1210.00)');

it('passes when total matches tax breakdown sum within tolerance', function () {
    $validator = new InvoiceRecordValidator;
    // 1000 + 210 = 1210, allow up to 0.01 tolerance
    $invoice = validInvoice()
        ->withTaxBreakdowns([
            new TaxBreakdown(TaxType::IVA, TaxRegime::General, 1000.00, 21.00, 210.00),
        ])
        ->withTotalAmount(1210.01);

    $validator->validate($invoice);

    expect(true)->toBeTrue();
});

it('fails when issue date is in the future', function () {
    $validator = new InvoiceRecordValidator;
    // Use tomorrow explicitly to test the boundary
    $tomorrow = new DateTimeImmutable('tomorrow');
    $invoice = InvoiceRecordFactory::make()
        ->withIssueDate($tomorrow);

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Issue date must not be in the future');

it('passes when issue date is today', function () {
    $validator = new InvoiceRecordValidator;
    $today = new DateTimeImmutable('today');
    $invoice = InvoiceRecordFactory::make()
        ->withIssueDate($today);

    $validator->validate($invoice);

    expect(true)->toBeTrue();
});

it('fails when issue date and identifier date do not match', function () {
    $validator = new InvoiceRecordValidator;
    $date1 = new DateTimeImmutable('2025-01-15');
    $date2 = new DateTimeImmutable('2025-01-20');

    // Manually create an invoice where identifier date != issueDate
    $invoice = new class($date1, $date2) implements \Krato\Verifactu\Contracts\InvoiceRecord {
        public function __construct(private \DateTimeInterface $identifierDate, private \DateTimeInterface $issueDate) {}

        public function getIdentifier(): \Krato\Verifactu\DTOs\InvoiceIdentifier
        {
            return new \Krato\Verifactu\DTOs\InvoiceIdentifier('FA', '001', $this->identifierDate);
        }

        public function getIssuer(): \Krato\Verifactu\DTOs\Issuer
        {
            return new \Krato\Verifactu\DTOs\Issuer('B12345678', 'Test S.L.');
        }

        public function getRecipient(): ?\Krato\Verifactu\DTOs\Recipient { return null; }

        public function getInvoiceType(): \Krato\Verifactu\Enums\InvoiceType
        {
            return \Krato\Verifactu\Enums\InvoiceType::F1;
        }

        public function getDescription(): string { return 'Test'; }

        public function getTaxBreakdowns(): array
        {
            return [new \Krato\Verifactu\DTOs\TaxBreakdown(
                \Krato\Verifactu\Enums\TaxType::IVA,
                \Krato\Verifactu\Enums\TaxRegime::General,
                1000.0, 21.0, 210.0,
            )];
        }

        public function getTotalAmount(): float { return 1210.0; }

        public function getIssueDate(): \DateTimeInterface { return $this->issueDate; }
    };

    $validator->validate($invoice);
})->throws(ValidationException::class, 'Issue date and identifier issue date must match');

it('collects multiple violations at once', function () {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()
        ->withIssuer('', '')
        ->withTaxBreakdowns([])
        ->withTotalAmount(-1);

    try {
        $validator->validate($invoice);
        test()->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->violations)->toHaveCount(4)
            ->and($e->violations[0])->toContain('Issuer NIF must not be empty')
            ->and($e->violations[1])->toContain('Issuer name must not be empty')
            ->and($e->violations[2])->toContain('Tax breakdowns must not be empty')
            ->and($e->violations[3])->toContain('Total amount must not be negative');
    }
});

it('accepts valid Spanish NIF formats', function (string $nif) {
    $validator = new InvoiceRecordValidator;
    $invoice = validInvoice()->withIssuer($nif, 'Test');

    $validator->validate($invoice);

    expect(true)->toBeTrue();
})->with([
    'company NIF' => ['B12345678'],
    'company NIF with A' => ['A87654321'],
    'individual DNI format' => ['X1234567L'],
]);
