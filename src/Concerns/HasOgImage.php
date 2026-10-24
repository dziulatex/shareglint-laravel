<?php

declare(strict_types=1);

namespace Shareglint\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Shareglint\Laravel\Contracts\OgImageable;
use Shareglint\Laravel\Jobs\GenerateOgImage;
use Shareglint\Laravel\ShareglintManager;

/**
 * Gives an Eloquent model an OG image.
 *
 * On create, and whenever an update changes a watched attribute, a queued
 * GenerateOgImage job renders the image through the API and stores its file
 * URL in the `og_image` column. Until it has, `ogImageUrl()` returns a signed
 * URL of the same image, so a page never goes out without one.
 *
 * The model implements OgImageable and ogImageFields(); override the other
 * methods to change the template, the column or what counts as a change.
 *
 * @mixin Model
 *
 * @phpstan-require-implements OgImageable
 */
trait HasOgImage
{
    public static function bootHasOgImage(): void
    {
        // Fail on first use rather than on the first queued job.
        if (! is_subclass_of(static::class, OgImageable::class)) {
            throw new LogicException(static::class.' uses HasOgImage, so it must implement '.OgImageable::class.'.');
        }

        // `created` and `updated` rather than `saved`: `updated` fires only
        // when something changed, after getChanges() holds this save's
        // changes — `saved` also fires for no-op saves, with the previous
        // save's changes and wasRecentlyCreated still set.
        static::created(static function (self $model): void {
            if ($model->ogImageColumn() !== null) {
                GenerateOgImage::dispatch($model)->afterCommit();
            }
        });

        static::updated(static function (self $model): void {
            if ($model->shouldGenerateOgImage()) {
                GenerateOgImage::dispatch($model)->afterCommit();
            }
        });
    }

    public function ogImageTemplate(): string
    {
        return app(ShareglintManager::class)->defaultTemplate();
    }

    /** Column that stores the rendered file URL. Null = never store, always sign. */
    public function ogImageColumn(): ?string
    {
        return 'og_image';
    }

    /**
     * Attributes whose change re-renders the image. Null = any attribute
     * except the image column and the timestamps. A re-render with the same
     * fields costs no quota (the API returns the cached file), so the
     * default is safe, just chattier.
     *
     * @return array<int, string>|null
     */
    public function ogImageWatched(): ?array
    {
        return null;
    }

    /** Whether the update that just happened needs a new image. */
    public function shouldGenerateOgImage(): bool
    {
        if ($this->ogImageColumn() === null) {
            return false;
        }

        $changed = array_keys($this->getChanges());
        $watched = $this->ogImageWatched();

        $relevant = $watched === null
            ? array_diff($changed, array_filter([$this->ogImageColumn(), $this->getCreatedAtColumn(), $this->getUpdatedAtColumn()]))
            : array_intersect($changed, $watched);

        return $relevant !== [];
    }

    /** The stored file URL, or a signed URL of the same image until the job has run. */
    public function ogImageUrl(): string
    {
        $column = $this->ogImageColumn();
        $stored = $column === null ? null : $this->getAttribute($column);

        return is_string($stored) && $stored !== '' ? $stored : $this->ogImageSignedUrl();
    }

    public function ogImageSignedUrl(): string
    {
        return app(ShareglintManager::class)->url($this->ogImageTemplate(), $this->ogImageFields());
    }
}
