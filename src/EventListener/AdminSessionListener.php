<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\EventListener;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Flags the session of a logged-in administrator, so shop pages add the quick edit bar for them only.
 *
 * Admin and shop share the session. The flag only decides whether the shop page asks for the bar,
 * the admin firewall still protects the endpoint, so a stale flag shows nothing.
 */
final readonly class AdminSessionListener
{
    public const string SESSION_KEY = 's_krull_sylius_quick_edit_bar';

    public function __construct(
        private Security $security,
    ) {
    }

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        // Administrators always have a session, checking the token without one would mark the response private
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession() || !$this->security->isGranted('ROLE_ADMINISTRATION_ACCESS')) {
            return;
        }

        $session = $request->getSession();
        if (true !== $session->get(self::SESSION_KEY)) {
            $session->set(self::SESSION_KEY, true);
        }
    }

    #[AsEventListener]
    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->getToken()?->getUser() instanceof AdminUserInterface || !$request->hasSession()) {
            return;
        }

        $request->getSession()->remove(self::SESSION_KEY);
    }
}
