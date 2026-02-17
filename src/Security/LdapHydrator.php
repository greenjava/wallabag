<?php

namespace Wallabag\Security;

use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wallabag\Entity\User;

class LdapHydrator
{
    private UserManagerInterface $userManager;
    private EventDispatcherInterface $eventDispatcher;

    /** @var array<string, string> */
    private array $attributesMap;

    private ?string $enabledAttribute;
    private string $ldapBaseDn;
    private ?string $ldapAdminFilter;

    /** @var object */
    private $ldapDriver;

    /**
     * @param array<int, string|null> $attributesMap
     */
    public function __construct(
        UserManagerInterface $userManager,
        EventDispatcherInterface $eventDispatcher,
        array $attributesMap,
        string $ldapBaseDn,
        ?string $ldapAdminFilter,
        $ldapDriver
    ) {
        $this->userManager = $userManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->attributesMap = [
            'setUsername' => (string) ($attributesMap[0] ?? 'uid'),
            'setEmail' => (string) ($attributesMap[1] ?? 'mail'),
            'setName' => (string) ($attributesMap[2] ?? 'cn'),
        ];
        $this->enabledAttribute = $attributesMap[3] ?? null;
        $this->ldapBaseDn = $ldapBaseDn;
        $this->ldapAdminFilter = $ldapAdminFilter;
        $this->ldapDriver = $ldapDriver;
    }

    /**
     * @param array<string, mixed> $ldapEntry
     */
    public function hydrate(array $ldapEntry): User
    {
        $dn = (string) ($ldapEntry['dn'] ?? '');
        /** @var User|null $user */
        $user = $this->userManager->findUserBy(['dn' => $dn]);

        if (null === $user) {
            /** @var User $user */
            $user = $this->userManager->createUser();
            $user->setDn($dn);
            $user->setPassword('');
            $this->updateUserFields($user, $ldapEntry);

            $event = new UserEvent($user);
            $this->eventDispatcher->dispatch($event, FOSUserEvents::USER_CREATED);

            $this->userManager->reloadUser($user);
        } else {
            $this->updateUserFields($user, $ldapEntry);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $ldapEntry
     */
    private function updateUserFields(User $user, array $ldapEntry): void
    {
        foreach ($this->attributesMap as $setter => $attribute) {
            if (!array_key_exists($attribute, $ldapEntry)) {
                continue;
            }

            $value = $ldapEntry[$attribute];
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (null !== $value) {
                $user->{$setter}((string) $value);
            }
        }

        if (null !== $this->enabledAttribute && array_key_exists($this->enabledAttribute, $ldapEntry)) {
            $user->setEnabled((bool) $ldapEntry[$this->enabledAttribute]);
        } else {
            $user->setEnabled(true);
        }

        if ($this->isAdmin($user)) {
            $user->addRole('ROLE_SUPER_ADMIN');
        } else {
            $user->removeRole('ROLE_SUPER_ADMIN');
        }

        $this->userManager->updateUser($user, true);
    }

    private function isAdmin(User $user): bool
    {
        if (null === $this->ldapAdminFilter || '' === trim($this->ldapAdminFilter)) {
            return false;
        }

        $escapedUsername = $user->getUsername();

        if (function_exists('ldap_escape')) {
            $escapedUsername = ldap_escape($escapedUsername, '', LDAP_ESCAPE_FILTER);
        }

        $filter = sprintf($this->ldapAdminFilter, $escapedUsername);
        $entries = $this->ldapDriver->search($this->ldapBaseDn, $filter);

        return isset($entries['count']) && 1 === (int) $entries['count'];
    }
}
