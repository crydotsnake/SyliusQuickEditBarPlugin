<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\Controller\Admin;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\Controller\Admin\QuickEditBarController;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ChannelDescriber;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ImpersonationDescriber;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ResourceDescriber;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class QuickEditBarControllerTest extends TestCase
{
    public function testItRendersOnlyTheBarWithoutResource(): void
    {
        self::assertSame(
            [
                'template' => '@SKrullSyliusQuickEditBarPlugin/admin/quick_edit_bar.html.twig',
                'position' => 'bottom',
                'channel' => null,
                'impersonation' => null,
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

    public function testItAddsTheChannelOfTheShopPage(): void
    {
        self::assertSame(
            ['label' => 'sylius.ui.channel', 'name' => 'Fashion Web Store', 'url' => '/sylius_admin_channel_update'],
            $this->decode($this->createController()(channel: 'FASHION_WEB'))['channel'] ?? null,
        );
    }

    public function testItIgnoresAnUnknownChannel(): void
    {
        $response = $this->decode($this->createController()(channel: 'UNKNOWN'));

        self::assertArrayHasKey('channel', $response);
        self::assertNull($response['channel']);
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

        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getName')->willReturn('Fashion Web Store');

        $channelRepository = $this->createStub(ChannelRepositoryInterface::class);
        $channelRepository->method('findOneByCode')->willReturnCallback(static fn (string $code): ?ChannelInterface => 'FASHION_WEB' === $code ? $channel : null);

        // Not impersonating
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);

        // Exposes the template and its context instead of rendering it
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $template, array $context): string => (string) json_encode(['template' => $template] + $context));

        return new QuickEditBarController(
            new ResourceDescriber($productRepository, $this->createStub(TaxonRepositoryInterface::class), $urlGenerator, $translator),
            new ChannelDescriber($channelRepository, $urlGenerator, $translator),
            new ImpersonationDescriber($authorizationChecker, new RequestStack(), $this->createStub(UserRepositoryInterface::class), $urlGenerator, $translator, 'shop'),
            $twig,
            $position,
        );
    }

    /** @return array<array-key, mixed> */
    private function decode(Response $response): array
    {
        $content = json_decode((string) $response->getContent(), true);
        self::assertIsArray($content);

        return $content;
    }
}
