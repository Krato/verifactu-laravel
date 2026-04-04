<?php

use Krato\Verifactu\Xml\SubmissionEnvelope;

it('wraps record XML in SOAP envelope', function () {
    $envelope = new SubmissionEnvelope;
    $recordXml = '<sii:RegistroFacturacion xmlns:sii="https://example.com"><sii:Test>value</sii:Test></sii:RegistroFacturacion>';

    $soapXml = $envelope->wrap($recordXml, 'B12345678', 'Test Company');

    expect($soapXml)->toContain('soapenv:Envelope')
        ->and($soapXml)->toContain('soapenv:Header')
        ->and($soapXml)->toContain('soapenv:Body')
        ->and($soapXml)->toContain('RegFactuSistemaFacturacion')
        ->and($soapXml)->toContain('B12345678')
        ->and($soapXml)->toContain('Test Company')
        ->and($soapXml)->toContain('RegistroFacturacion');
});
