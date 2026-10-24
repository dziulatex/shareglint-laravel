<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use ImageApi\Laravel\Tests\Fixtures\Post;
use ImageApi\Laravel\Tests\TestCase;

const ESCAPED_VECTOR = 'https://imageapi.dev/i/acc_01k8example0000000000000000/blog-og.png?author=Ann%20%26%20Bob&amp;locale=en&amp;title=Hello%2C%20world%21&amp;s=cdc6c6ad85a071f083b5f1374b0a6d98';

it('renders the signed og:image and twitter tags', function () {
    $html = Blade::render('<x-og-image template="blog-og" :fields="$fields" alt="Hello" />', [
        'fields' => ['title' => 'Hello, world!', 'author' => 'Ann & Bob', 'locale' => 'en'],
    ]);

    expect($html)
        ->toContain('<meta property="og:image" content="'.ESCAPED_VECTOR.'">')
        ->toContain('<meta property="og:image:width" content="1200">')
        ->toContain('<meta property="og:image:alt" content="Hello">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta name="twitter:image" content="'.ESCAPED_VECTOR.'">')
        ->not->toContain(TestCase::SECRET);
});

it('leaves out twitter tags and alt on request', function () {
    $html = Blade::render('<x-og-image template="blog-og" :twitter="false" :width="1080" :height="1080" />');

    expect($html)->not->toContain('twitter:')->not->toContain('og:image:alt')
        ->toContain('content="1080"');
});

it('takes a HasOgImage model: stored URL first, signed URL until then', function () {
    Queue::fake();
    $post = Post::create(['title' => 'Hello, world!', 'author' => 'Ann & Bob']);

    expect(Blade::render('<x-og-image :model="$post" />', ['post' => $post]))->toContain(ESCAPED_VECTOR);

    $post->forceFill(['og_image' => 'https://cdn.example.com/renders/img_1.png'])->saveQuietly();

    expect(Blade::render('<x-og-image :model="$post" />', ['post' => $post]))
        ->toContain('content="https://cdn.example.com/renders/img_1.png"');
});

it('builds the auto-OG URL of the current page, or of a given one', function () {
    $this->get('/');

    expect(Blade::render('<x-og-image auto />'))
        ->toContain('content="https://imageapi.dev/u/'.TestCase::ACCOUNT.'.png?url=http%3A%2F%2Flocalhost"')
        ->and(Blade::render('<x-og-image page="https://blog.example.com/a" extension="webp" />'))
        ->toContain('/u/'.TestCase::ACCOUNT.'.webp?url=https%3A%2F%2Fblog.example.com%2Fa"');
});

it('needs a template, a model or auto', function () {
    expect(fn () => Blade::render('<x-og-image />'))->toThrow(Exception::class, 'pass template, model or auto');
});
