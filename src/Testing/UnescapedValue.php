<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Models\Attributes\EscapedValue;

class UnescapedValue extends EscapedValue
{
    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return (string) $this->get();
    }

    /**
     * {@inheritDoc}
     */
    public function get()
    {
        // Don't escape values.
        return $this->value;
    }
}
