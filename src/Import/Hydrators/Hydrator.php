<?php

namespace LdapRecord\Laravel\Import\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

abstract class Hydrator
{
    /**
     * The for the hydrator configuration.
     */
    protected array $config = [];

    /**
     * Additional data for the hydration process.
     */
    protected array $data = [];

    /**
     * Constructor.
     */
    public function __construct(array $config = [], array $data = [])
    {
        $this->config = $config;
        $this->data = $data;
    }

    /**
     * Create a new hydrator instance.
     */
    public static function with(array $config = [], array $data = []): static
    {
        return new static($config, $data);
    }

    /**
     * Hydrate the database model with the LDAP user.
     */
    abstract public function hydrate(LdapModel $object, EloquentModel $eloquent): void;
}
