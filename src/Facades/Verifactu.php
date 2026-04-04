<?php

namespace Krato\Verifactu\Facades;

use Illuminate\Support\Facades\Facade;
use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\DTOs\SubmissionResult;
use Krato\Verifactu\Testing\FakeTransport;
use Krato\Verifactu\VerifactuManager;

/**
 * @method static SubmissionResult submit(InvoiceRecord $invoice)
 * @method static void dispatch(InvoiceRecord $invoice)
 * @method static VerifactuManager forTenant(string $nif)
 * @method static FakeTransport fake(?FakeTransport $transport = null)
 * @method static void assertSubmitted(string $invoiceNumber)
 * @method static void assertSubmittedCount(int $count)
 *
 * @see VerifactuManager
 */
class Verifactu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VerifactuManager::class;
    }
}
