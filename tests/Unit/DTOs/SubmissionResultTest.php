<?php

use Krato\Verifactu\DTOs\SubmissionResult;
use Krato\Verifactu\Enums\SubmissionStatus;

it('detects accepted status', function () {
    $result = new SubmissionResult(SubmissionStatus::Accepted, csv: 'CSV-001');

    expect($result->isAccepted())->toBeTrue()
        ->and($result->isRejected())->toBeFalse();
});

it('detects accepted with errors status', function () {
    $result = new SubmissionResult(SubmissionStatus::AcceptedWithErrors);

    expect($result->isAccepted())->toBeTrue();
});

it('detects rejected status', function () {
    $result = new SubmissionResult(SubmissionStatus::Rejected);

    expect($result->isRejected())->toBeTrue()
        ->and($result->isAccepted())->toBeFalse();
});
