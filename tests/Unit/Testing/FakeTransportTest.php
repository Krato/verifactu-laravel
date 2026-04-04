<?php

use Krato\Verifactu\Testing\FakeTransport;

it('records submissions', function () {
    $transport = new FakeTransport;

    $transport->send('<xml>FA-001</xml>');
    $transport->send('<xml>FA-002</xml>');

    expect($transport->submissions())->toHaveCount(2);
});

it('returns default accepted response', function () {
    $transport = new FakeTransport;

    $response = $transport->send('<xml>test</xml>');

    expect($response)->toContain('Correcto')
        ->and($response)->toContain('CSV');
});

it('returns custom response when configured', function () {
    $transport = new FakeTransport;
    $transport->respondWith('<custom>response</custom>');

    $response = $transport->send('<xml>test</xml>');

    expect($response)->toBe('<custom>response</custom>');
});

it('asserts submitted invoice number', function () {
    $transport = new FakeTransport;
    $transport->send('<xml>NumSerieFactura>FA-001</xml>');

    $transport->assertSubmitted('FA-001');
    $transport->assertSubmittedCount(1);
});

it('asserts nothing submitted', function () {
    $transport = new FakeTransport;

    $transport->assertNothingSubmitted();
});

it('resets submissions', function () {
    $transport = new FakeTransport;
    $transport->send('<xml>test</xml>');
    $transport->reset();

    $transport->assertNothingSubmitted();
});
