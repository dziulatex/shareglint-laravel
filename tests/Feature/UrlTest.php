<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use ImageApi\ImageApi as Client;
use ImageApi\Laravel\Facades\ImageApi;
use ImageApi\Laravel\ImageApiManager;
use ImageApi\Laravel\ImageApiServiceProvider;
use ImageApi\Laravel\MissingConfiguration;
use ImageApi\Laravel\Tests\TestCase;

const VECTOR_URL = 'https://imageapi.dev/i/acc_01k8example0000000000000000/blog-og.png?author=Ann%20%26%20Bob&locale=en&title=Hello%2C%20world%21&s=cdc6c6ad85a071f083b5f1374b0a6d98';
const VECTOR_FIELDS = ['title' => 'Hello, world!', 'author' => 'Ann & Bob', 'locale' => 'en'];

it('signs the documented test vector through the facade and the helper', function () {
    expect(ImageApi::url('blog-og', VECTOR_FIELDS))->toBe(VECTOR_URL)
        ->and(og_image('blog-og', VECTOR_FIELDS))->toBe(VECTOR_URL);
});

it('uses the configured base URL and extension', function () {
    config()->set('imageapi.base_url', 'https://img.example.com/');
    config()->set('imageapi.extension', 'webp');
    app()->forgetInstance(ImageApiManager::class);

    expect(og_image('blog-og', VECTOR_FIELDS))
        ->toStartWith('https://img.example.com/i/'.TestCase::ACCOUNT.'/blog-og.webp?')
        ->and(og_image('blog-og', VECTOR_FIELDS, 'jpg'))->toContain('/blog-og.jpg?');
});

it('falls back to the default base URL when the setting is empty', function () {
    config()->set('imageapi.base_url', '');
    app()->forgetInstance(ImageApiManager::class);

    expect(og_image('blog-og', VECTOR_FIELDS))->toBe(VECTOR_URL);
});

it('leaves nulls out and turns booleans, dates and numbers into strings', function () {
    expect(ImageApiManager::normalize([
        'title' => 'T',
        'eyebrow' => null,
        'featured' => true,
        'draft' => false,
        'date' => new DateTimeImmutable('2026-08-17 13:00'),
        'reading_time' => 7,
    ]))->toBe(['title' => 'T', 'featured' => '1', 'draft' => '0', 'date' => '2026-08-17', 'reading_time' => '7']);

    expect(fn () => ImageApiManager::normalize(['tags' => ['a']]))->toThrow(InvalidArgumentException::class, "field 'tags'");
});

it('builds the auto-OG URL without a secret', function () {
    config()->set('imageapi.signing_secret', null);
    app()->forgetInstance(ImageApiManager::class);

    expect(ImageApi::autoOgUrl('https://blog.example.com/posts/a b?x=1'))
        ->toBe('https://imageapi.dev/u/'.TestCase::ACCOUNT.'.png?url=https%3A%2F%2Fblog.example.com%2Fposts%2Fa%20b%3Fx%3D1');
});

it('says which setting is missing', function (string $key, string $env, Closure $call) {
    config()->set("imageapi.{$key}", '');
    app()->forgetInstance(ImageApiManager::class);

    expect($call)->toThrow(MissingConfiguration::class, $env);
})->with([
    'account' => ['account_id', 'IMAGE_API_ACCOUNT_ID', fn () => og_image('blog-og')],
    'secret' => ['signing_secret', 'IMAGE_API_SIGNING_SECRET', fn () => og_image('blog-og')],
    'key' => ['key', 'IMAGE_API_KEY', fn () => ImageApi::client()],
]);

it('rejects an unknown extension', function () {
    expect(fn () => og_image('blog-og', [], 'jpeg'))->toThrow(InvalidArgumentException::class, 'jpeg');
});

it('maps the jpg extension to the jpeg render format', function () {
    expect(app(ImageApiManager::class)->format('jpg'))->toBe('jpeg')
        ->and(app(ImageApiManager::class)->format('webp'))->toBe('webp');
});

it('binds the SDK client to the configured key', function () {
    expect(app(Client::class))->toBeInstanceOf(Client::class)
        ->and(app(Client::class))->toBe(ImageApi::client());
});

it('publishes the config file', function () {
    $paths = ServiceProvider::pathsToPublish(ImageApiServiceProvider::class, 'imageapi-config');

    expect(array_values($paths))->toBe([config_path('imageapi.php')])
        ->and(require array_key_first($paths))->toHaveKeys(['key', 'base_url', 'account_id', 'signing_secret']);
});
