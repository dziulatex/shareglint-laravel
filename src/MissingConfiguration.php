<?php

declare(strict_types=1);

namespace ImageApi\Laravel;

use InvalidArgumentException;

final class MissingConfiguration extends InvalidArgumentException
{
    public static function for(string $key, string $env): self
    {
        return new self("Image API: set {$env} (config imageapi.{$key}).");
    }
}
