<?php

namespace Krato\Verifactu\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\VerifactuManager;

class SubmitInvoiceRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    /** @var int[] */
    public array $backoff;

    public function __construct(
        public readonly InvoiceRecord $invoice,
        public readonly ?string $tenantNif = null,
    ) {
        $this->tries = (int) config('verifactu.retry.max_attempts', 3);
        $this->backoff = config('verifactu.retry.backoff', [60, 300, 900]);
    }

    public function handle(VerifactuManager $manager): void
    {
        $target = $this->tenantNif !== null
            ? $manager->forTenant($this->tenantNif)
            : $manager;

        $target->submit($this->invoice);
    }

    public function tags(): array
    {
        $id = $this->invoice->getIdentifier();

        return [
            'verifactu',
            'nif:'.$this->invoice->getIssuer()->nif,
            'invoice:'.$id->fullNumber(),
        ];
    }
}
