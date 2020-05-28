<?php

namespace LdapRecord\Laravel\Testing\Emulated;

use LdapRecord\Query\Model\ActiveDirectoryBuilder as BaseBuilder;

class ActiveDirectoryBuilder extends BaseBuilder
{
    use EmulatesModelQueries;
}
