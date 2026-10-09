<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\Controller\Admin;

use SKrull\SyliusQuickEditBarPlugin\Exception\ResourceNotFoundException;
use SKrull\SyliusQuickEditBarPlugin\Exception\UnsupportedResourceException;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ResourceDescriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides the content of the storefront quick edit bar.
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
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/quick-edit-bar', name: self::ROUTE_NAME, methods: ['GET'])]
    public function __invoke(
        #[MapQueryParameter]
        ?string $resource = null,
        #[MapQueryParameter]
        ?string $id = null,
    ): JsonResponse {
        $response = new JsonResponse([
            'label' => $this->translator->trans('s_krull_sylius_quick_edit_bar.ui.quick_edit_bar'),
            'administration' => [
                'label' => $this->translator->trans('sylius.ui.administration'),
                'url' => $this->urlGenerator->generate('sylius_admin_dashboard'),
            ],
            'resource' => null === $resource ? null : $this->describeResource($resource, $id),
        ]);
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
