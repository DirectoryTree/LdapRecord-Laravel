<!-- readme.md -->

<p align="center">
    <img src="https://ldaprecord.com/assets/img/logo.png" width="400">
</p>

<p align="center">
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/Built_for-Laravel-green.svg?style=flat-square"></a>
    <a href="https://travis-ci.org/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/travis/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
    <a href="https://scrutinizer-ci.com/g/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/scrutinizer/g/directorytree/ldaprecord-laravel/master.svg?style=flat-square"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/dt/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/v/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/l/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
</p>

<p align="center">
    Easily authenticate and synchronize LDAP users into your Laravel application.
</p>

<h4 align="center">
    <a href="https://ldaprecord.com/laravel/quickstart">Quickstart</a>
    <span> · </span>
    <a href="https://ldaprecord.com/laravel">Documentation</a>
</h4>

- **Authenticate LDAP users into your application.** Using the built-in authentication driver, easily allow
LDAP users to log into your application and control which users can login via [Scopes](https://adldap2.github.io/Adldap2-Laravel/#/auth/setup?id=scopes) and [Rules](https://adldap2.github.io/Adldap2-Laravel/#/auth/setup?id=rules).

- **Easily Import & Synchronize LDAP users.** Users can be imported into your database upon first login,
or you can import your entire directory via a simple [command](https://adldap2.github.io/Adldap2-Laravel/#/auth/importing): `php artisan adldap:import`.

- **Eloquent Query Builder.** Search for LDAP records with a [fluent and easy to use interface](https://ldaprecord.com/docs/searching/) you're used to. You'll feel right at home.

- **Active Record LDAP Models.** LDAP records are returned as [individual models](https://ldaprecord.com/docs/models/). Easily create
and update models then persist them to your LDAP server with a simple `save()`.
