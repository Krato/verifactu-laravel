<?php

use Krato\Verifactu\Transport\Endpoints;

it('returns testing endpoint by default', function () {
    $endpoints = new Endpoints;

    expect($endpoints->isTesting())->toBeTrue()
        ->and($endpoints->isProduction())->toBeFalse()
        ->and($endpoints->submission())->toContain('prewww1.aeat.es');
});

it('returns production endpoint', function () {
    $endpoints = new Endpoints('production');

    expect($endpoints->isProduction())->toBeTrue()
        ->and($endpoints->isTesting())->toBeFalse()
        ->and($endpoints->submission())->toContain('www1.agenciatributaria.gob.es');
});
