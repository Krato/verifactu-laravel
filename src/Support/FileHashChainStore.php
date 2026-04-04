<?php

namespace Krato\Verifactu\Support;

use Krato\Verifactu\Contracts\HashChainStore;
use Krato\Verifactu\DTOs\HashChainLink;

class FileHashChainStore implements HashChainStore
{
    public function __construct(
        private readonly string $storagePath = 'verifactu/chain',
    ) {}

    public function getLastHash(string $nif, string $series): ?string
    {
        $file = $this->filePath($nif, $series);

        if (! file_exists($file)) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || count($lines) === 0) {
            return null;
        }

        $lastLine = end($lines);
        $data = json_decode($lastLine, true);

        return $data['hash'] ?? null;
    }

    public function append(string $nif, string $series, HashChainLink $link): void
    {
        $file = $this->filePath($nif, $series);
        $dir = dirname($file);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = json_encode([
            'invoice_number' => $link->invoiceNumber,
            'invoice_date' => $link->invoiceDate->format('Y-m-d'),
            'hash' => $link->hash,
            'previous_hash' => $link->previousHash,
            'metadata' => $link->metadata,
            'timestamp' => (new \DateTimeImmutable)->format('c'),
        ]);

        file_put_contents($file, $data.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function filePath(string $nif, string $series): string
    {
        $basePath = storage_path($this->storagePath);
        $safeSeries = preg_replace('/[^a-zA-Z0-9_-]/', '_', $series);

        return "{$basePath}/{$nif}/{$safeSeries}.jsonl";
    }
}
