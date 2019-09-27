<?php

namespace LdapRecord\Laravel\Traits;

use Illuminate\Support\Facades\Config;
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
     * @return bool
     */
    protected function passesValidation(LdapModel $user, Model $model = null)
    {
        return (new Validator(
            $this->rules($user, $model)
        ))->passes();
    }

    /**
     * Returns an array of constructed rules.
     *
     * @param LdapModel  $user
     * @param Model|null $model
     *
     * @return array
     */
    protected function rules(LdapModel $user, Model $model = null)
    {
        $rules = [];

        foreach ($this->getRules() as $rule) {
            $rules[] = new $rule($user, $model);
        }

        return $rules;
    }

    /**
     * Retrieves the configured authentication rules.
     *
     * @return array
     */
    protected function getRules()
    {
        return Config::get('ldap_auth.rules', []);
    }
}
