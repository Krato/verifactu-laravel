# Verifactu Laravel

> **[English version](README.en.md)**


## Actualmente en Desarrollo - No usar todavía


SDK Laravel para cumplimiento Verifactu. Minimal. Desacoplado. Production-ready.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/krato/verifactu-laravel.svg?style=flat-square)](https://packagist.org/packages/krato/verifactu-laravel)
[![Tests](https://github.com/krato/verifactu-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/krato/verifactu-laravel/actions)
[![License](https://img.shields.io/packagist/l/krato/verifactu-laravel.svg?style=flat-square)](LICENSE.md)

---

DX inspirada en Stripe PHP SDK: configuración mínima, contratos claros, funciona desde el primer `composer require`.

```php
// Enviar una factura a AEAT en 3 líneas
$invoice = new InvoiceRecord($tuFactura);
$result = Verifactu::submit($invoice);
// → SubmissionResult { status: Accepted, csv: "CSV-001" }
```

## Lo que hace

- Registros de facturación Verifactu (alta F1/F2)
- Hash SHA-256 encadenado según especificación AEAT
- Envío síncrono y asíncrono (queues) a AEAT
- Autenticación por certificado digital (PKCS#12)
- Persistencia de requests/responses y cadena de hashes
- Multi-tenant con `forTenant()`
- Testing helpers completos

## Lo que NO hace (deliberadamente)

- **NO genera facturas** — solo registros de facturación Verifactu
- **NO tiene UI/dashboard** — backend puro
- **NO impone modelos Eloquent** — solo contratos que tú implementas
- **NO almacena datos de facturas** — solo hashes, envíos y respuestas AEAT

## Requisitos

- PHP 8.1+
- Laravel 10, 11 o 12
- Extensiones: `curl`, `dom`, `openssl`
- Certificado digital (.p12/.pfx) para envíos a AEAT

## Instalación

```bash
composer require krato/verifactu-laravel
```

```bash
php artisan verifactu:install
```

Esto publica la configuración, las migraciones y ejecuta las migraciones.

Añade a tu `.env`:

```env
VERIFACTU_ENV=testing
VERIFACTU_SIF_NAME="Tu Software"
VERIFACTU_SIF_NIF=B12345678
VERIFACTU_SIF_VERSION=1.0.0
VERIFACTU_CERT_PATH=/path/to/certificate.p12
VERIFACTU_CERT_PASSWORD=tu-password
```

## Uso rápido

### 1. Implementa el contrato `InvoiceRecord`

El paquete no depende de tus modelos. Tú implementas el contrato:

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

### 2. Envía a AEAT

```php
use Krato\Verifactu\Facades\Verifactu;

// Síncrono
$result = Verifactu::submit(new MyInvoiceRecord($invoice));

if ($result->isAccepted()) {
    // $result->csv contiene el CSV de AEAT
}

// Asíncrono (via queue)
Verifactu::dispatch(new MyInvoiceRecord($invoice));
```

## API

### `Verifactu::submit($invoice)`

Envía un registro de facturación a AEAT de forma síncrona. Devuelve `SubmissionResult`.

```php
$result = Verifactu::submit($invoice);

$result->status;       // SubmissionStatus enum
$result->csv;          // string|null — CSV de AEAT
$result->xmlResponse;  // string|null — XML completo de respuesta
$result->errors;       // SubmissionError[] — errores si los hay
$result->isAccepted(); // bool
$result->isRejected(); // bool
```

### `Verifactu::dispatch($invoice)`

Envía de forma asíncrona vía queue. Usa la configuración de `verifactu.queue` y `verifactu.retry`.

```php
Verifactu::dispatch($invoice);
```

### `Verifactu::forTenant($nif)`

Para aplicaciones multi-tenant. Devuelve una instancia configurada para ese NIF.

```php
Verifactu::forTenant('B12345678')->submit($invoice);
Verifactu::forTenant('A87654321')->dispatch($invoice);
```

Requiere implementar `CertificateResolver` para resolver el certificado por NIF:

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

// En un ServiceProvider:
$this->app->bind(CertificateResolver::class, MyCertificateResolver::class);
```

## Testing

El paquete incluye helpers para testing sin enviar nada a AEAT:

```php
use Krato\Verifactu\Facades\Verifactu;
use Krato\Verifactu\Testing\InvoiceRecordFactory;

it('submits invoice to AEAT', function () {
    // Activa el modo fake
    $fake = Verifactu::fake();

    // Envía con la factory incluida
    $invoice = InvoiceRecordFactory::make()
        ->withIdentifier('FA', '001')
        ->withIssuer('B12345678', 'Mi Empresa S.L.')
        ->withTotalAmount(1210.00);

    Verifactu::submit($invoice);

    // Assertions
    $fake->assertSubmitted('FA-001');
    $fake->assertSubmittedCount(1);
});
```

### `InvoiceRecordFactory`

Factory fluida para crear facturas de test:

```php
$invoice = InvoiceRecordFactory::make()
    ->withIdentifier('FA', '001', new DateTime('2027-01-15'))
    ->withIssuer('B12345678', 'Mi Empresa S.L.')
    ->withRecipient('A87654321', 'Cliente S.A.')
    ->withInvoiceType(InvoiceType::F2)
    ->withDescription('Servicios de consultoría')
    ->withTotalAmount(1210.00)
    ->withTaxBreakdowns([
        new TaxBreakdown(TaxType::IVA, TaxRegime::General, 1000.00, 21.00, 210.00),
    ])
    ->withIssueDate(new DateTime('2027-01-15'));
```

### `FakeTransport`

```php
$fake = Verifactu::fake();

// Assertions disponibles
$fake->assertSubmitted('FA-001');
$fake->assertSubmittedCount(2);
$fake->assertNothingSubmitted();
$fake->submissions(); // array de envíos registrados
$fake->reset();

// Respuesta personalizada
$fake->respondWith($customXmlResponse);
```

### `FakeHashChainStore`

Para tests unitarios de la cadena de hashes:

```php
use Krato\Verifactu\Testing\FakeHashChainStore;

$store = new FakeHashChainStore;
$store->getLastHash('B12345678', 'FA'); // null (cadena vacía)
$store->append('B12345678', 'FA', $link);
$store->getChain('B12345678', 'FA');    // [HashChainLink]
```

## Configuración

Publica el fichero de configuración:

```bash
php artisan vendor:publish --tag=verifactu-config
```

### Entorno

```php
// config/verifactu.php
'environment' => env('VERIFACTU_ENV', 'testing'), // 'production' o 'testing'
```

- `testing`: sandbox AEAT (`prewww1.aeat.es`)
- `production`: producción AEAT (`www1.agenciatributaria.gob.es`)

### Software de facturación (SIF)

```php
'sif' => [
    'name'    => env('VERIFACTU_SIF_NAME', 'Mi Software'),
    'nif'     => env('VERIFACTU_SIF_NIF'),
    'version' => env('VERIFACTU_SIF_VERSION', '1.0.0'),
    'id'      => env('VERIFACTU_SIF_ID'),
],
```

### Certificado

```php
'certificate' => [
    'path'     => env('VERIFACTU_CERT_PATH'),     // Ruta al .p12/.pfx
    'password' => env('VERIFACTU_CERT_PASSWORD'),
],
```

### Colas

```php
'queue' => [
    'enabled'    => env('VERIFACTU_QUEUE_ENABLED', true),
    'connection' => env('VERIFACTU_QUEUE_CONNECTION', 'default'),
    'queue'      => env('VERIFACTU_QUEUE_NAME', 'verifactu'),
],
```

### Reintentos

```php
'retry' => [
    'max_attempts' => env('VERIFACTU_RETRY_MAX', 3),
    'backoff'      => [60, 300, 900], // segundos entre reintentos
],
```

### Stores personalizados

```php
'bindings' => [
    'hash_chain_store'  => DatabaseHashChainStore::class, // o FileHashChainStore::class
    'submission_store'  => DatabaseSubmissionStore::class,
],
```

Puedes implementar tus propios stores implementando `HashChainStore` y `SubmissionStore`.

## Tipos de factura

| Enum | Valor | Descripción |
|------|-------|-------------|
| `InvoiceType::F1` | `F1` | Factura completa (art. 6, 7.2 y 7.3 del RD 1619/2012) |
| `InvoiceType::F2` | `F2` | Factura simplificada (art. 6.1.d y 7.1 del RD 1619/2012) |

## Tipos de impuesto

| Enum | Valor | Descripción |
|------|-------|-------------|
| `TaxType::IVA` | `01` | Impuesto sobre el Valor Añadido |
| `TaxType::IGIC` | `02` | Impuesto General Indirecto Canario |
| `TaxType::IPSI` | `03` | Impuesto sobre la Producción, Servicios e Importación |

## Regímenes fiscales

| Enum | Valor | Descripción |
|------|-------|-------------|
| `TaxRegime::General` | `01` | Régimen general |
| `TaxRegime::Export` | `02` | Exportación |
| `TaxRegime::SpecialGoods` | `03` | Bienes usados |
| `TaxRegime::InvestmentGold` | `04` | Oro de inversión |
| `TaxRegime::TravelAgencies` | `05` | Agencias de viaje |
| `TaxRegime::EntityGroups` | `06` | Grupos de entidades en IVA |
| `TaxRegime::CashBasis` | `07` | Criterio de caja |
| `TaxRegime::SimplifiedRegime` | `11` | Recargo de equivalencia |
| `TaxRegime::EqualizationCharge` | `12` | Régimen simplificado |

## Arquitectura

```
src/
├── Contracts/          ← Interfaces que tú implementas
│   ├── InvoiceRecord
│   ├── CertificateResolver
│   ├── HashChainStore
│   └── SubmissionStore
├── DTOs/               ← Value objects inmutables
├── Enums/              ← InvoiceType, TaxType, TaxRegime, etc.
├── Hash/               ← SHA-256 hash chaining (spec AEAT)
├── Xml/                ← XML builder, SOAP envelope, response parser
├── Transport/          ← cURL client, endpoints, certificados
├── Support/            ← Implementaciones por defecto (DB, ficheros)
├── Testing/            ← FakeTransport, InvoiceRecordFactory
├── Jobs/               ← SubmitInvoiceRecord (async)
├── Commands/           ← verifactu:install
├── Facades/            ← Verifactu
├── VerifactuManager    ← Orquestador principal
└── VerifactuServiceProvider
```

### Flujo de envío

```
Tu app → Verifactu::submit($invoice)
  1. ChainManager genera hash SHA-256 encadenado
  2. RecordBuilder genera XML del registro
  3. XsdValidator valida el XML
  4. SubmissionEnvelope envuelve en SOAP
  5. SubmissionStore guarda el request (ANTES de enviar)
  6. SoapClient envía a AEAT (HTTPS + certificado)
  7. ResponseParser parsea la respuesta
  8. SubmissionStore guarda la respuesta
  9. HashChainStore persiste el hash (solo si aceptada)
  → SubmissionResult devuelto
```

## Roadmap

### v0.1 — Core de envío (actual)
Alta de facturas (F1, F2), hash chaining, envío AEAT, persistencia de respuestas, async con queues, testing helpers.

### v0.2 — Casos fiscales
Rectificativas (R1-R5), anulaciones, QR, eventos Laravel, retry automático.

### v0.3 — Consultas y auditoría
Query API contra AEAT, auditoría cruzada, comandos operativos, exportación.

### v1.0 — Estable
Cuando haya 10+ empresas en producción y 4 semanas sin bugs críticos.

## Testing del paquete

```bash
composer test
```

## Changelog

Ver [CHANGELOG](CHANGELOG.md).

## Licencia

MIT. Ver [LICENSE](LICENSE.md).
