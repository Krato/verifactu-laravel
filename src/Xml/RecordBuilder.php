<?php

namespace Krato\Verifactu\Xml;

use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\DTOs\HashChainLink;

class RecordBuilder
{
    private const NAMESPACE_SII = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SusFactura.xsd';

    private const NAMESPACE_SIF = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SistemaFacturacion.xsd';

    public function __construct(
        private readonly array $sifConfig,
    ) {}

    /**
     * Build the XML for a high-record (alta) invoice registration.
     */
    public function buildAlta(InvoiceRecord $invoice, HashChainLink $chainLink): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NAMESPACE_SII, 'sii:RegistroFacturacion');
        $root->setAttribute('xmlns:sif', self::NAMESPACE_SIF);
        $dom->appendChild($root);

        // IDFactura
        $idFactura = $dom->createElement('sii:IDFactura');
        $root->appendChild($idFactura);

        $identifier = $invoice->getIdentifier();
        $issuer = $invoice->getIssuer();

        $idEmisor = $dom->createElement('sii:IDEmisorFactura', $issuer->nif);
        $idFactura->appendChild($idEmisor);

        $numSerie = $dom->createElement('sii:NumSerieFactura', $identifier->fullNumber());
        $idFactura->appendChild($numSerie);

        $fechaExpedicion = $dom->createElement(
            'sii:FechaExpedicionFactura',
            $identifier->issueDate->format('d-m-Y')
        );
        $idFactura->appendChild($fechaExpedicion);

        // RefExterna (optional)

        // TipoFactura
        $tipoFactura = $dom->createElement('sii:TipoFactura', $invoice->getInvoiceType()->value);
        $root->appendChild($tipoFactura);

        // DescripcionOperacion
        $descripcion = $dom->createElement('sii:DescripcionOperacion', $this->sanitizeXml($invoice->getDescription()));
        $root->appendChild($descripcion);

        // Destinatarios
        $recipient = $invoice->getRecipient();
        if ($recipient !== null) {
            $destinatarios = $dom->createElement('sii:Destinatarios');
            $root->appendChild($destinatarios);

            $idDestinatario = $dom->createElement('sii:IDDestinatario');
            $destinatarios->appendChild($idDestinatario);

            $nifDestinatario = $dom->createElement('sii:NIF', $recipient->nif);
            $idDestinatario->appendChild($nifDestinatario);

            $nombreDestinatario = $dom->createElement('sii:NombreRazon', $this->sanitizeXml($recipient->name));
            $idDestinatario->appendChild($nombreDestinatario);
        }

        // Desglose
        $this->appendTaxBreakdown($dom, $root, $invoice);

        // ImporteTotal
        $importeTotal = $dom->createElement('sii:ImporteTotal', $this->formatAmount($invoice->getTotalAmount()));
        $root->appendChild($importeTotal);

        // Encadenamiento
        $encadenamiento = $dom->createElement('sii:Encadenamiento');
        $root->appendChild($encadenamiento);

        if ($chainLink->previousHash !== null) {
            $hashAnterior = $dom->createElement('sii:RegistroAnterior');
            $encadenamiento->appendChild($hashAnterior);

            $huella = $dom->createElement('sii:Huella', $chainLink->previousHash);
            $hashAnterior->appendChild($huella);
        } else {
            $primeRegistro = $dom->createElement('sii:PrimerRegistro', 'S');
            $encadenamiento->appendChild($primeRegistro);
        }

        // SistemaInformatico
        $this->appendSifInfo($dom, $root);

        // FechaHoraHuella
        $hashTimestamp = $chainLink->metadata['hash_timestamp'] ?? (new \DateTimeImmutable)->format('Y-m-d\TH:i:sP');
        $fechaHuella = $dom->createElement('sii:FechaHoraHuella', $hashTimestamp);
        $root->appendChild($fechaHuella);

        // Huella
        $huellaElem = $dom->createElement('sii:Huella', $chainLink->hash);
        $root->appendChild($huellaElem);

        return $dom->saveXML($root);
    }

    private function appendTaxBreakdown(\DOMDocument $dom, \DOMElement $root, InvoiceRecord $invoice): void
    {
        $desglose = $dom->createElement('sii:Desglose');
        $root->appendChild($desglose);

        foreach ($invoice->getTaxBreakdowns() as $breakdown) {
            $detalle = $dom->createElement('sii:DetalleDesglose');
            $desglose->appendChild($detalle);

            $impuesto = $dom->createElement('sii:Impuesto', $breakdown->taxType->value);
            $detalle->appendChild($impuesto);

            $claveRegimen = $dom->createElement('sii:ClaveRegimen', $breakdown->taxRegime->value);
            $detalle->appendChild($claveRegimen);

            $baseImponible = $dom->createElement('sii:BaseImponible', $this->formatAmount($breakdown->taxBase));
            $detalle->appendChild($baseImponible);

            $tipoImpositivo = $dom->createElement('sii:TipoImpositivo', $this->formatAmount($breakdown->taxRate));
            $detalle->appendChild($tipoImpositivo);

            $cuotaRepercutida = $dom->createElement('sii:CuotaRepercutida', $this->formatAmount($breakdown->taxAmount));
            $detalle->appendChild($cuotaRepercutida);

            if ($breakdown->surchargeRate !== null) {
                $tipoRecargo = $dom->createElement('sii:TipoRecargoEquivalencia', $this->formatAmount($breakdown->surchargeRate));
                $detalle->appendChild($tipoRecargo);

                $cuotaRecargo = $dom->createElement('sii:CuotaRecargoEquivalencia', $this->formatAmount($breakdown->surchargeAmount ?? 0));
                $detalle->appendChild($cuotaRecargo);
            }
        }
    }

    private function appendSifInfo(\DOMDocument $dom, \DOMElement $root): void
    {
        $sif = $dom->createElement('sii:SistemaInformatico');
        $root->appendChild($sif);

        $nombreSif = $dom->createElement('sif:NombreSistemaInformatico', $this->sanitizeXml($this->sifConfig['name'] ?? ''));
        $sif->appendChild($nombreSif);

        if (! empty($this->sifConfig['nif'])) {
            $nifSif = $dom->createElement('sif:NIF', $this->sifConfig['nif']);
            $sif->appendChild($nifSif);
        }

        $versionSif = $dom->createElement('sif:Version', $this->sifConfig['version'] ?? '1.0.0');
        $sif->appendChild($versionSif);

        if (! empty($this->sifConfig['id'])) {
            $idSif = $dom->createElement('sif:IdSistemaInformatico', $this->sifConfig['id']);
            $sif->appendChild($idSif);
        }
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function sanitizeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }
}
