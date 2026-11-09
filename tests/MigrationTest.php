<?php

namespace Nine\Laravel\Server\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nine\Laravel\Server\ServerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/** The published migration adds the columns 9in maps to a default Laravel users table. */
class MigrationTest extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [ServerServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('nine-server.secret', 'adapter_secret_test');
    }

    private function stub(): string
    {
        return dirname(__DIR__) . '/database/migrations/add_missing_columns_to_users_table.php.stub';
    }

    public function testIsPublishedUnderTheNineTags(): void
    {
        foreach (['nine', 'nine:migrations'] as $tag) {
            $target = null;
            foreach (ServiceProvider::pathsToPublish(ServerServiceProvider::class, $tag) as $from => $to) {
                if (realpath($from) === realpath($this->stub())) {
                    $target = $to;
                }
            }
            $this->assertNotNull($target, "published with --tag={$tag}");
            $this->assertMatchesRegularExpression('#migrations/\d{4}_\d{2}_\d{2}_\d{6}_add_missing_columns_to_users_table\.php$#', $target);
        }
    }

    public function testAddsTheMissingColumnsToTheDefaultUsersTableAndCanRunAgain(): void
    {
        // Laravel's default users table.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        require_once $this->stub();
        $migration = new \AddMissingColumnsToUsersTable();
        $migration->up();
        $migration->up(); // existing columns are left alone

        foreach (['username', 'email', 'email_verified_at', 'phone', 'phone_verified_at', 'metadata', 'avatar_url'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column), $column);
        }
        $this->assertSame(1, \DB::table('users')->insertGetId(['name' => 'Jane', 'email' => 'jane@acme.test', 'password' => 'x', 'username' => 'jane', 'phone' => '+447700900000', 'metadata' => '{"plan":"pro"}']));
    }
}
