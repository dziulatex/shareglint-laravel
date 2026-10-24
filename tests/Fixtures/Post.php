<?php

declare(strict_types=1);

namespace ImageApi\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use ImageApi\Laravel\Concerns\HasOgImage;
use ImageApi\Laravel\Contracts\OgImageable;

class Post extends Model implements OgImageable
{
    use HasOgImage;

    protected $guarded = [];

    /** @var array<int, string>|null */
    public static ?array $watched = null;

    public function ogImageFields(): array
    {
        return ['title' => $this->title, 'author' => $this->author, 'locale' => 'en'];
    }

    public function ogImageWatched(): ?array
    {
        return self::$watched;
    }
}
