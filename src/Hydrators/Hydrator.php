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
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Create a new hydrator instance.
     *
     * @param array $config
     *
     * @return static
     */
    public static function with(array $config = [])
    {
        return new static($config);
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
