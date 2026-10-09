<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\QuickEditBar;

use SKrull\SyliusQuickEditBarPlugin\DependencyInjection\Configuration;
use SKrull\SyliusQuickEditBarPlugin\Exception\ResourceNotFoundException;
use SKrull\SyliusQuickEditBarPlugin\Exception\UnsupportedResourceException;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\Model\Link;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\Model\LinkGroup;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Describes a shop resource for the quick edit bar: its type, its name and the links into the Sylius admin.
 *
 * @phpstan-type Description array{key: string, type: string, name: string, links: list<array<string, mixed>>}
 */
final readonly class ResourceDescriber
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     */
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private TaxonRepositoryInterface $taxonRepository,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private int $maxProductVariants = Configuration::DEFAULT_MAX_PRODUCT_VARIANTS,
    ) {
    }

    /**
     * @param string $resource the name the shop page reports, "product" or "taxon"
     *
     * @throws UnsupportedResourceException
     * @throws ResourceNotFoundException
     *
     * @return Description
     */
    public function describe(string $resource, string $id): array
    {
        [$name, $links] = match ($resource) {
            'product' => $this->describeProduct($this->productRepository->find($id) ?? throw ResourceNotFoundException::forResource($resource, $id)),
            'taxon' => $this->describeTaxon($this->taxonRepository->find($id) ?? throw ResourceNotFoundException::forResource($resource, $id)),
            default => throw UnsupportedResourceException::forResource($resource),
        };

        return [
            'key' => $resource,
            'type' => $this->translator->trans('sylius.ui.' . $resource),
            'name' => $name ?? $id,
            'links' => array_map(static fn (Link|LinkGroup $link): array => $link->jsonSerialize(), $links),
        ];
    }

    /** @return array{?string, list<Link|LinkGroup>} */
    private function describeProduct(ProductInterface $product): array
    {
        $parameters = ['id' => $product->getId()];
        $links = [
            $this->createLink('edit', 'sylius_admin_product_update', $parameters),
            $this->createLink('show', 'sylius_admin_product_show', $parameters),
        ];

        // Simple products have exactly one variant, which is edited on the product page itself
        if (!$product->isSimple() && !$product->getVariants()->isEmpty()) {
            $links[] = $this->createVariantsGroup($product);
        }

        return [$product->getName(), $links];
    }

    /** @return array{?string, list<Link|LinkGroup>} */
    private function describeTaxon(TaxonInterface $taxon): array
    {
        return [$taxon->getName(), [
            $this->createLink('edit', 'sylius_admin_taxon_update', ['id' => $taxon->getId()]),
            $this->createLink('products', 'sylius_admin_product_taxon_index', ['taxonId' => $taxon->getId()]),
        ]];
    }

    private function createVariantsGroup(ProductInterface $product): LinkGroup
    {
        $productId = $product->getId();
        $variants = $product->getVariants();

        $links = [];
        foreach ($variants->slice(0, $this->maxProductVariants) as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $links[] = new Link(
                'edit',
                $variant->getName() ?? (string) $variant->getCode(),
                $this->urlGenerator->generate('sylius_admin_product_variant_update', ['productId' => $productId, 'id' => $variant->getId()]),
            );
        }

        $links[] = new Link(
            'index',
            $this->translator->trans('sylius.ui.list_variants'),
            $this->urlGenerator->generate('sylius_admin_product_variant_index', ['productId' => $productId]),
        );

        return new LinkGroup('variants', sprintf('%s (%d)', $this->translator->trans('sylius.ui.variants'), $variants->count()), $links);
    }

    /** @param array<string, mixed> $parameters */
    private function createLink(string $action, string $route, array $parameters): Link
    {
        return new Link($action, $this->translator->trans('sylius.ui.' . $action), $this->urlGenerator->generate($route, $parameters));
    }
}
