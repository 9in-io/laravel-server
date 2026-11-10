<?php

namespace Nine\Laravel\Server\Tests;

use Nine\Http\HttpClientInterface;
use Nine\Http\Response;

class ConfigTest extends TestCase
{
    public function testRoutesUseTheConfiguredPrefix(): void
    {
        $this->assertSame('/nine/authenticate', parse_url(route('nine.adapter.authenticate'), PHP_URL_PATH));
        $this->assertSame('/nine/user', parse_url(route('nine.adapter.user'), PHP_URL_PATH));
        $this->assertSame('/nine/event', parse_url(route('nine.adapter.event'), PHP_URL_PATH));
    }

    public function testOnlyPostIsRouted(): void
    {
        $this->get('/nine/user')->assertStatus(405);
    }

    public function testLoginLinkCommand(): void
    {
        $this->app['config']->set('nine-server.management', ['issuer' => 'https://acme.9in.test', 'client_id' => 'app_m2m', 'client_secret' => 's']);
        $this->app->instance(HttpClientInterface::class, new class () implements HttpClientInterface {
            public function request(string $method, string $url, array $headers = [], ?string $body = null): Response
            {
                $routes = [
                    'GET https://acme.9in.test/.well-known/openid-configuration' => ['issuer' => 'https://acme.9in.test', 'token_endpoint' => 'https://acme.9in.test/oauth/token', 'jwks_uri' => 'https://acme.9in.test/.well-known/jwks.json', 'authorization_endpoint' => 'x', 'userinfo_endpoint' => 'x'],
                    'POST https://acme.9in.test/oauth/token'                     => ['access_token' => 'tok', 'expires_in' => 3600],
                    'GET https://acme.9in.test/api/v1/users/lookup?email=jane%40acme.com' => ['user' => ['id' => 'usr_9']],
                    'POST https://acme.9in.test/api/v1/users/usr_9/login-link'   => ['url' => 'https://acme.9in.test/login-link/abc', 'expires_at' => '2030-01-01T00:00:00.000Z'],
                ];
                $json = $routes["{$method} {$url}"] ?? null;
                return $json ? new Response(200, [], json_encode($json)) : new Response(404, [], '{"error":"not_found"}');
            }
        });

        $this->artisan('nine:login-link', ['user' => 'jane@acme.com'])
            ->expectsOutput('https://acme.9in.test/login-link/abc')
            ->expectsOutput('Expires 2030-01-01T00:00:00.000Z')
            ->assertExitCode(0);

        $this->artisan('nine:login-link', ['user' => 'nobody@acme.com'])->assertExitCode(1);
    }

    public function testLegacySecretFallback(): void
    {
        putenv('SSOFY_API_SECRET=legacy-secret');
        try {
            $config = require __DIR__ . '/../config/nine-server.php';
            $this->assertSame('legacy-secret', $config['secret']);
        } finally {
            putenv('SSOFY_API_SECRET');
        }
    }
}
