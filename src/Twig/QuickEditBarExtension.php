<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\Twig;

use SKrull\SyliusQuickEditBarPlugin\EventListener\AdminSessionListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class QuickEditBarExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('s_krull_sylius_quick_edit_bar_has_admin_session', $this->hasAdminSession(...)),
        ];
    }

    /** Whether the session was flagged by the AdminSessionListener, never starts a session for visitors without one */
    public function hasAdminSession(): bool
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request || !$request->hasPreviousSession()) {
            return false;
        }

        return true === $request->getSession()->get(AdminSessionListener::SESSION_KEY);
    }
}
