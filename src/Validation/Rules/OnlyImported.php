<?php

namespace LdapRecord\Laravel\Validation\Rules;

class OnlyImported extends Rule
{
    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return $this->model && $this->model->exists;
    }
}
