<?php

namespace LdapRecord\Laravel\Testing\Emulated;

use LdapRecord\Query\Model\Builder;

class ModelBuilder extends Builder
{
    use EmulatesModelQueries;
}
