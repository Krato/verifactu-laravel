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

        $target->submit($this->invoice);
    }

    /**
     * Determine if the job should retry based on the exception type.
     * Only transport/transient failures are retryable. Validation errors
     * and AEAT functional rejections should not be retried.
     */
    public function shouldRetry(\Throwable $exception): bool
    {
        // Never retry validation failures
        if ($exception instanceof ValidationException) {
            return false;
        }

        // Never retry duplicate submissions
        if ($exception instanceof DuplicateSubmissionException) {
            return false;
        }

        // Only retry transport errors (SubmissionException with transport error status)
        if ($exception instanceof SubmissionException && $exception->result !== null) {
            return $exception->result->isTransportError();
        }

        // Retry unexpected exceptions (likely transient)
        return true;
    }

    /**
     * Handle a job failure. Called when all retries are exhausted or shouldRetry returns false.
     */
    public function failed(\Throwable $exception): void
    {
        // Non-retryable exceptions should fail immediately without exhausting retries
        if (! $this->shouldRetry($exception)) {
            $this->fail($exception);
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
