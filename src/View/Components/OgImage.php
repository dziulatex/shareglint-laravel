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
 */
final class OgImage extends Component
{
    public readonly string $url;

    /** @param array<string, mixed> $fields */
    public function __construct(
        ShareglintManager $api,
        ?string $template = null,
        array $fields = [],
        ?Model $model = null,
        bool $auto = false,
        ?string $page = null,
        ?string $extension = null,
        public readonly ?string $alt = null,
        public readonly int $width = 1200,
        public readonly int $height = 630,
        public readonly bool $twitter = true,
    ) {
        $this->url = match (true) {
            $model !== null => self::fromModel($model),
            $auto || $page !== null => $api->autoOgUrl($page ?? url()->current(), $extension),
            $template !== null => $api->url($template, $fields, $extension),
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
