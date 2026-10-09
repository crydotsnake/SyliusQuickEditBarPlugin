<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const string POSITION_BOTTOM = 'bottom';

    public const string POSITION_TOP = 'top';

    public const int DEFAULT_MAX_PRODUCT_VARIANTS = 20;

    /** @return TreeBuilder<'array'> */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('s_krull_sylius_quick_edit_bar');

        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('position')
                    ->info('Edge of the viewport the floating bar is shown at until it is moved to another corner. Bottom keeps the shop header with its navigation and cart free.')
                    ->values([self::POSITION_BOTTOM, self::POSITION_TOP])
                    ->defaultValue(self::POSITION_BOTTOM)
                ->end()
                ->arrayNode('product')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_variants')
                            ->info('Number of variants listed in the variants dropdown, the last entry always links to the full list.')
                            ->defaultValue(self::DEFAULT_MAX_PRODUCT_VARIANTS)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
