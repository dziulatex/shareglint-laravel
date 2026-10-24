<?php

declare(strict_types=1);

namespace Shareglint\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Shareglint\Laravel\ShareglintServiceProvider;

abstract class TestCase extends Orchestra
{
    // The documented test vector (docs.signed, tests/Feature/SignedImageUrlTest.php).
    public const ACCOUNT = 'acc_01k8example0000000000000000';

    public const SECRET = 'sign_example';

    protected function getPackageProviders($app): array
    {
        return [ShareglintServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('shareglint.account_id', self::ACCOUNT);
        $app['config']->set('shareglint.signing_secret', self::SECRET);
        $app['config']->set('shareglint.key', 'sk_test');
        $app['config']->set('app.url', 'https://blog.example.com');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('author')->default('');
            $table->unsignedInteger('views')->default(0);
            $table->string('og_image')->nullable();
            $table->timestamps();
        });
    }
}
