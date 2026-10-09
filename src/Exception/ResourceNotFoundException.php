<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\Exception;

final class ResourceNotFoundException extends \RuntimeException
{
    public static function forResource(string $resource, string $id): self
    {
        return new self(sprintf('The resource "%s" with id "%s" does not exist.', $resource, $id));
    }
}
