<?php

namespace LdapRecord\Laravel\Auth\Rules;

use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Models\Model as LdapRecord;

class OnlyImported implements Rule
{
    /**
     * {@inheritdoc}
     */
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        return $model?->exists;
    }
}
