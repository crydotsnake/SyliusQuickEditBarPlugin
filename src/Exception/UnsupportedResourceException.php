<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\Exception;

final class UnsupportedResourceException extends \InvalidArgumentException
{
    public static function forResource(string $resource): self
    {
        return new self(sprintf('The resource "%s" is not supported by the quick edit bar.', $resource));
    }
}
