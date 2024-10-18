<?php

namespace LdapRecord\Laravel\Events\Auth;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Models\Model as LdapModel;

abstract class RuleEvent extends Event
{
    /**
     * The authentication rule.
     */
    public Rule $rule;

    /**
     * Constructor.
     */
    public function __construct(Rule $rule, LdapModel $object, ?EloquentModel $eloquent = null)
    {
        parent::__construct($object, $eloquent);

        $this->rule = $rule;
    }
}
