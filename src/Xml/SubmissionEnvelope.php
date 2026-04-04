<?php

namespace Krato\Verifactu\Xml;

class SubmissionEnvelope
{
    private const NAMESPACE_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';

    private const NAMESPACE_SII = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    /**
     * Wrap an invoice record XML in a SOAP envelope for AEAT submission.
     */
    public function wrap(string $recordXml, string $issuerNif, string $issuerName): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // SOAP Envelope
        $envelope = $dom->createElementNS(self::NAMESPACE_SOAP, 'soapenv:Envelope');
        $envelope->setAttribute('xmlns:sum', self::NAMESPACE_SII);
        $dom->appendChild($envelope);

        // SOAP Header
        $header = $dom->createElement('soapenv:Header');
        $envelope->appendChild($header);

        // SOAP Body
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // RegFactuSistemaFacturacion
        $regFactu = $dom->createElement('sum:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        // Cabecera
        $cabecera = $dom->createElement('sum:Cabecera');
        $regFactu->appendChild($cabecera);

        $obligadoEmision = $dom->createElement('sum:ObligadoEmision');
        $cabecera->appendChild($obligadoEmision);

        $nifElem = $dom->createElement('sum:NIF', $issuerNif);
        $obligadoEmision->appendChild($nifElem);

        $nombreElem = $dom->createElement('sum:NombreRazon', htmlspecialchars($issuerName, ENT_XML1, 'UTF-8'));
        $obligadoEmision->appendChild($nombreElem);

        // RegistroFactura (inject the record XML)
        $registroFactura = $dom->createElement('sum:RegistroFactura');
        $regFactu->appendChild($registroFactura);

        // Import the record XML fragment
        $recordDom = new \DOMDocument;
        $recordDom->loadXML($recordXml);
        $imported = $dom->importNode($recordDom->documentElement, true);
        $registroFactura->appendChild($imported);

        return $dom->saveXML();
    }
}
