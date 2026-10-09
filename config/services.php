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

    // The position is set by the extension
    $services->set(QuickEditBarController::class)
        ->public()
        ->arg('$position', abstract_arg('position of the bar'));

    $services->set(AdminHintCookieListener::class);

    // The maximum number of variants is set by the extension
    $services->set(ResourceDescriber::class)
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$taxonRepository', service('sylius.repository.taxon'))
        ->arg('$maxProductVariants', abstract_arg('maximum number of listed product variants'));
};
