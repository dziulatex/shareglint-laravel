<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Shareglint\Laravel\Facades\Shareglint;
use Shareglint\Laravel\MissingConfiguration;
use Shareglint\Laravel\ShareglintManager;
use Shareglint\Laravel\ShareglintServiceProvider;
use Shareglint\Laravel\Tests\TestCase;
use Shareglint\Shareglint as Client;

const VECTOR_URL = 'https://shareglint.com/i/acc_01k8example0000000000000000/blog-og.png?author=Ann%20%26%20Bob&locale=en&title=Hello%2C%20world%21&s=cdc6c6ad85a071f083b5f1374b0a6d98';
const VECTOR_FIELDS = ['title' => 'Hello, world!', 'author' => 'Ann & Bob', 'locale' => 'en'];

it('signs the documented test vector through the facade and the helper', function () {
    expect(Shareglint::url('blog-og', VECTOR_FIELDS))->toBe(VECTOR_URL)
        ->and(og_image('blog-og', VECTOR_FIELDS))->toBe(VECTOR_URL);
});

it('uses the configured base URL and extension', function () {
    config()->set('shareglint.base_url', 'https://img.example.com/');
    config()->set('shareglint.extension', 'webp');
    app()->forgetInstance(ShareglintManager::class);

    expect(og_image('blog-og', VECTOR_FIELDS))
        ->toStartWith('https://img.example.com/i/'.TestCase::ACCOUNT.'/blog-og.webp?')
        ->and(og_image('blog-og', VECTOR_FIELDS, 'jpg'))->toContain('/blog-og.jpg?');
});

it('falls back to the default base URL when the setting is empty', function () {
    config()->set('shareglint.base_url', '');
    app()->forgetInstance(ShareglintManager::class);

    expect(og_image('blog-og', VECTOR_FIELDS))->toBe(VECTOR_URL);
});

it('leaves nulls out and turns booleans, dates and numbers into strings', function () {
    expect(ShareglintManager::normalize([
        'title' => 'T',
        'eyebrow' => null,
        'featured' => true,
        'draft' => false,
        'date' => new DateTimeImmutable('2026-08-17 13:00'),
        'reading_time' => 7,
    ]))->toBe(['title' => 'T', 'featured' => '1', 'draft' => '0', 'date' => '2026-08-17', 'reading_time' => '7']);

    expect(fn () => ShareglintManager::normalize(['tags' => ['a']]))->toThrow(InvalidArgumentException::class, "field 'tags'");
});

it('builds the auto-OG URL without a secret', function () {
    config()->set('shareglint.signing_secret', null);
    app()->forgetInstance(ShareglintManager::class);

    expect(Shareglint::autoOgUrl('https://blog.example.com/posts/a b?x=1'))
        ->toBe('https://shareglint.com/u/'.TestCase::ACCOUNT.'.png?url=https%3A%2F%2Fblog.example.com%2Fposts%2Fa%20b%3Fx%3D1');
});

it('says which setting is missing', function (string $key, string $env, Closure $call) {
    config()->set("shareglint.{$key}", '');
    app()->forgetInstance(ShareglintManager::class);

    expect($call)->toThrow(MissingConfiguration::class, $env);
})->with([
    'account' => ['account_id', 'SHAREGLINT_ACCOUNT_ID', fn () => og_image('blog-og')],
    'secret' => ['signing_secret', 'SHAREGLINT_SIGNING_SECRET', fn () => og_image('blog-og')],
    'key' => ['key', 'SHAREGLINT_KEY', fn () => Shareglint::client()],
]);

it('rejects an unknown extension', function () {
    expect(fn () => og_image('blog-og', [], 'jpeg'))->toThrow(InvalidArgumentException::class, 'jpeg');
});

it('maps the jpg extension to the jpeg render format', function () {
    expect(app(ShareglintManager::class)->format('jpg'))->toBe('jpeg')
        ->and(app(ShareglintManager::class)->format('webp'))->toBe('webp');
});

it('binds the SDK client to the configured key', function () {
    expect(app(Client::class))->toBeInstanceOf(Client::class)
        ->and(app(Client::class))->toBe(Shareglint::client());
});

it('publishes the config file', function () {
    $paths = ServiceProvider::pathsToPublish(ShareglintServiceProvider::class, 'shareglint-config');

    expect(array_values($paths))->toBe([config_path('shareglint.php')])
        ->and(require array_key_first($paths))->toHaveKeys(['key', 'base_url', 'account_id', 'signing_secret']);
});

it('signs a size preset with the fields and adds it to the auto-OG URL', function () {
    $square = og_image('blog-og', VECTOR_FIELDS, size: 'square');

    parse_str((string) parse_url($square, PHP_URL_QUERY), $params);
    unset($params['s']);
    ksort($params);
    $canonical = '/i/'.TestCase::ACCOUNT.'/blog-og.png?'.implode('&', array_map(
        fn ($k, $v) => rawurlencode($k).'='.rawurlencode($v), array_keys($params), $params,
    ));

    expect($params['size'])->toBe('square')
        ->and($square)->toEndWith('&s='.substr(hash_hmac('sha256', $canonical, TestCase::SECRET), 0, 32))
        ->and(Shareglint::autoOgUrl('https://a.dev/', size: 'story'))->toEndWith('?url=https%3A%2F%2Fa.dev%2F&size=story')
        ->and(fn () => og_image('blog-og', [], size: 'billboard'))->toThrow(InvalidArgumentException::class, 'billboard');
});

it('signs animated gif/mp4 URLs with duration and fps, and refuses them on a still extension', function () {
    $gif = og_image('animated-countdown', ['number' => 12], 'gif', duration: 4, fps: 12);
    parse_str((string) parse_url($gif, PHP_URL_QUERY), $query);

    expect($gif)->toStartWith('https://shareglint.com/i/'.TestCase::ACCOUNT.'/animated-countdown.gif?duration=4&fps=12&number=12&s=')
        ->and($gif)->toBe(Client::signedUrl(TestCase::ACCOUNT, 'animated-countdown', ['number' => '12', 'duration' => '4', 'fps' => '12'], TestCase::SECRET, 'gif'))
        ->and(Shareglint::url('animated-countdown', [], 'mp4'))->toContain('/animated-countdown.mp4?s=')
        ->and(fn () => og_image('blog-og', [], 'png', duration: 3))->toThrow(InvalidArgumentException::class, 'duration')
        ->and(fn () => og_image('blog-og', [], fps: 15))->toThrow(InvalidArgumentException::class, 'fps')
        ->and(fn () => Shareglint::autoOgUrl('https://a.dev/', 'gif'))->toThrow(InvalidArgumentException::class, 'gif');
});
