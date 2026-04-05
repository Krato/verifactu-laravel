<?php

use Krato\Verifactu\Enums\SubmissionStatus;

it('has correct status values', function () {
    expect(SubmissionStatus::Pending->value)->toBe('pending')
        ->and(SubmissionStatus::Sending->value)->toBe('sending')
        ->and(SubmissionStatus::Accepted->value)->toBe('accepted')
        ->and(SubmissionStatus::AcceptedWithErrors->value)->toBe('accepted_with_errors')
        ->and(SubmissionStatus::Rejected->value)->toBe('rejected')
        ->and(SubmissionStatus::TransportError->value)->toBe('transport_error');
});

it('identifies terminal statuses', function () {
    expect(SubmissionStatus::Accepted->isTerminal())->toBeTrue()
        ->and(SubmissionStatus::AcceptedWithErrors->isTerminal())->toBeTrue()
        ->and(SubmissionStatus::Rejected->isTerminal())->toBeTrue()
        ->and(SubmissionStatus::Pending->isTerminal())->toBeFalse()
        ->and(SubmissionStatus::Sending->isTerminal())->toBeFalse()
        ->and(SubmissionStatus::TransportError->isTerminal())->toBeFalse();
});

it('identifies retryable statuses', function () {
    expect(SubmissionStatus::TransportError->isRetryable())->toBeTrue()
        ->and(SubmissionStatus::Accepted->isRetryable())->toBeFalse()
        ->and(SubmissionStatus::Rejected->isRetryable())->toBeFalse()
        ->and(SubmissionStatus::Pending->isRetryable())->toBeFalse()
        ->and(SubmissionStatus::Sending->isRetryable())->toBeFalse();
});

it('preserves backwards-compatible constants', function () {
    expect(SubmissionStatus::Submitted)->toBe(SubmissionStatus::Sending)
        ->and(SubmissionStatus::Failed)->toBe(SubmissionStatus::TransportError);
});
