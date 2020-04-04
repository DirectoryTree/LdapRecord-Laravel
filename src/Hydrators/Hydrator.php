<?php

namespace LdapRecord\Laravel\Hydrators;

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
     *
     * @param array $config
     * @param array $data
     */
    public function __construct(array $config = [], array $data = [])
    {
        $this->config = $config;
        $this->data = $data;
    }

    /**
     * Create a new hydrator instance.
     *
     * @param array $config
     * @param array $data
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
     * @param LdapModel     $user
     * @param EloquentModel $database
     *
     * @return void
     */
    abstract public function hydrate(LdapModel $user, EloquentModel $database);
}
