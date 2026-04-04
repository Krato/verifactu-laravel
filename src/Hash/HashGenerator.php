<?php

namespace Krato\Verifactu\Hash;

use Krato\Verifactu\Contracts\InvoiceRecord;

class HashGenerator
{
    /**
     * Generate a SHA-256 hash for a Verifactu record according to AEAT specification.
     *
     * The hash input is a concatenation of specific fields separated by '&':
     * IDEmisorFactura & NumSerieFactura & FechaExpedicionFactura &
     * TipoFactura & CuotaTotal & ImporteTotal & Huella & FechaHoraHuella
     */
    public function generate(
        InvoiceRecord $invoice,
        ?string $previousHash = null,
        ?\DateTimeInterface $hashTimestamp = null,
    ): string {
        $hashTimestamp ??= new \DateTimeImmutable;
        $identifier = $invoice->getIdentifier();
        $issuer = $invoice->getIssuer();

        $totalTax = $this->calculateTotalTax($invoice);

        $fields = [
            'IDEmisorFactura' => $issuer->nif,
            'NumSerieFactura' => $identifier->fullNumber(),
            'FechaExpedicionFactura' => $identifier->issueDate->format('d-m-Y'),
            'TipoFactura' => $invoice->getInvoiceType()->value,
            'CuotaTotal' => $this->formatAmount($totalTax),
            'ImporteTotal' => $this->formatAmount($invoice->getTotalAmount()),
            'Huella' => $previousHash ?? '',
            'FechaHoraHuella' => $hashTimestamp->format('Y-m-d\TH:i:sP'),
        ];

        $input = implode('&', $fields);

        return hash('sha256', $input);
    }

    private function calculateTotalTax(InvoiceRecord $invoice): float
    {
        $total = 0.0;
        foreach ($invoice->getTaxBreakdowns() as $breakdown) {
            $total += $breakdown->taxAmount;
            if ($breakdown->surchargeAmount !== null) {
                $total += $breakdown->surchargeAmount;
            }
        }

        return $total;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
