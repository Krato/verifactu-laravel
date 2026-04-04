<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('Krato\Verifactu\Contracts')
    ->toBeInterfaces();

arch('dtos are readonly')
    ->expect('Krato\Verifactu\DTOs')
    ->toBeReadonly();

arch('enums are enums')
    ->expect('Krato\Verifactu\Enums')
    ->toBeEnums();
