<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\QuickEditBar\Model;

final readonly class Link implements \JsonSerializable
{
    /**
     * @param string $action identifies the link, e.g. "edit", and selects its icon in the bar
     */
    public function __construct(
        public string $action,
        public string $label,
        public string $url,
    ) {
    }

    /** @return array{type: 'link', action: string, label: string, url: string} */
    public function jsonSerialize(): array
    {
        return ['type' => 'link', 'action' => $this->action, 'label' => $this->label, 'url' => $this->url];
    }
}
