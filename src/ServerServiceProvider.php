<?php

namespace Nine\Laravel\Server;

use Illuminate\Support\ServiceProvider;
use Nine\Adapter\AdapterHandler;
use Nine\Adapter\AdapterInterface;
use Nine\Http\CurlHttpClient;
use Nine\Http\HttpClientInterface;
use Nine\Laravel\Server\Commands\CreateLoginLink;
use Nine\ManagementClient;

class ServerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nine-server.php', 'nine-server');

        $this->app->bindIf(HttpClientInterface::class, CurlHttpClient::class);

        $this->app->singleton(AdapterInterface::class, function ($app) {
            return $app->make($app['config']->get('nine-server.adapter', EloquentUserAdapter::class));
        });

        $this->app->singleton(AdapterHandler::class, function ($app) {
            $config = $app['config'];
            $secret = $config->get('nine-server.secret');
            if (empty($secret)) {
                throw new \RuntimeException('9in adapter secret is not configured (NINE_ADAPTER_SECRET).');
            }
            return new AdapterHandler(
                array_values(array_filter([$secret, $config->get('nine-server.previous_secret')])),
                $app->make(AdapterInterface::class),
                (int) $config->get('nine-server.tolerance', 300)
            );
        });

        $this->app->singleton(ManagementClient::class, function ($app) {
            $config = $app['config']->get('nine-server.management', []);
            if (empty($config['issuer']) || empty($config['client_id']) || empty($config['client_secret'])) {
                throw new \RuntimeException('9in Management API is not configured (NINE_ISSUER, NINE_CLIENT_ID, NINE_CLIENT_SECRET).');
            }
            return new ManagementClient($config['issuer'], $config['client_id'], $config['client_secret'], $app->make(HttpClientInterface::class));
        });
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../scaffold/resources/views', 'nine');

        if ($this->app['config']->get('nine-server.route.enabled', true) && !$this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/nine-server.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CreateLoginLink::class]);

            $this->publishes([
                __DIR__ . '/../config/nine-server.php' => config_path('nine-server.php'),
            ], ['nine', 'nine:config']);

            $this->publishes([
                __DIR__ . '/../scaffold/resources/views/' => resource_path('views/vendor/nine/'),
            ], ['nine', 'nine:views']);

            if (!class_exists('AddMissingColumnsToUsersTable')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/add_missing_columns_to_users_table.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_add_missing_columns_to_users_table.php'),
                ], ['nine', 'nine:migrations']);
            }
        }
    }
}
