<?php

namespace LdapRecord\Laravel;

use LdapRecord\Laravel\Database\Importer;
use LdapRecord\Laravel\Database\PasswordSynchronizer;

abstract class SynchronizedDomain extends Domain
{
    /**
     * Whether passwords should be synchronized to the local database.
     *
     * @var bool
     */
    protected $syncPasswords = false;

    /**
     * Whether authentication attempts should fall back to the local database.
     *
     * @var bool
     */
    protected $loginFallback = false;

    /**
     * Get whether the domain is falling back to local database authentication.
     *
     * @return bool
     */
    public function isFallingBack() : bool
    {
        return $this->loginFallback;
    }

    /**
     * Get whether the domain is syncing passwords to the local database.
     *
     * @return bool
     */
    public function isSyncingPasswords() : bool
    {
        return $this->syncPasswords;
    }

    /**
     * Create a new domain importer.
     *
     * @return Importer
     */
    public function importer() : Importer
    {
        return app(Importer::class, ['domain' => $this]);
    }

    /**
     * Create a new password synchronizer.
     *
     * @return PasswordSynchronizer
     */
    public function passwordSynchronizer() : PasswordSynchronizer
    {
        return new PasswordSynchronizer($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getSyncAttributes() : array
    {
        return ['name' => 'cn'];
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabaseModel() : string
    {
        return 'App\User';
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabaseUsernameColumn() : string
    {
        return 'email';
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabasePasswordColumn() : string
    {
        return 'password';
    }
}
