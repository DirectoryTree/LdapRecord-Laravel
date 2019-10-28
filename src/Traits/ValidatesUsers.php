<?php

namespace LdapRecord\Laravel\Traits;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;
use LdapRecord\Laravel\Validation\Validator;

trait ValidatesUsers
{
    /**
     * Determines if the model passes validation.
     *
     * @param LdapModel  $user
     * @param Model      $model
     *
     * @return Validator
     */
    protected function getLdapUserValidator(LdapModel $user, Model $model = null)
    {
        return new Validator($this->getLdapRules($user, $model));
    }

    /**
     * Returns an array of constructed rules.
     *
     * @param LdapModel  $user
     * @param Model|null $model
     *
     * @return array
     */
    protected function getLdapRules(LdapModel $user, Model $model = null)
    {
        $rules = [];

        foreach (config('ldap_auth.rules', []) as $rule) {
            $rules[] = new $rule($user, $model);
        }

        return $rules;
    }
}
