<?php

declare(strict_types=1);

namespace Shareglint\Laravel\View\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use InvalidArgumentException;
use Shareglint\Laravel\Contracts\OgImageable;
use Shareglint\Laravel\ShareglintManager;

/**
 * og:image and twitter:image tags for the <head>.
 *
 *   <x-og-image template="blog-og" :fields="['title' => $post->title]" />  signed URL
 *   <x-og-image :model="$post" />                                           HasOgImage model
 *   <x-og-image auto />                                                     auto-OG of this page
 *   <x-og-image template="blog-og" :fields="$fields" size="square" />       a size preset; width/height follow it
 */
final class OgImage extends Component
{
    public readonly string $url;

    public readonly int $width;

    public readonly int $height;

    /** @param array<string, mixed> $fields */
    public function __construct(
        ShareglintManager $api,
        ?string $template = null,
        array $fields = [],
        ?Model $model = null,
        bool $auto = false,
        ?string $page = null,
        ?string $extension = null,
        ?string $size = null,
        public readonly ?string $alt = null,
        ?int $width = null,
        ?int $height = null,
        public readonly bool $twitter = true,
    ) {
        $frame = ShareglintManager::dimensions($size);
        $this->width = $width ?? $frame['width'];
        $this->height = $height ?? $frame['height'];

        $this->url = match (true) {
            $model !== null => self::fromModel($model),
            $auto || $page !== null => $api->autoOgUrl($page ?? url()->current(), $extension, $size),
            $template !== null => $api->url($template, $fields, $extension, $size),
            default => throw new InvalidArgumentException('<x-og-image>: pass template, model or auto.'),
        };
    }

    public function render(): string
    {
        return 'shareglint::components.og-image';
    }

    private static function fromModel(Model $model): string
    {
        if (! $model instanceof OgImageable) {
            throw new InvalidArgumentException('<x-og-image :model>: '.$model::class.' does not implement '.OgImageable::class.'.');
        }

        return $model->ogImageUrl();
    }
}
