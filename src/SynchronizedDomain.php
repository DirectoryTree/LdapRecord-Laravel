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
     * {@inheritdoc}
     */
    public function getSyncAttributes() : array
    {
        return ['name' => 'cn'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseModel() : string
    {
        return 'App\User';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseUsernameColumn() : string
    {
        return 'email';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePasswordColumn() : string
    {
        return 'password';
    }
}
