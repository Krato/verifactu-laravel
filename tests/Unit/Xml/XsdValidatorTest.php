<?php

use Krato\Verifactu\Exceptions\XmlValidationException;
use Krato\Verifactu\Xml\XsdValidator;

it('validates well-formed XML', function () {
    $validator = new XsdValidator;

    expect($validator->validate('<root><child>value</child></root>'))->toBeTrue();
});

it('rejects malformed XML', function () {
    $validator = new XsdValidator;

    $validator->validate('<root><unclosed>');
})->throws(XmlValidationException::class);
