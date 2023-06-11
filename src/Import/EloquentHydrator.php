<?php

namespace LdapRecord\Laravel\Import;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class EloquentHydrator
{
    /**
     * The configuration to pass to each hydrator.
     */
    protected array $config = [];

    /**
     * Extra data to pass to each hydrator.
     */
    protected array $data = [];

    /**
     * The hydrators to use when importing.
     */
    protected array $hydrators = [
        Hydrators\GuidHydrator::class,
        Hydrators\DomainHydrator::class,
        Hydrators\AttributeHydrator::class,
    ];

    /**
     * Constructor.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Extra data to pass to each hydrator.
     */
    public function with(array $data = []): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Hydrate the database model with the LDAP user.
     */
    public function hydrate(LdapModel $user, EloquentModel $database): void
    {
        foreach ($this->hydrators as $hydrator) {
            $hydrator::with($this->config, $this->data)->hydrate($user, $database);
        }
    }
}
