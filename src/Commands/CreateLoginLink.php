<?php

namespace Nine\Laravel\Server\Commands;

use Illuminate\Console\Command;
use Nine\Exceptions\NineException;
use Nine\ManagementClient;

/**
 * Replaces SSOfy's `ssofy:token-*` commands (auth/token): creates a single-use 9in
 * sign-in URL through the Management API.
 */
class CreateLoginLink extends Command
{
    protected $signature = 'nine:login-link
        {user : 9in user id (usr_…) or the user\'s email address}
        {--return-to= : Registered redirect URL of one of your applications}
        {--ttl=600 : Lifetime in seconds}';

    protected $description = 'Create a single-use 9in sign-in link for a user';

    public function handle(ManagementClient $nine)
    {
        $user = (string) $this->argument('user');

        try {
            if (strpos($user, '@') !== false) {
                $found = $nine->findUserByEmail($user);
                if (!$found) {
                    $this->error("No 9in user with the email {$user}. Adapter users appear in 9in after their first sign-in.");
                    return 1;
                }
                $user = (string) $found['id'];
            }

            $link = $nine->createLoginLink($user, array_filter([
                'returnTo'   => $this->option('return-to'),
                'ttlSeconds' => (int) $this->option('ttl'),
            ]));
        } catch (NineException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->line($link['url']);
        $this->comment('Expires ' . $link['expires_at']);   // ISO 8601 UTC

        return 0;
    }
}
