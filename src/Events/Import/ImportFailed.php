<?php

namespace LdapRecord\Laravel\Events\Import;

use Exception;
use LdapRecord\Models\Model as LdapModel;
use Illuminate\Database\Eloquent\Model as Eloquent;

class ImportFailed
{
    /**
     * The LDAP user that was successfully imported.
     *
     * @var LdapModel
     */
    public $ldap;

    /**
     * The model belonging to the user that was imported.
     *
     * @var Eloquent
     */
    public $eloquent;

    /**
     * The exception that was thrown during import.
     *
     * @var Exception
     */
    public $exception;

    /**
     * Constructor.
     *
     * @param LdapModel $ldap
     * @param Eloquent  $eloquent
     * @param Exception $exception
     */
    public function __construct(LdapModel $ldap, Eloquent $eloquent, Exception $exception)
    {
        $this->ldap = $ldap;
        $this->eloquent = $eloquent;
        $this->exception = $exception;
    }
}
