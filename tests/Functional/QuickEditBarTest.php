<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Functional;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use SKrull\SyliusQuickEditBarPlugin\EventListener\AdminHintCookieListener;
use Sylius\Bundle\CoreBundle\Security\ImpersonationVoter;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QuickEditBarTest extends WebTestCase
{
    private const string ENDPOINT = '/admin/quick-edit-bar';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        (new ORMPurger($this->entityManager))->purge();
    }

    public function testItRendersTheAdministrationLinkWithoutResource(): void
    {
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $crawler = $this->client->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertSame('bottom', $crawler->filter('[data-position]')->attr('data-position'));
        self::assertSame('Quick edit bar', $crawler->filter('nav')->attr('aria-label'));
        self::assertSame(['administration' => ['Administration', '/admin/']], $this->getLinks($crawler));
        self::assertSame('Hide quick edit bar', $crawler->filter('[data-test-quick-edit-bar-toggle="hide"]')->attr('aria-label'));
        self::assertSame('Show quick edit bar', $crawler->filter('[data-test-quick-edit-bar-toggle="show"]')->attr('aria-label'));
        self::assertSame('Move quick edit bar', $crawler->filter('[data-test-quick-edit-bar-handle]')->attr('aria-label'));
        self::assertCount(0, $crawler->filter('.resource'));
        self::assertCount(4, $crawler->filter('svg'), 'Every link, toggle and the handle have an icon');
    }

    public function testItReturnsTheProductLinksForAnAdministrator(): void
    {
        $product = $this->createProduct();
        $productId = $product->getId();
        self::assertIsInt($productId);
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $crawler = $this->client->request('GET', self::ENDPOINT, ['resource' => 'product', 'id' => $productId]);

        self::assertResponseIsSuccessful();
        self::assertSame('Product:', $crawler->filter('.resource-type')->text());
        self::assertSame('Quick edit mug', $crawler->filter('.resource-name')->text());
        self::assertSame(
            [
                'administration' => ['Administration', '/admin/'],
                'action-edit' => ['Edit', sprintf('/admin/products/%d/edit', $productId)],
                'action-show' => ['Show', sprintf('/admin/products/%d', $productId)],
            ],
            $this->getLinks($crawler),
        );
    }

    public function testItListsTheVariantsOfAConfigurableProduct(): void
    {
        $product = $this->createProduct();
        $this->addVariant($product, 'QUICK_EDIT_MUG_SMALL', 'Small mug');
        $this->addVariant($product, 'QUICK_EDIT_MUG_LARGE', null);
        $this->entityManager->flush();
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $crawler = $this->client->request('GET', self::ENDPOINT, ['resource' => 'product', 'id' => $product->getId()]);

        self::assertResponseIsSuccessful();
        $group = $crawler->filter('[data-test-quick-edit-bar-link="action-variants"]');
        self::assertSame('Variants (2)', $group->attr('aria-label'));

        $menu = $crawler->filter('[data-test-quick-edit-bar-menu="variants"]');
        self::assertSame($group->attr('popovertarget'), $menu->attr('id'));

        $variantIds = [];
        foreach ($product->getVariants() as $variant) {
            $variantIds[] = $variant->getId();
        }
        self::assertSame(
            [
                ['Small mug', $this->generateUrl('sylius_admin_product_variant_update', ['productId' => $product->getId(), 'id' => $variantIds[0]])],
                ['QUICK_EDIT_MUG_LARGE', $this->generateUrl('sylius_admin_product_variant_update', ['productId' => $product->getId(), 'id' => $variantIds[1]])],
                ['List variants', $this->generateUrl('sylius_admin_product_variant_index', ['productId' => $product->getId()])],
            ],
            $menu->filter('a')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href')]),
        );
    }

    public function testItReturnsTheTaxonLinks(): void
    {
        $taxon = $this->createTaxon();
        $taxonId = $taxon->getId();
        self::assertIsInt($taxonId);
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $crawler = $this->client->request('GET', self::ENDPOINT, ['resource' => 'taxon', 'id' => $taxonId]);

        self::assertResponseIsSuccessful();
        self::assertSame('Taxon:', $crawler->filter('.resource-type')->text());
        self::assertSame('T-Shirts', $crawler->filter('.resource-name')->text());
        self::assertSame(
            [
                'administration' => ['Administration', '/admin/'],
                'action-edit' => ['Edit', $this->generateUrl('sylius_admin_taxon_update', ['id' => $taxonId])],
                'action-products' => ['Products', $this->generateUrl('sylius_admin_product_taxon_index', ['taxonId' => $taxonId])],
            ],
            $this->getLinks($crawler),
        );
    }

    public function testItReturnsTheChannelOfTheShopPage(): void
    {
        $channel = $this->createChannel();
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $crawler = $this->client->request('GET', self::ENDPOINT, ['channel' => 'QUICK_EDIT_WEB']);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Quick edit web store', $this->generateUrl('sylius_admin_channel_update', ['id' => $channel->getId()])],
            $this->getLinks($crawler)['channel'] ?? null,
        );
        self::assertSame('Channel: Quick edit web store', $crawler->filter('[data-test-quick-edit-bar-link="channel"]')->attr('aria-label'));
    }

    public function testItIgnoresAnUnknownChannel(): void
    {
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $crawler = $this->client->request('GET', self::ENDPOINT, ['channel' => 'UNKNOWN']);

        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('channel', $this->getLinks($crawler));
    }

    public function testItReportsTheImpersonatedCustomer(): void
    {
        if (!class_exists(ImpersonationVoter::class)) {
            self::markTestSkipped('Sylius marks impersonations since 2.1 only.');
        }

        $customer = $this->createShopUser()->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        $this->client->loginUser($this->createAdminUser(), 'admin');

        // The impersonate button on the customer page of the admin
        $this->client->request('GET', $this->generateUrl('sylius_admin_impersonate_user', ['username' => 'customer@example.com']));
        $crawler = $this->client->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Impersonating customer@example.com', $this->generateUrl('sylius_admin_customer_show', ['id' => $customer->getId()])],
            $this->getLinks($crawler)['impersonation'] ?? null,
        );
    }

    public function testItDoesNotReportACustomerWhoLoggedInThemselves(): void
    {
        $this->client->loginUser($this->createShopUser(), 'shop');
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $crawler = $this->client->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('impersonation', $this->getLinks($crawler));
    }

    public function testItReturnsBadRequestWithoutId(): void
    {
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $this->client->request('GET', self::ENDPOINT, ['resource' => 'product']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testItDeniesAccessForAnonymousVisitors(): void
    {
        $this->client->request('GET', self::ENDPOINT);

        self::assertResponseRedirects();
    }

    public function testItDeniesAccessForShopUsers(): void
    {
        $this->client->loginUser($this->createShopUser(), 'shop');

        $this->client->request('GET', self::ENDPOINT);

        self::assertResponseRedirects();
    }

    public function testItReturnsNotFoundForAnUnknownProduct(): void
    {
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $this->client->request('GET', self::ENDPOINT, ['resource' => 'product', 'id' => 999999]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturnsBadRequestForAnUnsupportedResource(): void
    {
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $this->client->request('GET', self::ENDPOINT, ['resource' => 'order', 'id' => 1]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testItSetsTheHintCookieForAnAdministrator(): void
    {
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $this->client->request('GET', self::ENDPOINT);

        $cookie = $this->findResponseCookie(AdminHintCookieListener::COOKIE_NAME);
        self::assertNotNull($cookie);
        self::assertSame(self::ENDPOINT, $cookie->getValue());
        self::assertSame('/', $cookie->getPath());
        self::assertFalse($cookie->isHttpOnly());
    }

    public function testItDoesNotSetTheHintCookieForAnonymousVisitors(): void
    {
        $this->client->request('GET', self::ENDPOINT);

        self::assertNull($this->findResponseCookie(AdminHintCookieListener::COOKIE_NAME));
    }

    public function testItClearsTheHintCookieOnAdminLogout(): void
    {
        $this->client->loginUser($this->createAdminUser(), 'admin');

        $this->client->request('GET', '/admin/logout');

        $cookie = $this->findResponseCookie(AdminHintCookieListener::COOKIE_NAME);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isCleared());
    }

    /** @param array<string, mixed> $parameters */
    private function generateUrl(string $route, array $parameters): string
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(UrlGeneratorInterface::class, $router);

        return $router->generate($route, $parameters);
    }

    /** @return array<string, array{string, ?string}> label and URL of each link in the bar, by its test name */
    private function getLinks(Crawler $crawler): array
    {
        $links = [];
        foreach ($crawler->filter('a[data-test-quick-edit-bar-link]') as $link) {
            $link = new Crawler($link);
            $links[(string) $link->attr('data-test-quick-edit-bar-link')] = [$link->text(), $link->attr('href')];
        }

        return $links;
    }

    private function createProduct(): ProductInterface
    {
        /** @var FactoryInterface<ProductInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product');
        $product = $factory->createNew();
        $product->setCode('QUICK_EDIT_MUG');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setName('Quick edit mug');
        $product->setSlug('quick-edit-mug');

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function addVariant(ProductInterface $product, string $code, ?string $name): void
    {
        /** @var FactoryInterface<ProductVariantInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product_variant');
        $variant = $factory->createNew();
        $variant->setCode($code);
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setName($name);
        $product->addVariant($variant);

        $this->entityManager->persist($variant);
    }

    private function createTaxon(): TaxonInterface
    {
        /** @var FactoryInterface<TaxonInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.taxon');
        $taxon = $factory->createNew();
        $taxon->setCode('T_SHIRTS');
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');
        $taxon->setName('T-Shirts');
        $taxon->setSlug('t-shirts');

        $this->entityManager->persist($taxon);
        $this->entityManager->flush();

        return $taxon;
    }

    private function createChannel(): ChannelInterface
    {
        /** @var FactoryInterface<ChannelInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.channel');
        $channel = $factory->createNew();
        $channel->setCode('QUICK_EDIT_WEB');
        $channel->setName('Quick edit web store');

        /** @var FactoryInterface<LocaleInterface> $localeFactory */
        $localeFactory = self::getContainer()->get('sylius.factory.locale');
        $locale = $localeFactory->createNew();
        $locale->setCode('en_US');
        $channel->setDefaultLocale($locale);

        /** @var FactoryInterface<CurrencyInterface> $currencyFactory */
        $currencyFactory = self::getContainer()->get('sylius.factory.currency');
        $currency = $currencyFactory->createNew();
        $currency->setCode('USD');
        $channel->setBaseCurrency($currency);

        $this->entityManager->persist($locale);
        $this->entityManager->persist($currency);

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return $channel;
    }

    private function createAdminUser(): AdminUserInterface
    {
        /** @var FactoryInterface<AdminUserInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.admin_user');
        $adminUser = $factory->createNew();
        $adminUser->setUsername('admin');
        $adminUser->setEmail('admin@example.com');
        $adminUser->setLocaleCode('en_US');
        $adminUser->setEnabled(true);

        $this->entityManager->persist($adminUser);
        $this->entityManager->flush();

        return $adminUser;
    }

    private function createShopUser(): ShopUserInterface
    {
        /** @var FactoryInterface<CustomerInterface> $customerFactory */
        $customerFactory = self::getContainer()->get('sylius.factory.customer');
        $customer = $customerFactory->createNew();
        $customer->setEmail('customer@example.com');

        /** @var FactoryInterface<ShopUserInterface> $shopUserFactory */
        $shopUserFactory = self::getContainer()->get('sylius.factory.shop_user');
        $shopUser = $shopUserFactory->createNew();
        $shopUser->setCustomer($customer);
        $shopUser->setUsername('customer@example.com');
        $shopUser->setEnabled(true);

        $this->entityManager->persist($shopUser);
        $this->entityManager->flush();

        return $shopUser;
    }

    private function findResponseCookie(string $name): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
