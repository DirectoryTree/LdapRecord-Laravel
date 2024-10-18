<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Models\Model as LdapRecord;

interface Rule
{
    /**
     * Determine if the rule passes validation.
     */
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool;
}
