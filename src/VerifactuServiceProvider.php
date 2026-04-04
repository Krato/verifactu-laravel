<?php

namespace Krato\Verifactu;

use Krato\Verifactu\Commands\InstallCommand;
use Krato\Verifactu\Contracts\CertificateResolver;
use Krato\Verifactu\Contracts\HashChainStore;
use Krato\Verifactu\Contracts\SubmissionStore;
use Krato\Verifactu\Hash\HashGenerator;
use Krato\Verifactu\Transport\CertificateAuth;
use Krato\Verifactu\Transport\Endpoints;
use Krato\Verifactu\Xml\RecordBuilder;
use Krato\Verifactu\Xml\ResponseParser;
use Krato\Verifactu\Xml\SubmissionEnvelope;
use Krato\Verifactu\Xml\XsdValidator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VerifactuServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('verifactu')
            ->hasConfigFile()
            ->hasMigrations([
                'create_verifactu_hash_chain_table',
                'create_verifactu_submissions_table',
            ])
            ->hasCommand(InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        // Bind store implementations from config
        $this->app->bind(HashChainStore::class, function ($app) {
            $class = config('verifactu.bindings.hash_chain_store');

            return $app->make($class);
        });

        $this->app->bind(SubmissionStore::class, function ($app) {
            $class = config('verifactu.bindings.submission_store');

            return $app->make($class);
        });

        // Bind CertificateResolver only if user has bound it
        if (! $this->app->bound(CertificateResolver::class)) {
            $this->app->bind(CertificateResolver::class, fn () => null);
        }

        // Bind the manager as singleton
        $this->app->singleton(VerifactuManager::class, function ($app) {
            $config = config('verifactu');

            return new VerifactuManager(
                hashGenerator: $app->make(HashGenerator::class),
                hashChainStore: $app->make(HashChainStore::class),
                submissionStore: $app->make(SubmissionStore::class),
                recordBuilder: new RecordBuilder($config['sif'] ?? []),
                submissionEnvelope: new SubmissionEnvelope,
                xsdValidator: new XsdValidator,
                responseParser: new ResponseParser,
                endpoints: new Endpoints($config['environment'] ?? 'testing'),
                certificateAuth: new CertificateAuth,
                certificateResolver: $app->make(CertificateResolver::class),
                config: $config,
            );
        });
    }
}
