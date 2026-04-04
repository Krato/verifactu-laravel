# Changelog

All notable changes to `verifactu-laravel` will be documented in this file.

## v0.1.0 - Unreleased

### Added
- Core invoice submission to AEAT (sync and async via queues)
- SHA-256 hash chaining per AEAT Verifactu specification
- XML record builder for alta records (F1, F2)
- SOAP envelope wrapper and response parser
- Certificate-based authentication (PKCS#12)
- Multi-tenant support via `Verifactu::forTenant()`
- Database and file-based hash chain stores
- Database submission store with full request/response logging
- Testing helpers: `FakeTransport`, `InvoiceRecordFactory`, `FakeHashChainStore`
- `verifactu:install` Artisan command
- Architecture tests ensuring contracts are interfaces, DTOs are readonly
- 43 tests covering hash generation, XML building, response parsing, and more
