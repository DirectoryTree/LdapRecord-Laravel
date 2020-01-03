<?php

namespace LdapRecord\Laravel\Database;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Laravel\SynchronizedDomain;
use LdapRecord\Models\Model as LdapModel;
use UnexpectedValueException;

class Importer
{
    /**
     * The scope to utilize for locating the LDAP user to synchronize.
     *
     * @var string
     */
    public static $scope = UserImportScope::class;

    /**
     * The LDAP domain to use for importing.
     *
     * @var SynchronizedDomain
     */
    protected $domain;

    /**
     * Sets the scope to use for locating LDAP users.
     *
     * @param $scope
     */
    public static function useScope($scope)
    {
        static::$scope = $scope;
    }
}
