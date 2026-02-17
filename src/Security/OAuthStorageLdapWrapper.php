<?php

namespace Wallabag\Security;

use FOS\OAuthServerBundle\Storage\OAuthStorage;
use OAuth2\Model\IOAuth2Client;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Wallabag\Entity\User;

class OAuthStorageLdapWrapper extends OAuthStorage
{
    /** @var object */
    private $ldapManager;

    public function setLdapManager($ldapManager): void
    {
        $this->ldapManager = $ldapManager;
    }

    public function checkUserCredentials(IOAuth2Client $client, $username, $password)
    {
        try {
            /** @var User $user */
            $user = $this->userProvider->loadUserByUsername($username);
        } catch (AuthenticationException $e) {
            return false;
        }

        if (method_exists($user, 'isLdapUser') && true === $user->isLdapUser()) {
            return $this->checkLdapUserCredentials($user, $password);
        }

        return parent::checkUserCredentials($client, $username, $password);
    }

    private function checkLdapUserCredentials(User $user, string $password)
    {
        if ($this->ldapManager->bind($user, $password)) {
            return [
                'data' => $user,
            ];
        }

        return false;
    }
}
