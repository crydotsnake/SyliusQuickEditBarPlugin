<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\Controller\Admin;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\Controller\Admin\QuickEditBarController;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ResourceDescriber;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class QuickEditBarControllerTest extends TestCase
{
    public function testItReturnsOnlyTheAdministrationLinkWithoutResource(): void
    {
        self::assertSame(
            [
                'label' => 's_krull_sylius_quick_edit_bar.ui.quick_edit_bar',
                'position' => 'bottom',
                'toggle' => ['hide' => 's_krull_sylius_quick_edit_bar.ui.hide', 'show' => 's_krull_sylius_quick_edit_bar.ui.show'],
                'administration' => ['label' => 'sylius.ui.administration', 'url' => '/sylius_admin_dashboard'],
                'resource' => null,
            ],
            $this->decode($this->createController()()),
        );
    }

    public function testItAddsTheDescribedResource(): void
    {
        $resource = $this->decode($this->createController()('product', '13'))['resource'] ?? null;

        self::assertIsArray($resource);
        self::assertSame('sylius.ui.product', $resource['type'] ?? null);
    }

    public function testItReturnsTheConfiguredPosition(): void
    {
        self::assertSame('top', $this->decode($this->createController('top')())['position'] ?? null);
    }

    public function testItIsNeverStoredInAnyCache(): void
    {
        $response = $this->createController()();

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testItRejectsAResourceWithoutId(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->createController()('product');
    }

    public function testItRejectsAnUnsupportedResource(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->createController()('order', '1');
    }

    public function testItReturnsNotFoundForAMissingResource(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()('product', '999');
    }

    private function createController(string $position = 'bottom'): QuickEditBarController
    {
        $product = $this->createStub(ProductInterface::class);
        $product->method('getVariants')->willReturn(new ArrayCollection());

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('find')->willReturnCallback(static fn (mixed $id): ?ProductInterface => '13' === $id ? $product : null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route): string => '/' . $route);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $describer = new ResourceDescriber($productRepository, $this->createStub(TaxonRepositoryInterface::class), $urlGenerator, $translator);

        return new QuickEditBarController($describer, $urlGenerator, $translator, $position);
    }

    /** @return array<array-key, mixed> */
    private function decode(JsonResponse $response): array
    {
        $content = json_decode((string) $response->getContent(), true);
        self::assertIsArray($content);

        return $content;
    }
}
