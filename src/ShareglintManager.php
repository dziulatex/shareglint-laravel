<?php

declare(strict_types=1);

namespace Shareglint\Laravel;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use Shareglint\Shareglint;
use Stringable;

/**
 * URL builders and the REST client, configured from config/shareglint.php.
 * Unknown methods go to the SDK client, so `Shareglint::render(...)` works
 * through the facade.
 *
 * @mixin Shareglint
 */
final class ShareglintManager
{
    public const EXTENSIONS = ['png', 'jpg', 'webp', 'pdf'];

    private ?Shareglint $client = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    /**
     * Signed `/i/` URL of `$template` filled with `$fields` — no API call,
     * the image renders on the first crawler hit. Null values are left out,
     * booleans become 1/0 and dates Y-m-d. `$size` is a key of
     * Shareglint::SIZES ('square', 'story'…), signed with the fields.
     *
     * @param  array<string, mixed>  $fields  template fields, optionally `locale`
     */
    public function url(string $template, array $fields = [], ?string $extension = null, ?string $size = null): string
    {
        $fields = self::normalize($fields);

        if ($size !== null) {
            $fields['size'] = self::size($size);
        }

        return Shareglint::signedUrl(
            $this->require('account_id', 'SHAREGLINT_ACCOUNT_ID'),
            $template,
            $fields,
            $this->require('signing_secret', 'SHAREGLINT_SIGNING_SECRET'),
            $this->extension($extension),
            $this->baseUrl(),
        );
    }

    /**
     * Auto-OG `/u/` URL of a page on one of the account's allowed domains.
     * Needs no secret: the image is built from the page's own title,
     * description and favicon. `$size` renders the same card in another frame.
     */
    public function autoOgUrl(string $pageUrl, ?string $extension = null, ?string $size = null): string
    {
        $account = $this->require('account_id', 'SHAREGLINT_ACCOUNT_ID');

        return $this->baseUrl().'/u/'.$account.'.'.$this->extension($extension).'?url='.rawurlencode($pageUrl)
            .($size === null ? '' : '&size='.self::size($size));
    }

    /**
     * Width and height (CSS px) of a size preset; null = the 1200×630 default.
     *
     * @return array{width: int, height: int}
     */
    public static function dimensions(?string $size): array
    {
        return Shareglint::SIZES[self::size($size ?? 'og')];
    }

    private static function size(string $size): string
    {
        if (! isset(Shareglint::SIZES[$size])) {
            throw new InvalidArgumentException("Shareglint: unknown size '{$size}' — use ".implode(', ', array_keys(Shareglint::SIZES)).'.');
        }

        return $size;
    }

    /** The REST client (renders, history, usage). Needs SHAREGLINT_KEY. */
    public function client(): Shareglint
    {
        return $this->client ??= new Shareglint(
            $this->require('key', 'SHAREGLINT_KEY'),
            $this->baseUrl().'/v1',
            (int) ($this->config['timeout'] ?? 30),
        );
    }

    public function defaultTemplate(): string
    {
        return (string) ($this->config['template'] ?? 'blog-og');
    }

    /** The API render `format` for a URL extension: jpg → jpeg. */
    public function format(?string $extension = null): string
    {
        $extension = $this->extension($extension);

        return $extension === 'jpg' ? 'jpeg' : $extension;
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? '') ?: 'https://shareglint.com', '/');
    }

    /** @param array<int, mixed> $arguments */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->client()->{$method}(...$arguments);
    }

    private function extension(?string $extension): string
    {
        $extension ??= (string) ($this->config['extension'] ?? 'png');

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException("Shareglint: unknown extension '{$extension}' — use ".implode(', ', self::EXTENSIONS).'.');
        }

        return $extension;
    }

    private function require(string $key, string $env): string
    {
        $value = (string) ($this->config[$key] ?? '');

        if ($value === '') {
            throw MissingConfiguration::for($key, $env);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    public static function normalize(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $name => $value) {
            $value = match (true) {
                $value === null => null,
                is_bool($value) => $value ? '1' : '0',
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                $value instanceof BackedEnum => (string) $value->value,
                is_scalar($value), $value instanceof Stringable => (string) $value,
                default => throw new InvalidArgumentException("Shareglint: field '{$name}' must be a string, number, boolean or date."),
            };

            if ($value !== null) {
                $normalized[(string) $name] = $value;
            }
        }

        return $normalized;
    }
}
