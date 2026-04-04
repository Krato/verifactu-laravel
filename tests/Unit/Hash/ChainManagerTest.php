<?php

use Krato\Verifactu\Hash\ChainManager;
use Krato\Verifactu\Hash\HashGenerator;
use Krato\Verifactu\Testing\FakeHashChainStore;
use Krato\Verifactu\Testing\InvoiceRecordFactory;

it('creates first link without previous hash', function () {
    $store = new FakeHashChainStore;
    $manager = new ChainManager(new HashGenerator, $store);
    $invoice = InvoiceRecordFactory::make();

    $link = $manager->chain($invoice);

    expect($link->hash)->toBeString()
        ->and(strlen($link->hash))->toBe(64)
        ->and($link->previousHash)->toBeNull()
        ->and($link->invoiceNumber)->toBe('FA-001');
});

it('chains hashes sequentially', function () {
    $store = new FakeHashChainStore;
    $manager = new ChainManager(new HashGenerator, $store);

    $invoice1 = InvoiceRecordFactory::make()->withIdentifier('FA', '001');
    $invoice2 = InvoiceRecordFactory::make()->withIdentifier('FA', '002');

    $link1 = $manager->chain($invoice1);
    $link2 = $manager->chain($invoice2);

    expect($link2->previousHash)->toBe($link1->hash)
        ->and($link2->hash)->not->toBe($link1->hash);
});

it('maintains separate chains per series', function () {
    $store = new FakeHashChainStore;
    $manager = new ChainManager(new HashGenerator, $store);

    $invoiceA = InvoiceRecordFactory::make()->withIdentifier('FA', '001');
    $invoiceB = InvoiceRecordFactory::make()->withIdentifier('FB', '001');

    $linkA = $manager->chain($invoiceA);
    $linkB = $manager->chain($invoiceB);

    expect($linkA->previousHash)->toBeNull()
        ->and($linkB->previousHash)->toBeNull();
});

it('stores links in the store', function () {
    $store = new FakeHashChainStore;
    $manager = new ChainManager(new HashGenerator, $store);
    $invoice = InvoiceRecordFactory::make();

    $manager->chain($invoice);

    $chain = $store->getChain('B12345678', 'FA');
    expect($chain)->toHaveCount(1);
});
