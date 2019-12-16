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
     * @param Domain[] $domains
     */
    public function __construct(array $domains = [])
    {
        $this->setDomains($domains);
    }

    /**
     * Setup the configured LDAP domains.
     */
    public function setup()
    {
        $instance = Container::getInstance();

        foreach ($this->domains as $name => $domain) {
            $connection = $instance->exists($name) ?
                $instance->get($name) : $domain->getNewConnection();

            if ($domain->shouldAutoConnect() && ! $connection->isConnected()) {
                try {
                    $connection->connect();
                } catch (LdapRecordException $ex) {
                    if (config('ldap.logging.enabled', false)) {
                        logger()->error($ex->getMessage());
                    }
                }
            }

            // Replace the connection in the container.
            $instance->add($connection, $name);

            $domain->setConnection($connection);
        }
    }

    /**
     * Get an LDAP domain by its name.
     *
     * @param string|null $name
     *
     * @return Domain|Domain[]
     *
     * @throws RegistrarException
     */
    public function get($name = null)
    {
        if (is_null($name)) {
            return $this->domains;
        }

        if (! $this->exists($name)) {
            throw new RegistrarException("Domain '$name' does not exist.");
        }

        return $this->domains[$name];
    }

    /**
     * Determine if the domain exists in the registrar.
     *
     * @param string $name
     *
     * @return bool
     */
    public function exists($name)
    {
        return array_key_exists($name, $this->domains);
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
     * Remove a domain from the registrar.
     *
     * @param string $name
     */
    public function remove($name)
    {
        unset($this->domains[$name]);
    }

    /**
     * Set the LDAP domains of the application.
     *
     * @param Domain[] $domains
     */
    public function setDomains(array $domains = [])
    {
        foreach ($domains as $domain) {
            $this->add($domain);
        }
    }
}
