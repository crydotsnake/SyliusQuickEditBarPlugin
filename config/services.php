<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SKrull\SyliusQuickEditBarPlugin\Controller\Admin\QuickEditBarController;
use SKrull\SyliusQuickEditBarPlugin\EventListener\AdminHintCookieListener;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ResourceDescriber;

return static function (ContainerConfigurator $container) {
    $container->import('services/**');

    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(QuickEditBarController::class)
        ->public();

    $services->set(AdminHintCookieListener::class);

    $services->set(ResourceDescriber::class)
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$taxonRepository', service('sylius.repository.taxon'));
};
