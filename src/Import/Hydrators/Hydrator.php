<?php

namespace LdapRecord\Laravel\Import\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

abstract class Hydrator
{
    /**
     * The attributes to hydrate.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Extra data for the hydration process.
     *
     * @var array
     */
    protected $data = [];

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
     *
     *
     * @return static
     */
    public static function with(array $config = [], array $data = [])
    {
        return new static($config, $data);
    }

    /**
     * Hydrate the database model with the LDAP user.
     *
     *
     * @return void
     */
    abstract public function hydrate(LdapModel $object, EloquentModel $eloquent);
}
