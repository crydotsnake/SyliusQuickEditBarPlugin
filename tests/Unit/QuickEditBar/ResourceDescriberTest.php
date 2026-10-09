<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\QuickEditBar;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\Exception\ResourceNotFoundException;
use SKrull\SyliusQuickEditBarPlugin\Exception\UnsupportedResourceException;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ResourceDescriber;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ResourceDescriberTest extends TestCase
{
    public function testItDescribesASimpleProduct(): void
    {
        self::assertSame(
            [
                'key' => 'product',
                'type' => 'sylius.ui.product',
                'name' => 'T-Shirt banana',
                'links' => [
                    ['type' => 'link', 'action' => 'edit', 'label' => 'sylius.ui.edit', 'url' => '/sylius_admin_product_update?id=13'],
                    ['type' => 'link', 'action' => 'show', 'label' => 'sylius.ui.show', 'url' => '/sylius_admin_product_show?id=13'],
                ],
            ],
            $this->createDescriber(product: $this->createProduct(isSimple: true, variantCount: 1))->describe('product', '13'),
        );
    }

    public function testItListsTheVariantsOfAConfigurableProduct(): void
    {
        $links = $this->createDescriber(product: $this->createProduct(isSimple: false, variantCount: 2))->describe('product', '13')['links'];

        self::assertSame(
            ['type' => 'group', 'action' => 'variants', 'label' => 'sylius.ui.variants (2)', 'links' => [
                ['type' => 'link', 'action' => 'edit', 'label' => 'Variant 1', 'url' => '/sylius_admin_product_variant_update?productId=13&id=1'],
                ['type' => 'link', 'action' => 'edit', 'label' => 'VARIANT_2', 'url' => '/sylius_admin_product_variant_update?productId=13&id=2'],
                ['type' => 'link', 'action' => 'index', 'label' => 'sylius.ui.list_variants', 'url' => '/sylius_admin_product_variant_index?productId=13'],
            ]],
            $links[2] ?? null,
        );
    }

    public function testItLimitsTheNumberOfListedVariants(): void
    {
        $links = $this->createDescriber(product: $this->createProduct(isSimple: false, variantCount: 30))->describe('product', '13')['links'];

        self::assertSame('sylius.ui.variants (30)', $links[2]['label'] ?? null);
        self::assertIsArray($links[2]['links'] ?? null);
        self::assertCount(ResourceDescriber::MAX_VARIANTS + 1, $links[2]['links']);
    }

    public function testItDoesNotListVariantsOfAConfigurableProductWithoutVariants(): void
    {
        $links = $this->createDescriber(product: $this->createProduct(isSimple: false, variantCount: 0))->describe('product', '13')['links'];

        self::assertSame(['edit', 'show'], array_column($links, 'action'));
    }

    public function testItDescribesATaxon(): void
    {
        $taxon = $this->createStub(TaxonInterface::class);
        $taxon->method('getId')->willReturn(7);
        $taxon->method('getName')->willReturn('T-Shirts');

        self::assertSame(
            [
                'key' => 'taxon',
                'type' => 'sylius.ui.taxon',
                'name' => 'T-Shirts',
                'links' => [
                    ['type' => 'link', 'action' => 'edit', 'label' => 'sylius.ui.edit', 'url' => '/sylius_admin_taxon_update?id=7'],
                    ['type' => 'link', 'action' => 'products', 'label' => 'sylius.ui.products', 'url' => '/sylius_admin_product_taxon_index?taxonId=7'],
                ],
            ],
            $this->createDescriber(taxon: $taxon)->describe('taxon', '7'),
        );
    }

    public function testItFallsBackToTheIdWithoutName(): void
    {
        $taxon = $this->createStub(TaxonInterface::class);
        $taxon->method('getId')->willReturn(7);
        $taxon->method('getName')->willReturn(null);

        self::assertSame('7', $this->createDescriber(taxon: $taxon)->describe('taxon', '7')['name']);
    }

    public function testItRejectsAnUnsupportedResource(): void
    {
        $this->expectException(UnsupportedResourceException::class);

        $this->createDescriber()->describe('order', '1');
    }

    public function testItFailsForAMissingResource(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->createDescriber()->describe('product', '999');
    }

    private function createDescriber(?ProductInterface $product = null, ?TaxonInterface $taxon = null): ResourceDescriber
    {
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('find')->willReturnCallback(static fn (mixed $id): ?ProductInterface => '13' === $id ? $product : null);

        $taxonRepository = $this->createStub(TaxonRepositoryInterface::class);
        $taxonRepository->method('find')->willReturnCallback(static fn (mixed $id): ?TaxonInterface => '7' === $id ? $taxon : null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/' . $route . '?' . http_build_query($parameters),
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ResourceDescriber($productRepository, $taxonRepository, $urlGenerator, $translator);
    }

    private function createProduct(bool $isSimple, int $variantCount): ProductInterface
    {
        $variants = [];
        for ($id = 1; $id <= $variantCount; ++$id) {
            $variant = $this->createStub(ProductVariantInterface::class);
            $variant->method('getId')->willReturn($id);
            // Variants without name fall back to their code
            $variant->method('getName')->willReturn(0 === $id % 2 ? null : 'Variant ' . $id);
            $variant->method('getCode')->willReturn('VARIANT_' . $id);
            $variants[] = $variant;
        }

        $product = $this->createStub(ProductInterface::class);
        $product->method('getId')->willReturn(13);
        $product->method('getName')->willReturn('T-Shirt banana');
        $product->method('isSimple')->willReturn($isSimple);
        $product->method('getVariants')->willReturn(new ArrayCollection($variants));

        return $product;
    }
}
