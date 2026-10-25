<?php

declare(strict_types=1);

use Shareglint\Laravel\ShareglintManager;

if (! function_exists('og_image')) {
    /**
     * Signed og:image URL of a template — no API call; the image renders on
     * the first crawler hit. `og_image('blog-og', ['title' => $post->title])`,
     * or in another size: `og_image('blog-og', $fields, size: 'square')`.
     *
     * @param  array<string, mixed>  $fields  template fields, optionally `locale`
     */
    function og_image(string $template, array $fields = [], ?string $extension = null, ?string $size = null): string
    {
        return app(ShareglintManager::class)->url($template, $fields, $extension, $size);
    }
}
