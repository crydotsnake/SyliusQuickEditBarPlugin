<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SKrull\SyliusQuickEditBarPlugin\Controller\Admin\QuickEditBarController;
use SKrull\SyliusQuickEditBarPlugin\EventListener\AdminSessionListener;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ChannelDescriber;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ImpersonationDescriber;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ResourceDescriber;
use SKrull\SyliusQuickEditBarPlugin\Twig\QuickEditBarExtension;

return static function (ContainerConfigurator $container) {
    $container->import('services/**');

    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // The position is set by the extension
    // Not autoconfigured, so it is not tagged with "routing.controller": the "routing.controllers" import of the
    // Symfony recipe would otherwise register the route a second time without the admin prefix and firewall
    $services->set(QuickEditBarController::class)
        ->public()
        ->autoconfigure(false)
        ->tag('controller.service_arguments')
        ->arg('$position', abstract_arg('position of the bar'));

    $services->set(AdminSessionListener::class);

    $services->set(QuickEditBarExtension::class);

    // The maximum number of variants is set by the extension
    $services->set(ResourceDescriber::class)
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$taxonRepository', service('sylius.repository.taxon'))
        ->arg('$maxProductVariants', abstract_arg('maximum number of listed product variants'));

    $services->set(ChannelDescriber::class)
        ->arg('$channelRepository', service('sylius.repository.channel'));

    $services->set(ImpersonationDescriber::class)
        ->arg('$shopUserRepository', service('sylius.repository.shop_user'));
};
