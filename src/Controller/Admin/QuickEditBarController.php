<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\Controller\Admin;

use SKrull\SyliusQuickEditBarPlugin\DependencyInjection\Configuration;
use SKrull\SyliusQuickEditBarPlugin\Exception\ResourceNotFoundException;
use SKrull\SyliusQuickEditBarPlugin\Exception\UnsupportedResourceException;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ChannelDescriber;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ImpersonationDescriber;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ResourceDescriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Renders the storefront quick edit bar, which the shop page inserts into its shadow root.
 *
 * The route must be imported with the admin prefix so that it is protected by the admin firewall.
 *
 * @phpstan-import-type Description from ResourceDescriber
 */
#[AsController]
#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
final readonly class QuickEditBarController
{
    public const string ROUTE_NAME = 's_krull_sylius_quick_edit_bar_admin_bar';

    public function __construct(
        private ResourceDescriber $resourceDescriber,
        private ChannelDescriber $channelDescriber,
        private ImpersonationDescriber $impersonationDescriber,
        private Environment $twig,
        private string $position = Configuration::POSITION_BOTTOM,
    ) {
    }

    #[Route('/quick-edit-bar', name: self::ROUTE_NAME, methods: ['GET'])]
    public function __invoke(
        #[MapQueryParameter]
        ?string $resource = null,
        #[MapQueryParameter]
        ?string $id = null,
        #[MapQueryParameter]
        ?string $channel = null,
    ): Response {
        $response = new Response($this->twig->render('@SKrullSyliusQuickEditBarPlugin/admin/quick_edit_bar.html.twig', [
            'position' => $this->position,
            'channel' => null === $channel || '' === $channel ? null : $this->channelDescriber->describe($channel),
            'impersonation' => $this->impersonationDescriber->describe(),
            'resource' => null === $resource ? null : $this->describeResource($resource, $id),
        ]));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /** @return Description */
    private function describeResource(string $resource, ?string $id): array
    {
        if (null === $id || '' === $id) {
            throw new BadRequestHttpException(sprintf('Missing id for resource "%s".', $resource));
        }

        try {
            return $this->resourceDescriber->describe($resource, $id);
        } catch (UnsupportedResourceException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        } catch (ResourceNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }
}
