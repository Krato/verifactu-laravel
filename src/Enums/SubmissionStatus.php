<?php

namespace Krato\Verifactu\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Accepted = 'accepted';
    case AcceptedWithErrors = 'accepted_with_errors';
    case Rejected = 'rejected';
    case TransportError = 'transport_error';

    /** @deprecated Use Sending instead. Will be removed in v1.0. */
    const Submitted = self::Sending;

    /** @deprecated Use TransportError instead. Will be removed in v1.0. */
    const Failed = self::TransportError;

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::AcceptedWithErrors, self::Rejected], true);
    }

    public function isRetryable(): bool
    {
        return $this === self::TransportError;
    }
}
