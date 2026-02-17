<?php

namespace Wallabag\Security;

use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;

class LdapBinder
{
    private LdapInterface $ldap;
    private string $baseDn;
    private string $filter;
    private string $usernameAttribute;
    private ?string $managerDn;
    private ?string $managerPassword;

    public function __construct(
        LdapInterface $ldap,
        string $baseDn,
        string $filter,
        string $usernameAttribute,
        ?string $managerDn,
        ?string $managerPassword
    ) {
        $this->ldap = $ldap;
        $this->baseDn = $baseDn;
        $this->filter = $filter;
        $this->usernameAttribute = $usernameAttribute;
        $this->managerDn = $managerDn;
        $this->managerPassword = $managerPassword;
    }

    public function bind(string $username, string $password): bool
    {
        if ('' === trim($password)) {
            return false;
        }

        if (null !== $this->managerDn && '' !== trim($this->managerDn)) {
            $this->ldap->bind($this->managerDn, (string) $this->managerPassword);
        }

        $escapedUsername = Ldap::escape($username, '', LdapInterface::ESCAPE_FILTER);
        $filter = str_replace(
            ['{uid_key}', '{username}'],
            [$this->usernameAttribute, $escapedUsername],
            $this->filter
        );

        $query = $this->ldap->query($this->baseDn, $filter);
        $results = $query->execute();

        if (0 === count($results)) {
            return false;
        }

        $entry = $results[0];
        $dn = $entry->getDn();

        if (null === $dn || '' === trim($dn)) {
            return false;
        }

        try {
            $this->ldap->bind($dn, $password);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
