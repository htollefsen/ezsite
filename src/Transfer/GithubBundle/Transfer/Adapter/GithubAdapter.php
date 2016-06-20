<?php

/*
 * This file is part of Transfer.
 *
 * For the full copyright and license information, please view the LICENSE file located
 * in the root directory.
 */

namespace Transfer\GithubBundle\Transfer\Adapter;

use Github\Api\Organization;
use Github\Api\User;
use Github\Client as GithubClient;
use Github\HttpClient\CachedHttpClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Transfer\Adapter\SourceAdapterInterface;
use Transfer\Adapter\Transaction\Request;
use Transfer\Adapter\Transaction\Response;

class GithubAdapter implements SourceAdapterInterface, LoggerAwareInterface
{
    /**
     * @var LoggerInterface Logger
     */
    protected $logger;

    /**
     * @var array Options
     */
    protected $options;

    /**
     * @var GithubClient
     */
    protected $client;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);

        $this->client = new GithubClient(
            new CachedHttpClient(array('cache_dir' => $this->options['cache_dir']))
        );
    }

    /**
     * Option configuration.
     *
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'organizations' => false,
            'users' => false,
            'cache_dir' => __DIR__.'/../../../../app/cache/transfer/github-api-cache',
        ));
        $resolver->setAllowedTypes('cache_dir', array('string'));
        $resolver->setAllowedTypes('organizations', array('array', 'bool'));
        $resolver->setAllowedTypes('users', array('array', 'bool'));
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(Request $request)
    {
        $response = new Response();

        $response->setData(new \ArrayIterator(array(
            'users' => $this->getUsers($this->options['users']),
            'organizations' => $this->getOrganizations($this->options['organizations']),
        )));

        return $response;
    }

    /**
     * @param string[]|bool $names Array of organization names, or false
     *
     * @return array information about the organization
     */
    protected function getOrganizations($names)
    {
        if (!$names) {
            return false;
        }

        $organizations = [];
        foreach ($names as $name) {
            /** @var Organization $api */
            $api = $this->client->api('organization');
            $organization = $api->show($name);
            $organization['repositories'] = $api->repositories($name);
            //$organization['members'] = $api->members()->all($name);
            $organizations[] = $organization;
        }

        return $organizations;
    }

    /**
     * @param string[] $usernames
     *
     * @return array|bool
     */
    protected function getUsers($usernames)
    {
        if (!$usernames) {
            return false;
        }

        $users = [];
        foreach ($usernames as $username) {
            /** @var User $api */
            $api = $this->client->api('users');
            $user = $api->show($username);
            //$user['organizations'] = $api->organizations($username);
            //$user['repositories'] = $api->repositories($username);
            $users[] = $user;
        }

        return $users;
    }
}
