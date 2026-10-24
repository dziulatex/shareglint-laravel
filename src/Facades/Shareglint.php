<?php

declare(strict_types=1);

namespace Shareglint\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Shareglint\Laravel\ShareglintManager;

/**
 * @method static string url(string $template, array<string, mixed> $fields = [], ?string $extension = null)
 * @method static string autoOgUrl(string $pageUrl, ?string $extension = null)
 * @method static \Shareglint\Shareglint client()
 * @method static \Shareglint\Render render(string $template, array<string, mixed> $modifications, ?string $locale = null, ?string $format = null, ?int $quality = null, ?int $scale = null)
 * @method static \Shareglint\Render queue(string $template, array<string, mixed> $modifications, ?string $webhookUrl = null, ?string $format = null, ?int $quality = null, ?int $scale = null, ?string $locale = null)
 * @method static \Shareglint\Render get(string $renderId)
 * @method static array<string, mixed> usage()
 *
 * @see ShareglintManager
 */
final class Shareglint extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ShareglintManager::class;
    }
}
