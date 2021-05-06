<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Models\Attributes\EscapedValue;

class UnescapedValue extends EscapedValue
{
    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return (string) $this->get();
    }

    /**
     * @inheritdoc
     */
    public function get()
    {
        // Don't escape values.
        return $this->value;
    }
}
