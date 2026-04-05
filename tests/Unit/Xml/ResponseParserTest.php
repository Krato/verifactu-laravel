<?php

use Krato\Verifactu\Enums\SubmissionStatus;
use Krato\Verifactu\Xml\ResponseParser;

it('parses accepted response', function () {
    $parser = new ResponseParser;
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>Correcto</EstadoEnvio>
            <CSV>CSV-TEST-001</CSV>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML;

    $result = $parser->parse($xml);

    expect($result->status)->toBe(SubmissionStatus::Accepted)
        ->and($result->csv)->toBe('CSV-TEST-001')
        ->and($result->isAccepted())->toBeTrue()
        ->and($result->errors)->toBeEmpty();
});

it('parses rejected response with errors', function () {
    $parser = new ResponseParser;
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>Incorrecto</EstadoEnvio>
            <RespuestaLinea>
                <EstadoRegistro>Incorrecto</EstadoRegistro>
                <CodigoErrorRegistro>4100</CodigoErrorRegistro>
                <DescripcionErrorRegistro>NIF no válido</DescripcionErrorRegistro>
            </RespuestaLinea>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML;

    $result = $parser->parse($xml);

    expect($result->status)->toBe(SubmissionStatus::Rejected)
        ->and($result->isRejected())->toBeTrue()
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->code)->toBe('4100')
        ->and($result->errors[0]->description)->toBe('NIF no válido');
});

it('parses SOAP fault', function () {
    $parser = new ResponseParser;
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <soapenv:Fault>
            <faultcode>Server</faultcode>
            <faultstring>Internal Server Error</faultstring>
        </soapenv:Fault>
    </soapenv:Body>
</soapenv:Envelope>
XML;

    $result = $parser->parse($xml);

    expect($result->status)->toBe(SubmissionStatus::TransportError)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->code)->toBe('SOAP_FAULT');
});

it('handles invalid XML', function () {
    $parser = new ResponseParser;

    $result = $parser->parse('not xml at all');

    expect($result->status)->toBe(SubmissionStatus::TransportError)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->code)->toBe('PARSE_ERROR');
});

it('parses accepted with errors response', function () {
    $parser = new ResponseParser;
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <RespuestaRegFactuSistemaFacturacion>
            <EstadoEnvio>AceptadoConErrores</EstadoEnvio>
            <CSV>CSV-TEST-002</CSV>
            <RespuestaLinea>
                <EstadoRegistro>AceptadoConErrores</EstadoRegistro>
                <CodigoErrorRegistro>1000</CodigoErrorRegistro>
                <DescripcionErrorRegistro>Warning menor</DescripcionErrorRegistro>
            </RespuestaLinea>
        </RespuestaRegFactuSistemaFacturacion>
    </soapenv:Body>
</soapenv:Envelope>
XML;

    $result = $parser->parse($xml);

    expect($result->status)->toBe(SubmissionStatus::AcceptedWithErrors)
        ->and($result->isAccepted())->toBeTrue()
        ->and($result->csv)->toBe('CSV-TEST-002')
        ->and($result->errors)->toHaveCount(1);
});
