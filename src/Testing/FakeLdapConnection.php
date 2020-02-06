<?php

namespace LdapRecord\Laravel\Testing;

use Closure;
use LdapRecord\DetailedError;
use LdapRecord\Ldap;

class FakeLdapConnection extends Ldap
{
    /**
     * Set whether the fake bind attempt will pass.
     *
     * @param bool $bound
     *
     * @return $this
     */
    public function setBound($bound = false)
    {
        $this->bound = $bound;

        return $this;
    }

    /**
     * Fake a bind attempt.
     *
     * @return bool
     */
    public function bind($username, $password)
    {
        return $this->bound;
    }

    public function errNo()
    {
        return 0;
    }

    public function getLastError()
    {
        return '';
    }

    public function getDetailedError()
    {
        return new DetailedError($this->errNo(), $this->getLastError(), 'diag');
    }

    protected function executeFailableOperation(Closure $operation)
    {
        // Do nothing.
    }
}
