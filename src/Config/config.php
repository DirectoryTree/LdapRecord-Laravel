<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    |
    | This option stores the domains your application connects to.
    |
    */

    'domains' => [
        // \App\Ldap\MyDomain::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | This option enables logging all LDAP operations on all configured
    | connections such as bind requests and CRUD operations.
    |
    | Log entries will be created in your default logging stack.
    |
    | This option is extremely helpful for debugging connectivity issues.
    |
    */

    'logging' => env('LDAP_LOGGING', false),

];
