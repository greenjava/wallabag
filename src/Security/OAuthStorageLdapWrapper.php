<?php

namespace Wallabag\Security;

use FOS\OAuthServerBundle\Storage\OAuthStorage;
use OAuth2\Model\IOAuth2Client;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Wallabag\Entity\User;

class OAuthStorageLdapWrapper extends OAuthStorage
{
    private LdapBinder $ldapBinder;

    public function setLdapBinder(LdapBinder $ldapBinder): void
    {
        $this->ldapBinder = $ldapBinder;
    }

    public function checkUserCredentials(IOAuth2Client $client, $username, $password)
    {
        try {
            /** @var User $user */
            $user = $this->userProvider->loadUserByUsername($username);
        } catch (AuthenticationException $e) {
            return false;
        }

        if ($this->checkLdapUserCredentials($user->getUsername(), (string) $password)) {
            return [
                'data' => $user,
            ];
        }

        return parent::checkUserCredentials($client, $username, $password);
    }

    private function checkLdapUserCredentials(string $username, string $password): bool
    {
        return $this->ldapBinder->bind($username, $password);
    }
}
