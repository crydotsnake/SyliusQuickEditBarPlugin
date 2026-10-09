<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\Controller\Admin\QuickEditBarController;
use SKrull\SyliusQuickEditBarPlugin\EventListener\AdminHintCookieListener;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class AdminHintCookieListenerTest extends TestCase
{
    private const string ENDPOINT_PATH = '/admin/quick-edit-bar';

    public function testItSetsTheHintCookieForAnAdministrator(): void
    {
        $response = new Response();

        $this->createListener($this->createStub(AdminUserInterface::class))
            ->onKernelResponse($this->createResponseEvent(Request::create('/admin/'), $response));

        $cookie = $this->findCookie($response);
        self::assertNotNull($cookie);
        self::assertSame(self::ENDPOINT_PATH, $cookie->getValue());
        self::assertSame('/', $cookie->getPath());
        self::assertFalse($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testItMarksTheHintCookieSecureOnHttpsRequests(): void
    {
        $response = new Response();

        $this->createListener($this->createStub(AdminUserInterface::class))
            ->onKernelResponse($this->createResponseEvent(Request::create('https://shop.test/admin/'), $response));

        self::assertTrue($this->findCookie($response)?->isSecure());
    }

    public function testItDoesNotSetTheHintCookieWithoutUser(): void
    {
        $response = new Response();

        $this->createListener(null)->onKernelResponse($this->createResponseEvent(Request::create('/'), $response));

        self::assertNull($this->findCookie($response));
    }

    public function testItDoesNotSetTheHintCookieForShopUsers(): void
    {
        $response = new Response();

        $this->createListener($this->createStub(ShopUserInterface::class))
            ->onKernelResponse($this->createResponseEvent(Request::create('/'), $response));

        self::assertNull($this->findCookie($response));
    }

    public function testItDoesNotSetTheHintCookieOnSubRequests(): void
    {
        $response = new Response();

        $this->createListener($this->createStub(AdminUserInterface::class))
            ->onKernelResponse($this->createResponseEvent(Request::create('/admin/'), $response, HttpKernelInterface::SUB_REQUEST));

        self::assertNull($this->findCookie($response));
    }

    public function testItDoesNotResendAnUpToDateHintCookie(): void
    {
        $response = new Response();
        $request = Request::create('/admin/', cookies: [AdminHintCookieListener::COOKIE_NAME => self::ENDPOINT_PATH]);

        $this->createListener($this->createStub(AdminUserInterface::class))
            ->onKernelResponse($this->createResponseEvent($request, $response));

        self::assertNull($this->findCookie($response));
    }

    public function testItClearsTheHintCookieOnAdminLogout(): void
    {
        $response = new Response();
        $event = new LogoutEvent(Request::create('/admin/logout'), $this->createToken($this->createStub(AdminUserInterface::class)));
        $event->setResponse($response);

        $this->createListener(null)->onLogout($event);

        self::assertTrue($this->findCookie($response)?->isCleared());
    }

    public function testItKeepsTheHintCookieOnShopLogout(): void
    {
        $response = new Response();
        $event = new LogoutEvent(Request::create('/logout'), $this->createToken($this->createStub(ShopUserInterface::class)));
        $event->setResponse($response);

        $this->createListener(null)->onLogout($event);

        self::assertNull($this->findCookie($response));
    }

    private function createListener(?UserInterface $user): AdminHintCookieListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnMap([
            [QuickEditBarController::ROUTE_NAME, [], UrlGeneratorInterface::ABSOLUTE_PATH, self::ENDPOINT_PATH],
        ]);

        return new AdminHintCookieListener($security, $urlGenerator);
    }

    private function createResponseEvent(
        Request $request,
        Response $response,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $requestType, $response);
    }

    private function createToken(UserInterface $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function findCookie(Response $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === AdminHintCookieListener::COOKIE_NAME) {
                return $cookie;
            }
        }

        return null;
    }
}
