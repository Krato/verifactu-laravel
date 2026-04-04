<?php

namespace Krato\Verifactu\Contracts;

use Krato\Verifactu\DTOs\HashChainLink;

interface HashChainStore
{
    public function getLastHash(string $nif, string $series): ?string;

    public function append(string $nif, string $series, HashChainLink $link): void;
}
