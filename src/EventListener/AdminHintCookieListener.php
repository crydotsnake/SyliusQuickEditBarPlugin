<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\EventListener;

use SKrull\SyliusQuickEditBarPlugin\Controller\Admin\QuickEditBarController;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Marks the browser of a logged-in administrator with a non-sensitive hint cookie.
 *
 * The cookie only carries the path of the quick edit bar endpoint. It lets the storefront skip the
 * endpoint request for regular visitors and keeps the admin path out of the public shop HTML.
 * Authorization is still enforced by the admin firewall on the endpoint itself.
 */
final readonly class AdminHintCookieListener
{
    public const string COOKIE_NAME = 's_krull_sylius_quick_edit_bar';

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->security->getUser() instanceof AdminUserInterface) {
            return;
        }

        $endpointPath = $this->urlGenerator->generate(QuickEditBarController::ROUTE_NAME);
        $request = $event->getRequest();
        if ($request->cookies->get(self::COOKIE_NAME) === $endpointPath) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(self::COOKIE_NAME, $endpointPath)
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_LAX),
        );
    }

    #[AsEventListener]
    public function onLogout(LogoutEvent $event): void
    {
        if (!$event->getToken()?->getUser() instanceof AdminUserInterface) {
            return;
        }

        $event->getResponse()?->headers->clearCookie(self::COOKIE_NAME, '/', null, $event->getRequest()->isSecure(), false, Cookie::SAMESITE_LAX);
    }
}
