<?php

declare(strict_types=1);

namespace O3\EntraSync;

use Illuminate\Support\ServiceProvider;
use O3\EntraSync\Commands\SyncUsersFromEntra;

class EntraSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/entra-sync.php', 'entra-sync');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncUsersFromEntra::class]);

            $this->publishes([
                __DIR__.'/Config/entra-sync.php' => config_path('entra-sync.php'),
            ], 'entra-sync-config');

            $this->publishes([
                __DIR__.'/../database/migrations/2024_01_01_000001_add_entra_fields_to_users_table.php' => database_path('migrations/2024_01_01_000001_add_entra_fields_to_users_table.php'),
            ], 'entra-sync-migrations');
        }
    }
}
