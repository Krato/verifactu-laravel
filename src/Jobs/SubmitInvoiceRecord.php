<?php

namespace Krato\Verifactu\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\Exceptions\DuplicateSubmissionException;
use Krato\Verifactu\Exceptions\SubmissionException;
use Krato\Verifactu\Exceptions\ValidationException;
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

        try {
            $target->submit($this->invoice);
        } catch (ValidationException|DuplicateSubmissionException $e) {
            // Non-retryable: fail immediately without exhausting retries
            $this->fail($e);
        } catch (SubmissionException $e) {
            if ($e->result !== null && ! $e->result->isTransportError()) {
                // AEAT functional rejection: do not retry
                $this->fail($e);

                return;
            }

            // Transport error: rethrow so Laravel retry/backoff applies
            throw $e;
        }
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
