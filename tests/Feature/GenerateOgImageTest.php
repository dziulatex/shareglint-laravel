<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use ImageApi\ImageApiException;
use ImageApi\Laravel\ImageApiManager;
use ImageApi\Laravel\Jobs\GenerateOgImage;
use ImageApi\Laravel\Tests\Fixtures\Post;

// The job goes through the real PHP SDK and cURL to a stand-in API served by
// `php -S` — the SDK client is final, and a fake at the HTTP level checks
// what actually goes over the wire.
beforeEach(function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $this->log = tempnam(sys_get_temp_dir(), 'fake-api');
    $this->server = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/../Fixtures/fake-api.php'],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes,
        null,
        ['FAKE_API_LOG' => $this->log],
    );

    for ($i = 0; $i < 50 && ! @fsockopen('127.0.0.1', $port); $i++) {
        usleep(100_000);
    }

    config()->set('imageapi.base_url', "http://127.0.0.1:{$port}");
    app()->forgetInstance(ImageApiManager::class);

    Queue::fake();
    $this->post = Post::create(['title' => 'Hello, world!', 'author' => 'Ann & Bob']);
});

afterEach(function () {
    proc_terminate($this->server);
    @unlink($this->log);
    @unlink($this->log.'.status');
});

function requests(string $log): array
{
    return array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", (string) file_get_contents($log))));
}

it('renders through the API and stores the file URL', function () {
    app()->call([new GenerateOgImage($this->post), 'handle']);

    [$request] = requests($this->log);

    expect($request['method'])->toBe('POST')
        ->and($request['uri'])->toBe('/v1/images?wait=true')
        ->and($request['authorization'])->toBe('Bearer sk_test')
        ->and($request['idempotency_key'])->toStartWith('og-')
        ->and($request['body'])->toBe([
            'template' => 'blog-og',
            'modifications' => ['title' => 'Hello, world!', 'author' => 'Ann & Bob', 'locale' => 'en'],
            'format' => 'png',
        ])
        ->and($this->post->fresh()->og_image)->toBe('https://cdn.example.com/renders/img_1.png');
});

it('does not queue itself again when it stores the URL', function () {
    app()->call([new GenerateOgImage($this->post), 'handle']);

    Queue::assertPushed(GenerateOgImage::class, 1); // the create, not the write-back
});

it('sends the same idempotency key for the same fields and a new one when they change', function () {
    app()->call([new GenerateOgImage($this->post), 'handle']);
    app()->call([new GenerateOgImage($this->post->fresh()), 'handle']);

    $this->post->update(['title' => 'Changed']);
    app()->call([new GenerateOgImage($this->post->fresh()), 'handle']);

    $keys = array_column(requests($this->log), 'idempotency_key');

    expect($keys[0])->toBe($keys[1])->and($keys[2])->not->toBe($keys[0]);
});

it('asks for the configured format', function () {
    config()->set('imageapi.extension', 'jpg');
    app()->forgetInstance(ImageApiManager::class);

    app()->call([new GenerateOgImage($this->post), 'handle']);

    expect(requests($this->log)[0]['body']['format'])->toBe('jpeg');
});

it('leaves the column alone when the quota is gone or the API is busy', function (int $status) {
    file_put_contents($this->log.'.status', (string) $status);

    app()->call([new GenerateOgImage($this->post), 'handle']);

    expect($this->post->fresh()->og_image)->toBeNull();
})->with(['quota' => 402, 'overloaded' => 503]);

it('throws on other API errors so the queue retries', function () {
    file_put_contents($this->log.'.status', '500');

    expect(fn () => app()->call([new GenerateOgImage($this->post), 'handle']))->toThrow(ImageApiException::class);
});

it('queues on the configured connection and queue', function () {
    config()->set('imageapi.queue', ['connection' => 'redis', 'name' => 'images']);

    $job = new GenerateOgImage($this->post);

    expect($job->connection)->toBe('redis')->and($job->queue)->toBe('images');
});

it('survives the queue serializer', function () {
    $job = unserialize(serialize(new GenerateOgImage($this->post)));

    app()->call([$job, 'handle']);

    expect($this->post->fresh()->og_image)->toBe('https://cdn.example.com/renders/img_1.png');
});
