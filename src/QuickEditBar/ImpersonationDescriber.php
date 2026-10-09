<?php

declare(strict_types=1);

namespace SKrull\SyliusQuickEditBarPlugin\QuickEditBar;

use Sylius\Bundle\CoreBundle\Security\ImpersonationVoter;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Describes the customer an administrator currently impersonates in the shop, with a link to the customer in the admin.
 *
 * Admin and shop share the session. Sylius marks an impersonation in it and removes the mark on shop logout,
 * so customers who log in themselves are never reported. Nothing about the customer is added to the shop HTML.
 *
 * @phpstan-type ImpersonationDescription array{label: string, url: string}
 */
final readonly class ImpersonationDescriber
{
    /** @param UserRepositoryInterface<ShopUserInterface> $shopUserRepository */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private RequestStack $requestStack,
        private UserRepositoryInterface $shopUserRepository,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        #[Autowire(param: 'sylius_shop.firewall_context_name')]
        private string $shopFirewallContextName,
    ) {
    }

    /** @return ImpersonationDescription|null */
    public function describe(): ?array
    {
        if (!$this->authorizationChecker->isGranted(ImpersonationVoter::IS_IMPERSONATOR_SYLIUS, $this->shopFirewallContextName)) {
            return null;
        }

        $customer = $this->findImpersonatedUser()?->getCustomer();
        if (null === $customer) {
            return null;
        }

        return [
            'label' => $this->translator->trans('s_krull_sylius_quick_edit_bar.ui.impersonating', ['%name%' => $customer->getEmail()]),
            'url' => $this->urlGenerator->generate('sylius_admin_customer_show', ['id' => $customer->getId()]),
        ];
    }

    private function findImpersonatedUser(): ?ShopUserInterface
    {
        try {
            // Symfony keeps the token of each firewall context in the session, like the UserImpersonator of Sylius writes it
            $serializedToken = $this->requestStack->getSession()->get('_security_' . $this->shopFirewallContextName);
        } catch (SessionNotFoundException) {
            return null;
        }

        if (!\is_string($serializedToken)) {
            return null;
        }

        // The session is server side and Symfony unserializes this token the same way. Allowed classes cannot
        // be listed, since the token and user classes depend on the application
        /** @noinspection UnserializeExploitsInspection */
        $token = unserialize($serializedToken);
        $user = $token instanceof TokenInterface ? $token->getUser() : null;
        if (!$user instanceof ShopUserInterface || null === $user->getId()) {
            return null;
        }

        // The serialized user only holds a few fields, the customer has to be loaded
        return $this->shopUserRepository->find($user->getId());
    }
}
