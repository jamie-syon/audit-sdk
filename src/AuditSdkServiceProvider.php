<?php

namespace Syon\AuditSdk;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Syon\AuditSdk\Client\AuditClient;
use Syon\AuditSdk\Client\RequestSigner;
use Syon\AuditSdk\Console\CatalogueCommand;

class AuditSdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/audit-sdk.php', 'audit-sdk');

        $this->app->singleton(AuditClient::class, function (Application $app) {
            $config = $app['config']['audit-sdk'];

            return new AuditClient(
                baseUrl: (string) ($config['base_url'] ?? ''),
                projectId: (string) ($config['project_id'] ?? ''),
                signer: new RequestSigner((string) ($config['push_secret'] ?? '')),
                timeout: (int) ($config['timeout'] ?? 10),
                retries: (int) ($config['retries'] ?? 2),
            );
        });

        $this->app->alias(AuditClient::class, 'audit-sdk');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CatalogueCommand::class]);

            $this->publishes([
                __DIR__.'/../config/audit-sdk.php' => $this->app->configPath('audit-sdk.php'),
            ], 'audit-sdk-config');
        }
    }
}
