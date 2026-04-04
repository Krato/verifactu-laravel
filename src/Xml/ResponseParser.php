<?php

namespace Krato\Verifactu\Xml;

use Krato\Verifactu\DTOs\SubmissionError;
use Krato\Verifactu\DTOs\SubmissionResult;
use Krato\Verifactu\Enums\SubmissionStatus;

class ResponseParser
{
    /**
     * Parse the AEAT SOAP response XML into a SubmissionResult.
     */
    public function parse(string $xmlResponse): SubmissionResult
    {
        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);

        if (! $dom->loadXML($xmlResponse)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return new SubmissionResult(
                status: SubmissionStatus::Failed,
                xmlResponse: $xmlResponse,
                errors: [new SubmissionError('PARSE_ERROR', 'Could not parse XML response')],
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');

        // Check for SOAP Fault
        $faultNodes = $xpath->query('//env:Fault');
        if ($faultNodes !== false && $faultNodes->length > 0) {
            $faultString = $this->getNodeValue($xpath, '//env:Fault/faultstring') ?? 'Unknown SOAP fault';

            return new SubmissionResult(
                status: SubmissionStatus::Failed,
                xmlResponse: $xmlResponse,
                errors: [new SubmissionError('SOAP_FAULT', $faultString)],
            );
        }

        // Parse the response body
        $csv = $this->findElementValue($dom, 'CSV');
        $estadoEnvio = $this->findElementValue($dom, 'EstadoEnvio');

        $status = $this->mapStatus($estadoEnvio);
        $errors = $this->extractErrors($dom);

        return new SubmissionResult(
            status: $status,
            csv: $csv,
            xmlResponse: $xmlResponse,
            errors: $errors,
        );
    }

    private function mapStatus(?string $estadoEnvio): SubmissionStatus
    {
        return match ($estadoEnvio) {
            'Correcto' => SubmissionStatus::Accepted,
            'AceptadoConErrores' => SubmissionStatus::AcceptedWithErrors,
            'Incorrecto' => SubmissionStatus::Rejected,
            default => SubmissionStatus::Failed,
        };
    }

    /**
     * @return SubmissionError[]
     */
    private function extractErrors(\DOMDocument $dom): array
    {
        $errors = [];
        $registros = $dom->getElementsByTagName('RespuestaLinea');

        for ($i = 0; $i < $registros->length; $i++) {
            $registro = $registros->item($i);
            if ($registro === null) {
                continue;
            }

            $estado = $this->getChildValue($registro, 'EstadoRegistro');
            if ($estado === 'Incorrecto' || $estado === 'AceptadoConErrores') {
                $code = $this->getChildValue($registro, 'CodigoErrorRegistro') ?? 'UNKNOWN';
                $desc = $this->getChildValue($registro, 'DescripcionErrorRegistro') ?? 'Unknown error';
                $errors[] = new SubmissionError($code, $desc);
            }
        }

        return $errors;
    }

    private function findElementValue(\DOMDocument $dom, string $localName): ?string
    {
        $elements = $dom->getElementsByTagName($localName);
        if ($elements->length === 0) {
            return null;
        }

        return $elements->item(0)?->textContent;
    }

    private function getNodeValue(\DOMXPath $xpath, string $expression): ?string
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0)?->textContent;
    }

    private function getChildValue(\DOMElement $parent, string $localName): ?string
    {
        $elements = $parent->getElementsByTagName($localName);
        if ($elements->length === 0) {
            return null;
        }

        return $elements->item(0)?->textContent;
    }
}
