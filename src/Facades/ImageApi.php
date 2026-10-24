<?php

declare(strict_types=1);

namespace ImageApi\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use ImageApi\Laravel\ImageApiManager;

/**
 * @method static string url(string $template, array<string, mixed> $fields = [], ?string $extension = null)
 * @method static string autoOgUrl(string $pageUrl, ?string $extension = null)
 * @method static \ImageApi\ImageApi client()
 * @method static \ImageApi\Render render(string $template, array<string, mixed> $modifications, ?string $locale = null, ?string $format = null, ?int $quality = null, ?int $scale = null)
 * @method static \ImageApi\Render queue(string $template, array<string, mixed> $modifications, ?string $webhookUrl = null, ?string $format = null, ?int $quality = null, ?int $scale = null, ?string $locale = null)
 * @method static \ImageApi\Render get(string $renderId)
 * @method static array<string, mixed> usage()
 *
 * @see ImageApiManager
 */
final class ImageApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ImageApiManager::class;
    }
}
