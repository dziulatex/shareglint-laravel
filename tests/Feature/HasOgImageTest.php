<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use ImageApi\Laravel\Concerns\HasOgImage;
use ImageApi\Laravel\ImageApiManager;
use ImageApi\Laravel\Jobs\GenerateOgImage;
use ImageApi\Laravel\Tests\Fixtures\Post;

beforeEach(function () {
    Queue::fake();
    Post::$watched = null;
});

it('queues a render when a model is created', function () {
    $post = Post::create(['title' => 'First']);

    Queue::assertPushed(GenerateOgImage::class, fn (GenerateOgImage $job) => $job->model->is($post));
});

it('queues after commit, so the worker never loads a row that is not saved yet', function () {
    Post::create(['title' => 'First']);

    Queue::assertPushed(GenerateOgImage::class, fn (GenerateOgImage $job) => $job->afterCommit === true);
});

it('does not queue on a save that changes nothing', function () {
    $post = Post::create(['title' => 'First']);
    $post->update(['title' => 'Second']);

    $post->save();
    $post->update(['title' => 'Second']);

    Queue::assertPushed(GenerateOgImage::class, 2);
});

it('does not queue for a model whose column is null', function () {
    $post = new class(['title' => 'x']) extends Post
    {
        protected $table = 'posts';

        public function ogImageColumn(): ?string
        {
            return null;
        }
    };
    $post->save();
    $post->update(['title' => 'y']);

    Queue::assertNothingPushed();
});

it('re-renders on any change except the image column and timestamps by default', function () {
    $post = Post::create(['title' => 'First']);

    $post->update(['og_image' => 'https://cdn.example.com/x.png']);
    $post->touch();
    Queue::assertPushed(GenerateOgImage::class, 1);

    $post->update(['views' => 5]);
    Queue::assertPushed(GenerateOgImage::class, 2);
});

it('re-renders only on watched attributes when the model lists them', function () {
    Post::$watched = ['title'];
    $post = Post::create(['title' => 'First']);

    $post->update(['views' => 5]);
    Queue::assertPushed(GenerateOgImage::class, 1);

    $post->update(['title' => 'Second']);
    Queue::assertPushed(GenerateOgImage::class, 2);
});

it('uses the configured default template', function () {
    config()->set('imageapi.template', 'article');
    app()->forgetInstance(ImageApiManager::class);

    expect((new Post(['title' => 'x']))->ogImageTemplate())->toBe('article')
        ->and((new Post(['title' => 'x']))->ogImageSignedUrl())->toContain('/article.png?');
});

it('fails on first use when the model does not implement OgImageable', function () {
    expect(fn () => new class extends Model
    {
        use HasOgImage;

        public function ogImageFields(): array
        {
            return [];
        }
    })->toThrow(LogicException::class, 'must implement');
});
