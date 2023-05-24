<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Models\Attributes\EscapedValue;

class UnescapedValue extends EscapedValue
{
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->get();
    }

    /**
     * {@inheritdoc}
     */
    public function get(): string
    {
        // Don't escape values.
        return (string) $this->value;
    }
}
