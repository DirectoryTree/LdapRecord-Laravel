<?php

namespace LdapRecord\Laravel;

use LdapRecord\Laravel\Database\Importer;
use LdapRecord\Laravel\Database\PasswordSynchronizer;

class SynchronizedDomain extends Domain
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
    public function isFallingBack()
    {
        return $this->loginFallback;
    }

    /**
     * Get whether the domain is syncing passwords to the local database.
     *
     * @return bool
     */
    public function isSyncingPasswords()
    {
        return $this->syncPasswords;
    }

    /**
     * Create a new domain importer.
     *
     * @return Importer
     */
    public function importer()
    {
        return new Importer($this);
    }

    /**
     * Create a new password synchronizer.
     *
     * @return PasswordSynchronizer
     */
    public function passwordSynchronizer()
    {
        return new PasswordSynchronizer($this);
    }

    /**
     * Get the database sync attributes.
     *
     * @return array
     */
    public function getDatabaseSyncAttributes()
    {
        return ['name' => 'cn'];
    }

    /**
     * Get the database model.
     *
     * @return string
     */
    public function getDatabaseModel()
    {
        return 'App\User';
    }

    /**
     * Get the database username column.
     *
     * @return string
     */
    public function getDatabaseUsernameColumn()
    {
        return 'email';
    }

    /**
     * Get the database password column.
     *
     * @return string
     */
    public function getDatabasePasswordColumn()
    {
        return 'password';
    }
}
