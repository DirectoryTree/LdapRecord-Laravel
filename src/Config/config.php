<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | This array stores the connections that are added to Adldap. You can add
    | as many connections as you like.
    |
    | The key is the name of the connection you wish to use and the value is
    | an array of configuration settings.
    |
    */

    'connections' => [

        'default' => [

            /*
            |--------------------------------------------------------------------------
            | Auto Connect
            |--------------------------------------------------------------------------
            |
            | If auto connect is true, Adldap will try to automatically connect to
            | your LDAP server in your configuration. This allows you to assume
            | connectivity rather than having to connect manually
            | in your application.
            |
            | If this is set to false, you **must** connect manually before running
            | LDAP operations. Otherwise, you will receive exceptions.
            |
            */

            'auto_connect' => env('LDAP_AUTO_CONNECT', true),

            /*
            |--------------------------------------------------------------------------
            | Connection Settings
            |--------------------------------------------------------------------------
            |
            | This connection settings array is directly passed into the Adldap constructor.
            |
            | Feel free to add or remove settings you don't need.
            |
            */

            'settings' => [

                /*
                |--------------------------------------------------------------------------
                | Domain Controllers
                |--------------------------------------------------------------------------
                |
                | The domain controllers option is an array of servers located on your
                | network that serve Active Directory. You can insert as many servers or
                | as little as you'd like depending on your forest (with the
                | minimum of one of course).
                |
                | These can be IP addresses of your server(s), or the host name.
                |
                */

                'hosts' => explode(' ', env('LDAP_HOSTS', 'corp-dc1.corp.acme.org corp-dc2.corp.acme.org')),

                /*
                |--------------------------------------------------------------------------
                | Port
                |--------------------------------------------------------------------------
                |
                | The port option is used for authenticating and binding to your LDAP server.
                |
                */

                'port' => env('LDAP_PORT', 389),

                /*
                |--------------------------------------------------------------------------
                | Timeout
                |--------------------------------------------------------------------------
                |
                | The timeout option allows you to configure the amount of time in
                | seconds that your application waits until a response
                | is received from your LDAP server.
                |
                */

                'timeout' => env('LDAP_TIMEOUT', 5),

                /*
                |--------------------------------------------------------------------------
                | Base Distinguished Name
                |--------------------------------------------------------------------------
                |
                | The base distinguished name is the base distinguished name you'd
                | like to perform query operations on. An example base DN would be:
                |
                |        dc=corp,dc=acme,dc=org
                |
                | A correct base DN is required for any query results to be returned.
                |
                */

                'base_dn' => env('LDAP_BASE_DN', 'dc=corp,dc=acme,dc=org'),

                /*
                |--------------------------------------------------------------------------
                | LDAP Username & Password
                |--------------------------------------------------------------------------
                |
                | When connecting to your LDAP server, a username and password is required
                | to be able to query and run operations on your server(s). You can
                | use any user account that has these permissions. This account
                | does not need to be a domain administrator unless you
                | require changing and resetting user passwords.
                |
                */

                'username' => env('LDAP_USERNAME'),
                'password' => env('LDAP_PASSWORD'),

                /*
                |--------------------------------------------------------------------------
                | SSL & TLS
                |--------------------------------------------------------------------------
                |
                | If you need to be able to change user passwords on your server, then an
                | SSL or TLS connection is required. All other operations are allowed
                | on unsecured protocols.
                |
                | One of these options are definitely recommended if you
                | have the ability to connect to your server securely.
                |
                */

                'use_ssl' => env('LDAP_USE_SSL', false),
                'use_tls' => env('LDAP_USE_TLS', false),

            ],

        ],

    ],

];
