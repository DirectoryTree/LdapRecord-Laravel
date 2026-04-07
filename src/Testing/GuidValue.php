<?php

namespace LdapRecord\Laravel\Testing;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\Guid;

class GuidValue
{
    /**
     * Convert a GUID value (possibly encoded hex) to a UUID string.
     */
    public static function toUuid(string $value): string
    {
        // If the value contains backslashes, it's an encoded hex GUID (e.g., \70\1a\3c\c4...)
        if (str_contains($value, '\\')) {
            try {
                // Remove backslashes to get the hex string, then convert to binary.
                $hexOnly = str_replace('\\', '', $value);
                $binary = hex2bin($hexOnly);

                if ($binary !== false && strlen($binary) === 16) {
                    return (new Guid($binary))->getValue();
                }
            } catch (InvalidArgumentException) {
                // Fall through to return original value.
            }
        }

        // If it's already a valid UUID or GUID string, try to normalize it.
        if (Guid::isValid($value)) {
            try {
                return (new Guid($value))->getValue();
            } catch (InvalidArgumentException) {
                // Fall through to return original value.
            }
        }

        return $value;
    }
}
