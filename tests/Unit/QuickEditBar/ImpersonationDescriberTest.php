<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\QuickEditBar;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\QuickEditBar\ImpersonationDescriber;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ShopUser;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImpersonationDescriberTest extends TestCase
{
    public function testItDescribesTheImpersonatedCustomer(): void
    {
        $shopUser = $this->createShopUser();

        self::assertSame(
            ['label' => 's_krull_sylius_quick_edit_bar.ui.impersonating customer@example.com', 'url' => '/sylius_admin_customer_show?id=5'],
            $this->createDescriber(impersonating: true, sessionUser: $shopUser, storedUser: $shopUser)->describe(),
        );
    }

    public function testItDescribesNothingWithoutImpersonation(): void
    {
        $shopUser = $this->createShopUser();

        // A customer who logged in themselves
        self::assertNull($this->createDescriber(impersonating: false, sessionUser: $shopUser, storedUser: $shopUser)->describe());
    }

    public function testItDescribesNothingWithoutShopToken(): void
    {
        self::assertNull($this->createDescriber(impersonating: true, sessionUser: null, storedUser: null)->describe());
    }

    public function testItDescribesNothingForADeletedShopUser(): void
    {
        self::assertNull($this->createDescriber(impersonating: true, sessionUser: $this->createShopUser(), storedUser: null)->describe());
    }

    private function createDescriber(bool $impersonating, ?ShopUserInterface $sessionUser, ?ShopUserInterface $storedUser): ImpersonationDescriber
    {
        // Written like the UserImpersonator of Sylius does
        $session = new Session(new MockArraySessionStorage());
        if ($impersonating) {
            $session->set('_security_impersonate_sylius_shop', true);
        }
        if (null !== $sessionUser) {
            $session->set('_security_shop', serialize(new UsernamePasswordToken($sessionUser, 'shop', $sessionUser->getRoles())));
        }

        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $shopUserRepository = $this->createStub(UserRepositoryInterface::class);
        $shopUserRepository->method('find')->willReturn($storedUser);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/' . $route . '?' . http_build_query($parameters),
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => strtr($id . ' %name%', $parameters));

        return new ImpersonationDescriber($requestStack, $shopUserRepository, $urlGenerator, $translator, 'shop');
    }

    private function createShopUser(): ShopUserInterface
    {
        $customer = new Customer();
        $customer->setEmail('customer@example.com');
        // Ids are set by Doctrine only
        (new \ReflectionProperty(Customer::class, 'id'))->setValue($customer, 5);

        $shopUser = new ShopUser();
        $shopUser->setCustomer($customer);
        $shopUser->setUsername('customer@example.com');
        (new \ReflectionProperty(ShopUser::class, 'id'))->setValue($shopUser, 9);

        return $shopUser;
    }
}
