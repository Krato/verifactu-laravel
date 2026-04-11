<?php

namespace Krato\Verifactu\Hash;

use Krato\Verifactu\Contracts\HashChainStore;
use Krato\Verifactu\Contracts\InvoiceRecord;
use Krato\Verifactu\DTOs\HashChainLink;

class ChainManager
{
    public function __construct(
        private readonly HashGenerator $hashGenerator,
        private readonly HashChainStore $store,
    ) {}

    /**
     * Generate the next hash in the chain for a given invoice.
     * Does NOT persist the link — call append() after AEAT accepts.
     */
    public function chain(InvoiceRecord $invoice): HashChainLink
    {
        $identifier = $invoice->getIdentifier();
        $issuer = $invoice->getIssuer();
        $series = $identifier->series;

        $previousHash = $this->store->getLastHash($issuer->nif, $series);
        $hashTimestamp = new \DateTimeImmutable;

        $hash = $this->hashGenerator->generate($invoice, $previousHash, $hashTimestamp);

        return new HashChainLink(
            invoiceNumber: $identifier->fullNumber(),
            invoiceDate: $identifier->issueDate,
            hash: $hash,
            previousHash: $previousHash,
            metadata: [
                'hash_timestamp' => $hashTimestamp->format('Y-m-d\TH:i:sP'),
            ],
        );
    }

    /**
     * Persist a hash link to the chain store. Call only after AEAT acceptance.
     */
    public function append(string $nif, string $series, HashChainLink $link): void
    {
        $this->store->append($nif, $series, $link);
    }

    /**
     * Generate hash without persisting (useful for preview/validation).
     */
    public function preview(InvoiceRecord $invoice): string
    {
        $issuer = $invoice->getIssuer();
        $previousHash = $this->store->getLastHash($issuer->nif, $invoice->getIdentifier()->series);

        return $this->hashGenerator->generate($invoice, $previousHash);
    }
}
