<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Whether to enable logging and which authentication events are logged.
    |
    */

    'logging' => [

        'enabled' => env('LDAP_LOGGING', true),

        'events' => [

            \LdapRecord\Laravel\Events\Importing::class                 => \LdapRecord\Laravel\Listeners\LogImport::class,
            \LdapRecord\Laravel\Events\Synchronized::class              => \LdapRecord\Laravel\Listeners\LogSynchronized::class,
            \LdapRecord\Laravel\Events\Synchronizing::class             => \LdapRecord\Laravel\Listeners\LogSynchronizing::class,
            \LdapRecord\Laravel\Events\Authenticated::class             => \LdapRecord\Laravel\Listeners\LogAuthenticated::class,
            \LdapRecord\Laravel\Events\Authenticating::class            => \LdapRecord\Laravel\Listeners\LogAuthentication::class,
            \LdapRecord\Laravel\Events\AuthenticationFailed::class      => \LdapRecord\Laravel\Listeners\LogAuthenticationFailure::class,
            \LdapRecord\Laravel\Events\AuthenticationRejected::class    => \LdapRecord\Laravel\Listeners\LogAuthenticationRejection::class,
            \LdapRecord\Laravel\Events\AuthenticationSuccessful::class  => \LdapRecord\Laravel\Listeners\LogAuthenticationSuccess::class,
            \LdapRecord\Laravel\Events\DiscoveredWithCredentials::class => \LdapRecord\Laravel\Listeners\LogDiscovery::class,
            \LdapRecord\Laravel\Events\AuthenticatedWithWindows::class  => \LdapRecord\Laravel\Listeners\LogWindowsAuth::class,
            \LdapRecord\Laravel\Events\AuthenticatedModelTrashed::class => \LdapRecord\Laravel\Listeners\LogTrashedModel::class,

        ],
    ],

];
