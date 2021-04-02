<?php

namespace LdapRecord\Laravel\Import;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class EloquentHydrator
{
    /**
     * The configuration to pass to each hydrator.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Extra data to pass to each hydrator.
     *
     * @var array
     */
    protected $data = [];

    /**
     * The hydrators to use when importing.
     *
     * @var array
     */
    protected $hydrators = [
        Hydrators\GuidHydrator::class,
        Hydrators\DomainHydrator::class,
        Hydrators\AttributeHydrator::class,
    ];

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
     * Extra data to pass to each hydrator.
     *
     * @param array $data
     *
     * @return $this
     */
    public function with(array $data = [])
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Hydrate the database model with the LDAP user.
     *
     * @param LdapModel     $user
     * @param EloquentModel $database
     *
     * @return void
     */
    public function hydrate(LdapModel $user, EloquentModel $database)
    {
        foreach ($this->hydrators as $hydrator) {
            $hydrator::with($this->config, $this->data)->hydrate($user, $database);
        }
    }
}
