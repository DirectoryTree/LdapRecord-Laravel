<?php

namespace LdapRecord\Laravel;

use LdapRecord\Container;
use LdapRecord\LdapRecordException;

class DomainRegistrar
{
    /**
     * The LDAP domains of the application.
     *
     * @var Domain[]
     */
    protected $domains = [];

    /**
     * Constructor.
     *
     * @param string[] $domains
     */
    public function __construct(array $domains = [])
    {
        foreach ($domains as $name => $domain) {
            $this->add(new $domain($name));
        }
    }

    /**
     * Setup the configured LDAP domains.
     */
    public function setup()
    {
        $instance = Container::getInstance();

        foreach ($this->domains as $name => $domain) {
            $connection = $domain->getNewConnection();

            if ($domain->shouldAutoConnect()) {
                try {
                    $connection->connect();
                } catch (LdapRecordException $ex) {
                    if (config('ldap.logging.enabled', false)) {
                        logger()->error($ex->getMessage());
                    }
                }
            }

            if (! $instance->exists($name)) {
                $instance->add($connection, $name);
            }

            $domain->setConnection($connection);
        }
    }

    /**
     * Get an LDAP domain by its name.
     *
     * @param string|null $name
     *
     * @return Domain|Domain[]
     */
    public function get($name = null)
    {
        return $name ? $this->domains[$name] : $this->domains;
    }

    /**
     * Add a domain into the repository.
     *
     * @param Domain $domain
     */
    public function add(Domain $domain)
    {
        $this->domains[$domain->getName()] = $domain;
    }

    /**
     * Set the LDAP domains of the application.
     *
     * @param Domain[] $domains
     */
    public function setDomains(array $domains = [])
    {
        $this->domains = $domains;
    }
}
