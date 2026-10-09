<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\QuickEditBar\Model;

/** Rendered as a dropdown in the bar */
final readonly class LinkGroup implements \JsonSerializable
{
    /**
     * @param string $action identifies the group, e.g. "variants", and selects its icon in the bar
     * @param list<Link> $links
     */
    public function __construct(
        public string $action,
        public string $label,
        public array $links,
    ) {
    }

    /** @return array{type: 'group', action: string, label: string, links: list<array{type: 'link', action: string, label: string, url: string}>} */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'group',
            'action' => $this->action,
            'label' => $this->label,
            'links' => array_map(static fn (Link $link): array => $link->jsonSerialize(), $this->links),
        ];
    }
}
