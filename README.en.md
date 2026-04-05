# Verifactu Laravel

> **[Versión en español](README.md)**

Laravel SDK for Spanish Verifactu tax compliance. Minimal. Decoupled. Production-ready.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/krato/verifactu-laravel.svg?style=flat-square)](https://packagist.org/packages/krato/verifactu-laravel)
[![Tests](https://github.com/krato/verifactu-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/krato/verifactu-laravel/actions)
[![License](https://img.shields.io/packagist/l/krato/verifactu-laravel.svg?style=flat-square)](LICENSE.md)

---

DX inspired by the Stripe PHP SDK: minimal configuration, clear contracts, works from the first `composer require`.

```php
// Submit an invoice to AEAT in 3 lines
$invoice = new InvoiceRecord($yourInvoice);
$result = Verifactu::submit($invoice);
// → SubmissionResult { status: Accepted, csv: "CSV-001" }
```

## What it does

- Verifactu invoice registration records (alta F1/F2)
- SHA-256 chained hashing per AEAT specification
- Synchronous and asynchronous (queues) submission to AEAT
- Digital certificate authentication (PKCS#12)
- Full request/response and hash chain persistence
- Multi-tenant support with `forTenant()`
- Complete testing helpers

## What it does NOT do (by design)

- **Does NOT generate invoices** — only Verifactu tax registration records
- **Does NOT have a UI/dashboard** — pure backend
- **Does NOT impose Eloquent models** — only contracts you implement
- **Does NOT store invoice data** — only hashes, submissions and AEAT responses

## Requirements

- PHP 8.1+
- Laravel 10, 11 or 12
- Extensions: `curl`, `dom`, `openssl`
- Digital certificate (.p12/.pfx) for AEAT submissions

## Installation

```bash
composer require krato/verifactu-laravel
```

```bash
php artisan verifactu:install
```

This publishes the configuration, migrations and runs them.

Add to your `.env`:

```env
VERIFACTU_ENV=testing
VERIFACTU_SIF_NAME="Your Software Name"
VERIFACTU_SIF_NIF=B12345678
VERIFACTU_SIF_VERSION=1.0.0
VERIFACTU_CERT_PATH=/path/to/certificate.p12
VERIFACTU_CERT_PASSWORD=your-password
```

## Quick start

### 1. Implement the `InvoiceRecord` contract

The package does not depend on your models. You implement the contract:

```php
use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\DTOs\InvoiceIdentifier;
use Krato\Verifactu\DTOs\Issuer;
use Krato\Verifactu\DTOs\Recipient;
use Krato\Verifactu\DTOs\TaxBreakdown;
use Krato\Verifactu\Enums\InvoiceType;
use Krato\Verifactu\Enums\TaxRegime;
use Krato\Verifactu\Enums\TaxType;

class MyInvoiceRecord implements InvoiceRecord
{
    public function __construct(private Invoice $invoice) {}

    public function getIdentifier(): InvoiceIdentifier
    {
        return new InvoiceIdentifier(
            series: $this->invoice->series,
            number: $this->invoice->number,
            issueDate: $this->invoice->issue_date,
        );
    }

    public function getIssuer(): Issuer
    {
        return new Issuer(
            nif: config('verifactu.sif.nif'),
            name: config('verifactu.sif.name'),
        );
    }

    public function getRecipient(): ?Recipient
    {
        return new Recipient(
            nif: $this->invoice->customer->nif,
            name: $this->invoice->customer->name,
        );
    }

    public function getInvoiceType(): InvoiceType
    {
        return InvoiceType::F1;
    }

    public function getDescription(): string
    {
        return $this->invoice->description;
    }

    public function getTaxBreakdowns(): array
    {
        return [
            new TaxBreakdown(
                taxType: TaxType::IVA,
                taxRegime: TaxRegime::General,
                taxBase: 1000.00,
                taxRate: 21.00,
                taxAmount: 210.00,
            ),
        ];
    }

    public function getTotalAmount(): float
    {
        return $this->invoice->total;
    }

    public function getIssueDate(): \DateTimeInterface
    {
        return $this->invoice->issue_date;
    }
}
```

### 2. Submit to AEAT

```php
use Krato\Verifactu\Facades\Verifactu;

// Synchronous
$result = Verifactu::submit(new MyInvoiceRecord($invoice));

if ($result->isAccepted()) {
    // $result->csv contains the AEAT CSV identifier
}

// Asynchronous (via queue)
Verifactu::dispatch(new MyInvoiceRecord($invoice));
```

## API

### `Verifactu::submit($invoice)`

Submits an invoice registration record to AEAT synchronously. Returns `SubmissionResult`.

```php
$result = Verifactu::submit($invoice);

$result->status;       // SubmissionStatus enum
$result->csv;          // string|null — AEAT CSV identifier
$result->xmlResponse;  // string|null — Full XML response
$result->errors;       // SubmissionError[] — Errors if any
$result->isAccepted(); // bool
$result->isRejected(); // bool
```

### `Verifactu::dispatch($invoice)`

Submits asynchronously via queue. Uses `verifactu.queue` and `verifactu.retry` configuration.

```php
Verifactu::dispatch($invoice);
```

### `Verifactu::forTenant($nif)`

For multi-tenant applications. Returns an instance configured for that NIF (tax ID).

```php
Verifactu::forTenant('B12345678')->submit($invoice);
Verifactu::forTenant('A87654321')->dispatch($invoice);
```

Requires implementing `CertificateResolver` to resolve certificates per NIF:

```php
use Krato\Verifactu\Contracts\CertificateResolver;
use Krato\Verifactu\DTOs\CertificateCredentials;

class MyCertificateResolver implements CertificateResolver
{
    public function resolve(string $nif): CertificateCredentials
    {
        $tenant = Tenant::where('nif', $nif)->firstOrFail();

        return new CertificateCredentials(
            path: $tenant->certificate_path,
            password: $tenant->certificate_password,
        );
    }
}

// In a ServiceProvider:
$this->app->bind(CertificateResolver::class, MyCertificateResolver::class);
```

## Testing

The package includes helpers for testing without sending anything to AEAT:

```php
use Krato\Verifactu\Facades\Verifactu;
use Krato\Verifactu\Testing\InvoiceRecordFactory;

it('submits invoice to AEAT', function () {
    // Enable fake mode
    $fake = Verifactu::fake();

    // Submit using the included factory
    $invoice = InvoiceRecordFactory::make()
        ->withIdentifier('FA', '001')
        ->withIssuer('B12345678', 'My Company S.L.')
        ->withTotalAmount(1210.00);

    Verifactu::submit($invoice);

    // Assertions
    $fake->assertSubmitted('FA-001');
    $fake->assertSubmittedCount(1);
});
```

### `InvoiceRecordFactory`

Fluent factory for creating test invoices:

```php
$invoice = InvoiceRecordFactory::make()
    ->withIdentifier('FA', '001', new DateTime('2027-01-15'))
    ->withIssuer('B12345678', 'My Company S.L.')
    ->withRecipient('A87654321', 'Customer S.A.')
    ->withInvoiceType(InvoiceType::F2)
    ->withDescription('Consulting services')
    ->withTotalAmount(1210.00)
    ->withTaxBreakdowns([
        new TaxBreakdown(TaxType::IVA, TaxRegime::General, 1000.00, 21.00, 210.00),
    ])
    ->withIssueDate(new DateTime('2027-01-15'));
```

### `FakeTransport`

```php
$fake = Verifactu::fake();

// Available assertions
$fake->assertSubmitted('FA-001');
$fake->assertSubmittedCount(2);
$fake->assertNothingSubmitted();
$fake->submissions(); // array of recorded submissions
$fake->reset();

// Custom response
$fake->respondWith($customXmlResponse);
```

### `FakeHashChainStore`

For unit testing the hash chain:

```php
use Krato\Verifactu\Testing\FakeHashChainStore;

$store = new FakeHashChainStore;
$store->getLastHash('B12345678', 'FA'); // null (empty chain)
$store->append('B12345678', 'FA', $link);
$store->getChain('B12345678', 'FA');    // [HashChainLink]
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=verifactu-config
```

### Environment

```php
// config/verifactu.php
'environment' => env('VERIFACTU_ENV', 'testing'), // 'production' or 'testing'
```

- `testing`: AEAT sandbox (`prewww1.aeat.es`)
- `production`: AEAT production (`www1.agenciatributaria.gob.es`)

### Invoicing software (SIF)

```php
'sif' => [
    'name'    => env('VERIFACTU_SIF_NAME', 'Mi Software'),
    'nif'     => env('VERIFACTU_SIF_NIF'),
    'version' => env('VERIFACTU_SIF_VERSION', '1.0.0'),
    'id'      => env('VERIFACTU_SIF_ID'),
],
```

### Certificate

```php
'certificate' => [
    'path'     => env('VERIFACTU_CERT_PATH'),     // Path to .p12/.pfx
    'password' => env('VERIFACTU_CERT_PASSWORD'),
],
```

### Queues

```php
'queue' => [
    'enabled'    => env('VERIFACTU_QUEUE_ENABLED', true),
    'connection' => env('VERIFACTU_QUEUE_CONNECTION', 'default'),
    'queue'      => env('VERIFACTU_QUEUE_NAME', 'verifactu'),
],
```

### Retries

```php
'retry' => [
    'max_attempts' => env('VERIFACTU_RETRY_MAX', 3),
    'backoff'      => [60, 300, 900], // seconds between retries
],
```

### Custom stores

```php
'bindings' => [
    'hash_chain_store'  => DatabaseHashChainStore::class, // or FileHashChainStore::class
    'submission_store'  => DatabaseSubmissionStore::class,
],
```

You can implement your own stores by implementing `HashChainStore` and `SubmissionStore`.

## Invoice types

| Enum | Value | Description |
|------|-------|-------------|
| `InvoiceType::F1` | `F1` | Full invoice (art. 6, 7.2 y 7.3 RD 1619/2012) |
| `InvoiceType::F2` | `F2` | Simplified invoice (art. 6.1.d y 7.1 RD 1619/2012) |

## Tax types

| Enum | Value | Description |
|------|-------|-------------|
| `TaxType::IVA` | `01` | Value Added Tax (VAT) |
| `TaxType::IGIC` | `02` | Canary Islands General Indirect Tax |
| `TaxType::IPSI` | `03` | Production, Services and Import Tax |

## Tax regimes

| Enum | Value | Description |
|------|-------|-------------|
| `TaxRegime::General` | `01` | General regime |
| `TaxRegime::Export` | `02` | Export |
| `TaxRegime::SpecialGoods` | `03` | Used goods |
| `TaxRegime::InvestmentGold` | `04` | Investment gold |
| `TaxRegime::TravelAgencies` | `05` | Travel agencies |
| `TaxRegime::EntityGroups` | `06` | VAT entity groups |
| `TaxRegime::CashBasis` | `07` | Cash basis |
| `TaxRegime::SimplifiedRegime` | `11` | Equivalence surcharge |
| `TaxRegime::EqualizationCharge` | `12` | Simplified regime |

## Architecture

```
src/
├── Contracts/          ← Interfaces you implement
│   ├── InvoiceRecord
│   ├── CertificateResolver
│   ├── HashChainStore
│   └── SubmissionStore
├── DTOs/               ← Immutable value objects
├── Enums/              ← InvoiceType, TaxType, TaxRegime, etc.
├── Hash/               ← SHA-256 hash chaining (AEAT spec)
├── Xml/                ← XML builder, SOAP envelope, response parser
├── Transport/          ← cURL client, endpoints, certificates
├── Support/            ← Default implementations (DB, file-based)
├── Testing/            ← FakeTransport, InvoiceRecordFactory
├── Jobs/               ← SubmitInvoiceRecord (async)
├── Commands/           ← verifactu:install
├── Facades/            ← Verifactu
├── VerifactuManager    ← Main orchestrator
└── VerifactuServiceProvider
```

### Submission flow

```
Your app → Verifactu::submit($invoice)
  1. ChainManager generates chained SHA-256 hash
  2. RecordBuilder generates the record XML
  3. XsdValidator validates the XML
  4. SubmissionEnvelope wraps in SOAP
  5. SubmissionStore saves the request (BEFORE sending)
  6. SoapClient sends to AEAT (HTTPS + certificate)
  7. ResponseParser parses the response
  8. SubmissionStore saves the response
  9. HashChainStore persists the hash (only if accepted)
  → SubmissionResult returned
```

## What v0.1 does today

v0.1 is the **submission core** — everything you need to register invoices with AEAT:

- **Invoice registration** — Alta records for F1 (full) and F2 (simplified) invoices
- **SHA-256 hash chaining** — Per AEAT specification, each record is chained to the previous one
- **Pre-submit validation** — Invoice records are validated before any network call (NIF format, recipient data, tax breakdowns, total consistency, dates)
- **Idempotency guard** — Prevents accidental duplicate submissions of already-accepted invoices
- **Synchronous and async submission** — `submit()` for immediate, `dispatch()` for queue-based
- **Transport error handling** — Only transport/transient failures are retried; validation errors and AEAT rejections fail immediately
- **Digital certificate auth** — PKCS#12 (.p12/.pfx) certificate support
- **Full persistence** — Requests, responses, hash chain, and submission status are all stored
- **Multi-tenant** — `forTenant($nif)` for SaaS applications
- **Testing helpers** — `FakeTransport`, `FakeSubmissionStore`, `InvoiceRecordFactory`

## What v0.1 does NOT do yet

These features are planned for future versions:

- **Corrective invoices** (R1-R5) — v0.2
- **Cancellations** (anulaciones) — v0.2
- **QR code generation** — v0.2
- **Laravel events** (InvoiceSubmitted, InvoiceAccepted, etc.) — v0.2
- **Query API** against AEAT — v0.3
- **Cross-referencing audit** — v0.3
- **Operational CLI commands** — v0.3
- **Data export** — v0.3

## Troubleshooting

### "Invoice record validation failed"

The package validates all invoice data before sending. Check the exception's `violations` array for specific issues:

```php
try {
    Verifactu::submit($invoice);
} catch (\Krato\Verifactu\Exceptions\ValidationException $e) {
    // $e->violations contains an array of human-readable error messages
    foreach ($e->violations as $violation) {
        logger()->error($violation);
    }
}
```

Common causes:
- **Invalid NIF format** — Must be 9 characters (e.g. `B12345678`)
- **Empty tax breakdowns** — At least one `TaxBreakdown` is required
- **Total mismatch** — `getTotalAmount()` must equal the sum of `taxBase + taxAmount + surchargeAmount` across breakdowns (tolerance: 0.01)
- **Empty recipient fields** — If a recipient is provided, both NIF and name are required
- **Future issue date** — Issue date cannot be in the future
- **Mismatched dates** — `getIssueDate()` and `getIdentifier()->issueDate` must match

### "Duplicate submission prevented"

The package blocks re-sending an invoice that was already accepted by AEAT. This is intentional. If you need to correct an invoice, rectifications will be available in v0.2.

```php
try {
    Verifactu::submit($invoice);
} catch (\Krato\Verifactu\Exceptions\DuplicateSubmissionException $e) {
    // This invoice was already accepted — no action needed
}
```

### Transport errors and retries

When using `dispatch()` (queue-based), only transport failures are retried. AEAT rejections and validation errors fail immediately without retrying:

```php
// Configure retries in config/verifactu.php
'retry' => [
    'max_attempts' => 3,
    'backoff' => [60, 300, 900], // seconds between retries
],
```

### Certificate errors

- Ensure your `.p12`/`.pfx` file path is absolute
- Verify the password is correct
- Check that the `openssl` PHP extension is installed
- For multi-tenant setups, implement `CertificateResolver`

## Roadmap

### v0.1 — Submission core (current)
Invoice registration (F1, F2), hash chaining, AEAT submission, response persistence, async with queues, testing helpers.

### v0.2 — Tax scenarios
Corrective invoices (R1-R5), cancellations, QR codes, Laravel events, automatic retries.

### v0.3 — Queries and auditing
AEAT Query API, cross-referencing audit, operational commands, export.

### v1.0 — Stable
When 10+ companies are using it in production with 4 weeks without critical bugs.

## Running package tests

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

MIT. See [LICENSE](LICENSE.md).
