<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ldap_object_attribute_id
 * @property string $value
 */
class LdapObjectAttributeValue extends Model
{
    use ResolvesEmulatedConnection;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}
