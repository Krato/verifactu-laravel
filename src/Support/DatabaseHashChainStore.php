<?php

namespace Krato\Verifactu\Support;

use Illuminate\Support\Facades\DB;
use Krato\Verifactu\Contracts\HashChainStore;
use Krato\Verifactu\DTOs\HashChainLink;

class DatabaseHashChainStore implements HashChainStore
{
    public function getLastHash(string $nif, string $series): ?string
    {
        $record = DB::table('verifactu_hash_chain')
            ->where('nif', $nif)
            ->where('series', $series)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['hash']);

        return $record?->hash;
    }

    public function append(string $nif, string $series, HashChainLink $link): void
    {
        DB::table('verifactu_hash_chain')->insert([
            'nif' => $nif,
            'series' => $series,
            'invoice_number' => $link->invoiceNumber,
            'invoice_date' => $link->invoiceDate->format('Y-m-d'),
            'hash' => $link->hash,
            'previous_hash' => $link->previousHash,
            'metadata' => $link->metadata !== null ? json_encode($link->metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
