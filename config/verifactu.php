<?php

use Krato\Verifactu\Support\DatabaseHashChainStore;
use Krato\Verifactu\Support\DatabaseSubmissionStore;

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | 'production' or 'testing' (sandbox AEAT).
    |
    */
    'environment' => env('VERIFACTU_ENV', 'testing'),

    /*
    |--------------------------------------------------------------------------
    | Software Information (SIF)
    |--------------------------------------------------------------------------
    |
    | Identifies your software system to AEAT.
    |
    */
    'sif' => [
        'name'    => env('VERIFACTU_SIF_NAME', 'Mi Software'),
        'nif'     => env('VERIFACTU_SIF_NIF'),
        'version' => env('VERIFACTU_SIF_VERSION', '1.0.0'),
        'id'      => env('VERIFACTU_SIF_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate
    |--------------------------------------------------------------------------
    |
    | Default certificate for AEAT authentication.
    | For multi-tenant, implement CertificateResolver.
    |
    */
    'certificate' => [
        'path'     => env('VERIFACTU_CERT_PATH'),
        'password' => env('VERIFACTU_CERT_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled'    => env('VERIFACTU_QUEUE_ENABLED', true),
        'connection' => env('VERIFACTU_QUEUE_CONNECTION', 'default'),
        'queue'      => env('VERIFACTU_QUEUE_NAME', 'verifactu'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => env('VERIFACTU_RETRY_MAX', 3),
        'backoff'      => [60, 300, 900],
    ],

    /*
    |--------------------------------------------------------------------------
    | Store Bindings
    |--------------------------------------------------------------------------
    |
    | Swap these for custom implementations if needed.
    |
    */
    'bindings' => [
        'hash_chain_store'  => DatabaseHashChainStore::class,
        'submission_store'  => DatabaseSubmissionStore::class,
    ],
];
