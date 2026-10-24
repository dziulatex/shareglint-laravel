<?php

declare(strict_types=1);

namespace ImageApi\Laravel\Contracts;

/**
 * A model with an OG image. The HasOgImage trait implements everything here
 * except ogImageFields():
 *
 *   class Post extends Model implements OgImageable
 *   {
 *       use HasOgImage;
 *
 *       public function ogImageFields(): array { return ['title' => $this->title]; }
 *   }
 */
interface OgImageable
{
    /** @return array<string, mixed> template fields, optionally `locale` */
    public function ogImageFields(): array;

    public function ogImageTemplate(): string;

    /** Column that stores the rendered file URL. Null = never store, always sign. */
    public function ogImageColumn(): ?string;

    /** The stored file URL, or a signed URL of the same image until it is stored. */
    public function ogImageUrl(): string;
}
