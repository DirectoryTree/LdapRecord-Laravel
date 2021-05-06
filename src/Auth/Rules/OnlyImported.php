<?php

namespace LdapRecord\Laravel\Auth\Rules;

use LdapRecord\Laravel\Auth\Rule;

class OnlyImported extends Rule
{
    /**
     * @inheritdoc
     */
    public function isValid()
    {
        return $this->model && $this->model->exists;
    }
}
