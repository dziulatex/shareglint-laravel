<?php

declare(strict_types=1);

namespace ImageApi\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ImageApi\Laravel\ImageApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    // The documented test vector (docs.signed, tests/Feature/SignedImageUrlTest.php).
    public const ACCOUNT = 'acc_01k8example0000000000000000';

    public const SECRET = 'sign_example';

    protected function getPackageProviders($app): array
    {
        return [ImageApiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('imageapi.account_id', self::ACCOUNT);
        $app['config']->set('imageapi.signing_secret', self::SECRET);
        $app['config']->set('imageapi.key', 'sk_test');
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
