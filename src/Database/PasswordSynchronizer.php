<?php

namespace LdapRecord\Laravel\Database;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LdapRecord\Laravel\SynchronizedDomain;

class PasswordSynchronizer
{
    /**
     * The LDAP domain.
     *
     * @var SynchronizedDomain
     */
    protected $domain;

    /**
     * Constructor.
     *
     * @param SynchronizedDomain $domain
     */
    public function __construct(SynchronizedDomain $domain)
    {
        $this->domain = $domain;
    }

    /**
     * Synchronize the models password.
     *
     * @param Model       $model
     * @param string|null $password
     *
     * @return Model
     */
    public function run(Model $model, string $password = null): Model
    {
        if ($this->hasPasswordColumn()) {
            $password = $this->domain->isSyncingPasswords() ?
                $password : Str::random();

            if ($this->passwordNeedsUpdate($model, $password)) {
                $this->setPassword($model, $password);
            }
        }

        return $model;
    }


}
