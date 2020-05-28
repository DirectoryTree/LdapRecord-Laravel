<?php

namespace LdapRecord\Laravel\Testing\Emulated;

use LdapRecord\Query\Model\OpenLdapBuilder as BaseBuilder;

class OpenLdapBuilder extends BaseBuilder
{
    use EmulatesModelQueries;
}
