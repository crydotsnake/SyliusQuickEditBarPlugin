<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\EventListener\AdminSessionListener;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class AdminSessionListenerTest extends TestCase
{
    public function testItFlagsTheSessionOfAnAdministrator(): void
    {
        $request = $this->createRequestWithSession('/admin/');

        $this->createListener(true)->onKernelResponse($this->createResponseEvent($request));

        self::assertTrue($request->getSession()->get(AdminSessionListener::SESSION_KEY));
    }

    public function testItDoesNotFlagTheSessionWithoutAdministrationAccess(): void
    {
        $request = $this->createRequestWithSession('/');

        $this->createListener(false)->onKernelResponse($this->createResponseEvent($request));

        self::assertFalse($request->getSession()->has(AdminSessionListener::SESSION_KEY));
    }

    public function testItDoesNotFlagTheSessionOnSubRequests(): void
    {
        $request = $this->createRequestWithSession('/admin/');

        $this->createListener(true)->onKernelResponse($this->createResponseEvent($request, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($request->getSession()->has(AdminSessionListener::SESSION_KEY));
    }

    public function testItDoesNotStartASessionForVisitorsWithoutSessionCookie(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $this->createListener(true)->onKernelResponse($this->createResponseEvent($request));

        self::assertFalse($session->isStarted());
    }

    public function testItRemovesTheFlagOnAdminLogout(): void
    {
        $request = $this->createRequestWithSession('/admin/logout');
        $request->getSession()->set(AdminSessionListener::SESSION_KEY, true);

        $this->createListener(false)->onLogout(new LogoutEvent($request, $this->createToken($this->createStub(AdminUserInterface::class))));

        self::assertFalse($request->getSession()->has(AdminSessionListener::SESSION_KEY));
    }

    public function testItKeepsTheFlagOnShopLogout(): void
    {
        $request = $this->createRequestWithSession('/logout');
        $request->getSession()->set(AdminSessionListener::SESSION_KEY, true);

        $this->createListener(false)->onLogout(new LogoutEvent($request, $this->createToken($this->createStub(ShopUserInterface::class))));

        self::assertTrue($request->getSession()->get(AdminSessionListener::SESSION_KEY));
    }

    private function createListener(bool $isGranted): AdminSessionListener
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => $isGranted && 'ROLE_ADMINISTRATION_ACCESS' === $attribute,
        );

        return new AdminSessionListener($security);
    }

    /** A request of a browser that already holds a session cookie */
    private function createRequestWithSession(string $uri): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create($uri, cookies: [$session->getName() => 'session-id']);
        $request->setSession($session);

        return $request;
    }

    private function createResponseEvent(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $requestType, new Response());
    }

    private function createToken(UserInterface $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
