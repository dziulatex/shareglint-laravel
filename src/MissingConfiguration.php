<?php

declare(strict_types=1);

namespace Shareglint\Laravel;

use InvalidArgumentException;

final class MissingConfiguration extends InvalidArgumentException
{
    public static function for(string $key, string $env): self
    {
        return new self("Shareglint: set {$env} (config shareglint.{$key}).");
    }
}
