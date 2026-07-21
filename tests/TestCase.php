<?php

declare(strict_types=1);

namespace Trail\Trail\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Trail\Trail\TrailServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Trail\\Trail\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            McpServiceProvider::class,
            TrailServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('queue.default', 'sync');
        // Tests must not depend on a database cache table. Laravel 11+ defaults
        // CACHE_STORE to "database"; the array store keeps the suite self-contained
        // (the ingest rate limiter is cache-backed).
        config()->set('cache.default', 'array');
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', $this->connectionUnderTest());
    }

    /**
     * The connection under test. SQLite in memory by default; set TRAIL_TEST_DB
     * to pgsql or mysql to run the same suite against a real server.
     *
     * The dashboard emits driver-specific SQL (ilike vs like, date(), grouped
     * subqueries), so a SQLite-only suite can go green while Postgres breaks.
     *
     * @return array<string, mixed>
     */
    private function connectionUnderTest(): array
    {
        $driver = env('TRAIL_TEST_DB', 'sqlite');

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }

        return [
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'trail_test'),
            'username' => env('DB_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // In real apps the service provider auto-loads these for `php artisan migrate`;
        // testbench's per-test migrator needs them loaded here explicitly.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        view()->addNamespace('trail-fixtures', __DIR__.'/Fixtures/views');

        // A server-backed driver keeps the table between tests, unlike an
        // in-memory SQLite file that dies with the connection.
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }
}
