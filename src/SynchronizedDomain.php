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
        return app(PasswordSynchronizer::class, ['domain' => $this]);
    }

    /**
     * Get the database sync attributes.
     *
     * @return array
     */
    public function getDatabaseSyncAttributes() : array
    {
        return ['name' => 'cn'];
    }

    /**
     * Get the database model.
     *
     * @return string
     */
    public function getDatabaseModel() : string
    {
        return 'App\User';
    }

    /**
     * Get the database username column.
     *
     * @return string
     */
    public function getDatabaseUsernameColumn() : string
    {
        return 'email';
    }

    /**
     * Get the database password column.
     *
     * @return string
     */
    public function getDatabasePasswordColumn() : string
    {
        return 'password';
    }
}
