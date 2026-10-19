<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\EventListener\AdminSessionListener;
use SKrull\SyliusQuickEditBarPlugin\Twig\QuickEditBarExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class QuickEditBarExtensionTest extends TestCase
{
    public function testItReportsAFlaggedAdminSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(AdminSessionListener::SESSION_KEY, true);

        self::assertTrue($this->createExtension($this->createRequest($session, true))->hasAdminSession());
    }

    public function testItReportsNoAdminSessionWithoutFlag(): void
    {
        $session = new Session(new MockArraySessionStorage());

        self::assertFalse($this->createExtension($this->createRequest($session, true))->hasAdminSession());
    }

    public function testItDoesNotStartASessionForVisitorsWithoutSessionCookie(): void
    {
        $session = new Session(new MockArraySessionStorage());

        self::assertFalse($this->createExtension($this->createRequest($session, false))->hasAdminSession());
        self::assertFalse($session->isStarted());
    }

    public function testItReportsNoAdminSessionWithoutRequest(): void
    {
        self::assertFalse($this->createExtension(null)->hasAdminSession());
    }

    private function createExtension(?Request $request): QuickEditBarExtension
    {
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        return new QuickEditBarExtension($requestStack);
    }

    private function createRequest(Session $session, bool $withSessionCookie): Request
    {
        $request = Request::create('/', cookies: $withSessionCookie ? [$session->getName() => 'session-id'] : []);
        $request->setSession($session);

        return $request;
    }
}
