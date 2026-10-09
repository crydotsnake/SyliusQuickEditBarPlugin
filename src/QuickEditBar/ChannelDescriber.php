<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\QuickEditBar;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Describes the channel of the shop page the bar is shown on, with a link to its admin page.
 *
 * The shop page reports the channel code, because the admin request cannot reliably resolve the channel of the shop page,
 * e.g. when several channels share a hostname.
 *
 * @phpstan-type ChannelDescription array{label: string, name: string, url: string}
 */
final readonly class ChannelDescriber
{
    /** @param ChannelRepositoryInterface<ChannelInterface> $channelRepository */
    public function __construct(
        private ChannelRepositoryInterface $channelRepository,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    /** @return ChannelDescription|null null for an unknown channel, the bar is still useful without it */
    public function describe(string $code): ?array
    {
        $channel = $this->channelRepository->findOneByCode($code);
        if (null === $channel) {
            return null;
        }

        return [
            'label' => $this->translator->trans('sylius.ui.channel'),
            'name' => $channel->getName() ?? $code,
            'url' => $this->urlGenerator->generate('sylius_admin_channel_update', ['id' => $channel->getId()]),
        ];
    }
}
