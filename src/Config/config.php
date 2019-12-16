<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    |
    | The LDAP domains your application connects to.
    |
    */

    'domains' => [
        // 'default' => \App\Ldap\MyDomain::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Whether to enable authentication logging.
    |
    */

    'logging' => env('LDAP_LOGGING', true),

];
