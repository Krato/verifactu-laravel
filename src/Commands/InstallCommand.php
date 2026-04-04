<?php

namespace Krato\Verifactu\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'verifactu:install';

    protected $description = 'Install the Verifactu package';

    public function handle(): int
    {
        $this->info('Installing Verifactu...');

        // Publish config
        $this->callSilent('vendor:publish', [
            '--tag' => 'verifactu-config',
        ]);
        $this->info('✓ Config file published.');

        // Publish migrations
        $this->callSilent('vendor:publish', [
            '--tag' => 'verifactu-migrations',
        ]);
        $this->info('✓ Migrations published.');

        // Run migrations
        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
            $this->info('✓ Migrations executed.');
        }

        // Reminder for .env
        $this->newLine();
        $this->info('Add the following to your .env file:');
        $this->newLine();
        $this->line('VERIFACTU_ENV=testing');
        $this->line('VERIFACTU_SIF_NAME="Your Software Name"');
        $this->line('VERIFACTU_SIF_NIF=B12345678');
        $this->line('VERIFACTU_SIF_VERSION=1.0.0');
        $this->line('VERIFACTU_CERT_PATH=/path/to/certificate.p12');
        $this->line('VERIFACTU_CERT_PASSWORD=your-password');
        $this->newLine();

        $this->info('Verifactu installed successfully!');

        return self::SUCCESS;
    }
}
