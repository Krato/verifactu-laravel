<?php

namespace Krato\Verifactu\Testing;

use Krato\Verifactu\Contracts\HashChainStore;
use Krato\Verifactu\DTOs\HashChainLink;

class FakeHashChainStore implements HashChainStore
{
    /** @var array<string, HashChainLink[]> */
    private array $chains = [];

    public function getLastHash(string $nif, string $series): ?string
    {
        $key = "{$nif}:{$series}";
        $chain = $this->chains[$key] ?? [];

        if (count($chain) === 0) {
            return null;
        }

        return end($chain)->hash;
    }

    public function append(string $nif, string $series, HashChainLink $link): void
    {
        $key = "{$nif}:{$series}";
        $this->chains[$key][] = $link;
    }

    /**
     * @return HashChainLink[]
     */
    public function getChain(string $nif, string $series): array
    {
        return $this->chains["{$nif}:{$series}"] ?? [];
    }

    public function reset(): void
    {
        $this->chains = [];
    }
}
