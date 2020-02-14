<!-- readme.md -->

<p align="center">
    <img src="https://ldaprecord.com/assets/img/logo.png" width="400">
</p>

<p align="center">
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/Built_for-Laravel-green.svg?style=flat-square"></a>
    <a href="https://travis-ci.com/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/travis/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
    <a href="https://scrutinizer-ci.com/g/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/scrutinizer/g/directorytree/ldaprecord-laravel/master.svg?style=flat-square"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/dt/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/v/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord-laravel"><img src="https://img.shields.io/packagist/l/directorytree/ldaprecord-laravel.svg?style=flat-square"></a>
</p>

<p align="center">
    Easily authenticate and synchronize LDAP users into your Laravel application.
</p>

<h4 align="center">
    <a href="https://ldaprecord.com/docs/laravel/quickstart">Quickstart</a>
    <span> · </span>
    <a href="https://ldaprecord.com/docs/laravel">Documentation</a>
</h4>

🔑 **Authenticate LDAP users into your application.**

Using the built-in authentication driver, easily allow LDAP users to log into your application and control which users can login via [Scopes](https://ldaprecord.com/docs/models/#query-scopes) and [Rules](https://ldaprecord.com/docs/laravel/auth/configuration/#rules).

🔄**Easily Import & Synchronize LDAP users.**

Users can be imported into your database upon first login,
or you can import your entire directory via a simple [command](https://ldaprecord.com/docs/laravel/auth/importing): `php artisan ldap:import`.

💼 **Multi-Domain Support.**

Authenticate users from as many LDAP domains as you'd like. Support comes [out of the box](https://ldaprecord.com/docs/laravel/auth/multi-domain).

🎩 **Eloquent Query Builder.**

Search for LDAP records with a [fluent and easy to use interface](https://ldaprecord.com/docs/searching) you're used to. You'll feel right at home.

✏️ **ActiveRecord LDAP Models.**

LDAP records are returned as [individual models](https://ldaprecord.com/docs/models). Easily create
and update models then persist them to your LDAP server with a simple `save()`.

---

<h3 align="center">LdapRecord-Laravel is Sponsorware™</h3>

<p align="center">If you really enjoy using LdapRecord-Laravel, a <a href="https://github.com/sponsors/stevebauman">sponsorship</a> would mean the world :pray:</p>

<p align="center">Thank you for your consideration :heart:</p>
